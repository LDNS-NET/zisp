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

    /**
     * Sync Invoices to QuickBooks.
     */
    public function syncInvoices()
    {
        $service = $this->getDataServiceForTenant();
        if (!$service) return;

        // Fetch invoices that haven't been synced yet
        $invoices = TenantInvoice::where('tenant_id', $this->tenantId)
            ->whereNull('qbo_id') // We'll need to add this column or store it in a map
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $qboInvoice = Invoice::create([
                    "Line" => [
                        [
                            "Amount" => $invoice->amount,
                            "DetailType" => "SalesItemLineDetail",
                            "SalesItemLineDetail" => [
                                "ItemRef" => [
                                    "value" => "1", // Fallback to "Services" item
                                    "name" => "Services"
                                ]
                            ]
                        ]
                    ],
                    "CustomerRef" => [
                        "value" => $this->getOrCreateCustomer($invoice->user)
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
     * Get or create a Customer in QBO based on internal user.
     */
    protected function getOrCreateCustomer($userId)
    {
        // For now, return a placeholder or implement lookup logic
        // Ideally, we'd search for a customer with this email/name or keep a mapping
        return "1"; // Placeholder
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
                $qboPayment = Payment::create([
                    "CustomerRef" => [
                        "value" => $this->getOrCreateCustomer($payment->user_id)
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
     * Sync Expenses to QuickBooks.
     */
    public function syncExpenses()
    {
        $service = $this->getDataServiceForTenant();
        if (!$service) return;

        $expenses = TenantExpenses::whereNull('qbo_id')
            ->get(); // TenantExpenses already has a global scope for created_by

        foreach ($expenses as $expense) {
            try {
                $qboPurchase = Purchase::create([
                    "PaymentType" => "Cash",
                    "AccountRef" => [
                        "value" => "1" // Default expense account
                    ],
                    "Line" => [
                        [
                            "Amount" => $expense->amount,
                            "DetailType" => "AccountBasedExpenseLineDetail",
                            "AccountBasedExpenseLineDetail" => [
                                "AccountRef" => [
                                    "value" => "1"
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
     * Sync Equipment to QuickBooks Items.
     */
    public function syncEquipment()
    {
        $service = $this->getDataServiceForTenant();
        if (!$service) return;

        $equipments = TenantEquipment::where('tenant_id', $this->tenantId)
            ->whereNull('qbo_id')
            ->get();

        foreach ($equipments as $equipment) {
            try {
                $qboItem = Item::create([
                    "Name" => $equipment->name . " (" . $equipment->serial_number . ")",
                    "Type" => "NonInventory",
                    "IncomeAccountRef" => [
                        "value" => "1"
                    ],
                    "ExpenseAccountRef" => [
                        "value" => "1"
                    ],
                    "PurchaseCost" => $equipment->price,
                ]);

                $result = $service->Add($qboItem);
                if ($result) {
                    $equipment->update(['qbo_id' => $result->Id]);
                }
            } catch (\Exception $e) {
                Log::error("QuickBooks Sync Equipment Failed: " . $e->getMessage());
            }
        }
    }
}
