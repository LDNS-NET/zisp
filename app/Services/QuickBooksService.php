<?php

namespace App\Services;

use App\Models\TenantSetting;
use App\Models\Tenants\TenantInvoice;
use App\Models\Tenants\TenantPayment;
use App\Models\TenantExpenses;
use App\Models\Tenants\TenantEquipment;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\Facades\Invoice;
use QuickBooksOnline\API\Facades\Payment;
use QuickBooksOnline\API\Facades\Purchase;
use QuickBooksOnline\API\Facades\Item;
use QuickBooksOnline\API\Facades\Account;
use QuickBooksOnline\API\Facades\Customer;
use Illuminate\Support\Facades\Log;

class QuickBooksService
{
    protected $dataService;
    protected $tenantId;

    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->dataService = $this->createDataService();
    }

    protected function createDataService()
    {
        return DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => config('services.quickbooks.client_id'),
            'ClientSecret' => config('services.quickbooks.client_secret'),
            'RedirectURI' => config('services.quickbooks.redirect_uri'),
            'scope' => 'com.intuit.quickbooks.accounting',
            'baseUrl' => config('services.quickbooks.environment') === 'production' ? 'Production' : 'Development',
        ]);
    }

    public function getAuthorizationUrl()
    {
        $OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();
        return $OAuth2LoginHelper->getAuthorizationCodeURL();
    }

    public function exchangeCodeForToken($code, $realmId)
    {
        $OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();
        $accessToken = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken($code, $realmId);
        $this->storeTokens($accessToken, $realmId);
        return $accessToken;
    }

    protected function storeTokens($accessToken, $realmId)
    {
        $settings = [
            'realmId' => $realmId,
            'access_token' => $accessToken->getAccessToken(),
            'refresh_token' => $accessToken->getRefreshToken(),
            'access_token_expires_at' => $accessToken->getAccessTokenExpiresAt(),
            'refresh_token_expires_at' => $accessToken->getRefreshTokenExpiresAt(),
            'connected_at' => now()->toDateTimeString(),
        ];

        TenantSetting::updateOrCreate(
            ['tenant_id' => $this->tenantId, 'category' => 'quickbooks'],
            ['settings' => $settings]
        );
    }

    public function getDataServiceForTenant()
    {
        $setting = TenantSetting::where('tenant_id', $this->tenantId)
            ->where('category', 'quickbooks')
            ->first();

        if (!$setting || empty($setting->settings)) {
            return null;
        }

        $tokens = $setting->settings;
        $this->dataService->updateOAuth2Token($this->mapTokensToOAuth2AccessToken($tokens));

        if (strtotime($tokens['access_token_expires_at']) <= (time() + 300)) {
            $this->refreshToken($setting);
        }

        return $this->dataService;
    }

    protected function refreshToken($setting)
    {
        try {
            $OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();
            $refreshedToken = $OAuth2LoginHelper->refreshAccessTokenWithRefreshToken($setting->settings['refresh_token']);
            $this->storeTokens($refreshedToken, $setting->settings['realmId']);
            $this->dataService->updateOAuth2Token($refreshedToken);
        } catch (\Exception $e) {
            Log::error("QuickBooks Token Refresh Failed for Tenant {$this->tenantId}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function mapTokensToOAuth2AccessToken($tokens)
    {
        $accessToken = new \QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken(
            config('services.quickbooks.client_id'),
            config('services.quickbooks.client_secret'),
            $tokens['access_token'],
            $tokens['refresh_token']
        );
        $accessToken->setRealmID($tokens['realmId']);
        return $accessToken;
    }

    // --- Dynamic Lookup Helpers ---

    /**
     * Get or Create a Customer in QBO by Email.
     */
    protected function getOrCreateCustomer($user)
    {
        if (!$user) return null;

        $service = $this->dataService;
        
        // Escape single quotes for query
        $email = addslashes($user->email);
        $query = "SELECT * FROM Customer WHERE PrimaryEmailAddr.Address = '{$email}' MAXRESULTS 1";
        
        try {
            $result = $service->Query($query);
            if ($result && count($result) > 0) {
                return $result[0]->Id;
            }

            // Create new customer
            $customerObj = Customer::create([
                "DisplayName" => $user->name . " (" . $user->id . ")", // Ensure uniqueness
                "PrimaryEmailAddr" => [
                    "Address" => $user->email
                ],
                "GivenName" => explode(' ', $user->name)[0] ?? $user->name,
                "FamilyName" => explode(' ', $user->name)[1] ?? '',
                "Mobile" => [
                    "FreeFormNumber" => $user->phone ?? ''
                ]
            ]);

            $res = $service->Add($customerObj);
            if ($res) return $res->Id;

        } catch (\Exception $e) {
            Log::error("QuickBooks Customer Sync Failed: " . $e->getMessage());
        }

        return null; // Should handle this error upstream
    }

    /**
     * Get or Create an Account (Income or Expense).
     */
    protected function getOrCreateAccount($name, $accountType, $accountSubType = null)
    {
        $service = $this->dataService;
        $cleanName = addslashes($name);
        $query = "SELECT * FROM Account WHERE Name = '{$cleanName}' MAXRESULTS 1";

        try {
            $result = $service->Query($query);
            if ($result && count($result) > 0) {
                return $result[0]->Id;
            }

            // Create new account
            $accountData = [
                "Name" => $name,
                "AccountType" => $accountType,
            ];
            
            if ($accountSubType) {
                $accountData["AccountSubType"] = $accountSubType;
            }

            $accountObj = Account::create($accountData);
            $res = $service->Add($accountObj);
            if ($res) return $res->Id;

        } catch (\Exception $e) {
            Log::error("QuickBooks Account Sync Failed: " . $e->getMessage());
            // Fallback: Try to find ANY account of that type
            $fallbackQuery = "SELECT * FROM Account WHERE AccountType = '{$accountType}' MAXRESULTS 1";
            $fallback = $service->Query($fallbackQuery);
            if ($fallback && count($fallback) > 0) return $fallback[0]->Id;
        }

        return null;
    }

    /**
     * Get or Create an Item (Service or NonInventory).
     */
    protected function getOrCreateItem($name, $type, $incomeAccountId, $expenseAccountId = null, $price = 0)
    {
        $service = $this->dataService;
        $cleanName = addslashes($name);
        $query = "SELECT * FROM Item WHERE Name = '{$cleanName}' MAXRESULTS 1";

        try {
            $result = $service->Query($query);
            if ($result && count($result) > 0) {
                return $result[0]->Id;
            }

            // Create new item
            $itemData = [
                "Name" => $name,
                "Type" => $type,
                "UnitPrice" => $price,
                "IncomeAccountRef" => [
                    "value" => $incomeAccountId
                ]
            ];

            if ($expenseAccountId) {
                $itemData["ExpenseAccountRef"] = [
                    "value" => $expenseAccountId
                ];
            }

            $itemObj = Item::create($itemData);
            $res = $service->Add($itemObj);
            if ($res) return $res->Id;

        } catch (\Exception $e) {
            Log::error("QuickBooks Item Sync Failed ({$name}): " . $e->getMessage());
        }
        return null;
    }


    // --- Sync Methods ---

    /**
     * Sync Invoices to QuickBooks using dynamic Customers and Items.
     */
    public function syncInvoices()
    {
        $service = $this->getDataServiceForTenant();
        if (!$service) return;

        // Ensure Income Account exists
        $incomeAccountId = $this->getOrCreateAccount("Sales of Product Income", "Income", "SalesOfProductIncome");

        $invoices = TenantInvoice::where('tenant_id', $this->tenantId)
            ->whereNull('qbo_id')
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $customerId = $this->getOrCreateCustomer($invoice->user);
                if (!$customerId) continue;

                // Use the package name as the Item name, fallback to "General Service"
                $packageName = $invoice->package ?? 'General Internet Service';
                $itemId = $this->getOrCreateItem($packageName, 'Service', $incomeAccountId, null, $invoice->amount);

                if (!$itemId) continue;

                $qboInvoice = Invoice::create([
                    "Line" => [
                        [
                            "Amount" => $invoice->amount,
                            "DetailType" => "SalesItemLineDetail",
                            "SalesItemLineDetail" => [
                                "ItemRef" => [
                                    "value" => $itemId,
                                    "name" => $packageName
                                ],
                                "Qty" => 1
                            ]
                        ]
                    ],
                    "CustomerRef" => [
                        "value" => $customerId
                    ],
                    "DocNumber" => $invoice->tenant_invoice,
                    "TxnDate" => $invoice->issued_on,
                    "DueDate" => $invoice->due_on,
                ]);

                $result = $service->Add($qboInvoice);
                if ($result) {
                    $invoice->update(['qbo_id' => $result->Id]);
                }
            } catch (\Exception $e) {
                Log::error("QuickBooks Sync Invoice Failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Sync Payments to QuickBooks.
     */
    public function syncPayments()
    {
        $service = $this->getDataServiceForTenant();
        if (!$service) return;

        $payments = TenantPayment::where('tenant_id', $this->tenantId)
            ->whereNull('qbo_id')
            ->where('status', 'Completed')
            ->get();

        foreach ($payments as $payment) {
            try {
                // Must have a customer to record a payment
                $customerId = $this->getOrCreateCustomer($payment->user_id ? \App\Models\User::find($payment->user_id) : null);
                if (!$customerId) continue;

                $qboPayment = Payment::create([
                    "CustomerRef" => [
                        "value" => $customerId
                    ],
                    "TotalAmt" => $payment->amount,
                    "PaymentRefNum" => $payment->receipt_number,
                    "TxnDate" => $payment->paid_at ? $payment->paid_at->toDateString() : now()->toDateString(),
                ]);

                $result = $service->Add($qboPayment);
                if ($result) {
                    $payment->update(['qbo_id' => $result->Id]);
                }
            } catch (\Exception $e) {
                Log::error("QuickBooks Sync Payment Failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Sync Expenses to QuickBooks using dynamic generic Account.
     */
    public function syncExpenses()
    {
        $service = $this->getDataServiceForTenant();
        if (!$service) return;

        // Ensure Expense Account exists
        $expenseAccountId = $this->getOrCreateAccount("General Expenses", "Expense", "OtherBusinessExpenses");
        $cashAccountId = $this->getOrCreateAccount("Cash on Hand", "Asset", "CashOnHand");

        $expenses = TenantExpenses::whereNull('qbo_id')->get();

        foreach ($expenses as $expense) {
            try {
                if (!$expenseAccountId) continue;

                $qboPurchase = Purchase::create([
                    "PaymentType" => "Cash",
                    "AccountRef" => [
                        "value" => $cashAccountId ?? "1" 
                    ],
                    "Line" => [
                        [
                            "Amount" => $expense->amount,
                            "DetailType" => "AccountBasedExpenseLineDetail",
                            "AccountBasedExpenseLineDetail" => [
                                "AccountRef" => [
                                    "value" => $expenseAccountId
                                ]
                            ],
                            "Description" => $expense->description
                        ]
                    ],
                    "TxnDate" => $expense->incurred_on
                ]);

                $result = $service->Add($qboPurchase);
                if ($result) {
                    $expense->update(['qbo_id' => $result->Id]);
                }
            } catch (\Exception $e) {
                Log::error("QuickBooks Sync Expense Failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Sync Equipment to QuickBooks Items with price details.
     */
    public function syncEquipment()
    {
        $service = $this->getDataServiceForTenant();
        if (!$service) return;

        // Ensure Accounts exist
        $incomeAccountId = $this->getOrCreateAccount("Sales of Product Income", "Income", "SalesOfProductIncome");
        $expenseAccountId = $this->getOrCreateAccount("Cost of Goods Sold", "Cost of Goods Sold", "SuppliesMaterialsCogs");

        $equipments = TenantEquipment::where('tenant_id', $this->tenantId)
            ->whereNull('qbo_id')
            ->get();

        foreach ($equipments as $equipment) {
            try {
                $itemName = $equipment->name . " (" . $equipment->model . ")";
                
                // Create item with current purchase price
                $itemId = $this->getOrCreateItem(
                    $itemName, 
                    'NonInventory', 
                    $incomeAccountId, 
                    $expenseAccountId, 
                    $equipment->price // Unit Price
                );

                if ($itemId) {
                    // Update the local record to link it
                    $equipment->update(['qbo_id' => $itemId]);
                }
            } catch (\Exception $e) {
                Log::error("QuickBooks Sync Equipment Failed: " . $e->getMessage());
            }
        }
    }
}
