<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantHotspot;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\NetworkUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Jobs\CheckIntaSendPaymentStatusJob;

use App\Models\Tenants\TenantGeneralSetting;

class TenantHotspotController extends Controller
{
    /**
     * Display a listing of the tenant hotspot packages for the current tenant.
     */
    public function index()
    {
        // Extract subdomain
        $host = request()->getHost(); // e.g., user1.zyraaf.cloud
        $subdomain = explode('.', $host)[0]; // "user1"

        // Find tenant
        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

        // Get merged tenant settings (Logo, Business Name, etc.)
        $settings = TenantGeneralSetting::where('tenant_id', $tenant->id)->first();
        
        $tenantData = $tenant->toArray();
        if ($settings) {
            // Apply overrides from General Settings
            $tenantData['name'] = $settings->business_name ?: ($tenantData['name'] ?: $tenantData['id']); // Prefer business name, then tenant name, then ID
            $tenantData['logo'] = $settings->logo ? '/storage/' . $settings->logo : null; // Assume storage link
            $tenantData['support_phone'] = $settings->support_phone ?: $settings->primary_phone;
            $tenantData['support_email'] = $settings->support_email ?: $settings->primary_email;
        }

        // Get packages belonging to this tenant
        $packages = TenantHotspot::where('tenant_id', $tenant->id)->get();

        // Return to Inertia
        return inertia('Hotspot/Index', [
            'tenant' => $tenantData,
            'packages' => $packages,
        ]);
    }

    /**
     * Process hotspot package purchase via STK Push.
     */
    public function purchaseSTKPush(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|exists:tenant_hotspot_packages,id',
            'phone' => 'required|string|regex:/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/',
        ]);

        $package = $this->findTenantPackage($data['id']);
        // Forward to TenantPaymentController
        $paymentController = new TenantPaymentController();
        $request->merge([
            'phone' => $data['phone'],
            'package_id' => $package->id,
            'amount' => $package->price,
        ]);

        return $paymentController->processSTKPush($request);
    }

    /**
     * Process IntaSend checkout for hotspot packages.
     */
    public function checkout(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'hotspot_package_id' => 'required|exists:tenant_hotspot_packages,id',
                'phone' => 'required|string',
                'email' => 'nullable|email',
            ]);

         // Get current tenant from subdomain
            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];
            $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

            // Find hotspot package
            $package = $this->findTenantPackage($request->hotspot_package_id);
            $amount = $package->price;

            // Normalize phone
            $phone = $this->formatPhoneNumber($request->phone);
            if (!$phone) {
                \Log::warning('Invalid phone number format', ['phone' => $request->phone]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format. Use 07xxxxxxxx, 01xxxxxxxx, or 2547xxxxxxxx format.'
                ]);
            }

            // Create reference: HS|package_id|phone|uniqid
            $reference = "HS|{$package->id}|{$phone}|" . strtoupper(uniqid());

            // Initiate M-Pesa STK Push
            $mpesa = app(\App\Services\MpesaService::class);
            $mpesaResponse = $mpesa->stkPush(
                $phone,
                $amount,
                $reference,
                "Hotspot Package - {$package->name}"
            );

            \Log::info('M-Pesa STK Push initiated', [
                'response' => $mpesaResponse,
                'reference' => $reference,
            ]);

            if (!$mpesaResponse['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $mpesaResponse['message'] ?? 'Failed to initiate payment',
                ]);
            }

            // Find tenant admin to assign as creator
            $adminId = 1; // Default fallback
            try {
                $adminUser = \App\Models\User::first();
                if ($adminUser) {
                    $adminId = $adminUser->id;
                }
            } catch (\Exception $e) {
                \Log::warning('Could not find admin user for payment creation', ['error' => $e->getMessage()]);
            }

            // Initialize tenancy to ensure we can create users in tenant DB
            if (!tenancy()->initialized) {
                tenancy()->initialize($tenant);
            }

            // Generate user credentials
            $username = NetworkUser::generateUsername();
            $password = NetworkUser::generatePassword();
            
            // Create pending user
            $user = NetworkUser::create([
                'account_number' => 'NU' . mt_rand(1000000000, 9999999999), // Temporary fallback if model hook fails
                'username' => $username,
                'password' => $password,
                'phone' => $phone,
                'type' => 'hotspot',
                'hotspot_package_id' => $package->id,
                'status' => 'pending', // Pending payment
                'registered_at' => now(),
                'created_by' => $adminId,
            ]);

            // Create payment record immediately with pending status
            $payment = TenantPayment::create([
                'user_id' => $user->id, // Link to user
                'phone' => $phone,
                'hotspot_package_id' => $package->id,
                'package_id' => null,
                'amount' => $amount,
                'currency' => 'KES',
                'payment_method' => 'mpesa',
                'receipt_number' => $reference,
                'status' => 'pending',
                'checked' => false,
                'disbursement_type' => 'pending',
                'created_by' => $adminId,
                'checkout_request_id' => $mpesaResponse['checkout_request_id'] ?? null,
                'merchant_request_id' => $mpesaResponse['merchant_request_id'] ?? null,
                'intasend_reference' => null, // Legacy field, keeping null
                'intasend_checkout_id' => null, // Legacy field, keeping null
                'response' => $mpesaResponse['response'] ?? [],
            ]);

            \Log::info('Payment record created', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'checkout_request_id' => $payment->checkout_request_id,
                'user_id' => $user->id,
                'username' => $username
            ]);

            // Dispatch job to check payment status (using payment model)
            \App\Jobs\CheckMpesaPaymentStatusJob::dispatch($payment, $tenant->id)
                ->delay(now()->addSeconds(30));

            return response()->json([
                'success' => true,
                'message' => $mpesaResponse['message'] ?? 'STK Push sent. Complete payment on your phone.',
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('STK Push exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Check payment status (checks database only, relies on IntaSend callback to update status)
     */
    public function checkPaymentStatus($identifier)
    {
        try {
            \Log::info('Checking payment status from database', ['identifier' => $identifier]);

            // Find the payment record
            $payment = TenantPayment::where('id', $identifier)
                ->orWhere('checkout_request_id', $identifier)
                ->orWhere('intasend_reference', $identifier)
                ->orWhere('receipt_number', $identifier)
                ->orderByDesc('id')
                ->first();

            if (!$payment) {
                \Log::warning('No payment record found', ['identifier' => $identifier]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found'
                ]);
            }

            \Log::info('Payment record status', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'checked' => $payment->checked
            ]);

            // If already paid, return user credentials
            if ($payment->status === 'paid') {
                $user = NetworkUser::where('phone', $payment->phone)
                    ->where('type', 'hotspot')
                    ->first();
                    
                if ($user) {
                    return response()->json([
                        'success' => true,
                        'status' => 'paid',
                        'user' => [
                            'username' => $user->username,
                            'password' => $user->password,
                            'expires_at' => $user->expires_at->toDateTimeString(),
                        ]
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment confirmed, generating user account...'
                ]);
            }

            // Return current status
            return response()->json([
                'success' => true,
                'status' => $payment->status,
                'message' => $payment->status === 'pending' 
                    ? 'Payment pending. Please complete payment on your phone, then click "I have Paid" again.'
                    : 'Payment ' . $payment->status
            ]);

        } catch (\Exception $e) {
            \Log::error('Check status exception', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status'
            ]);
        }
    }

    /**
     * Handle M-Pesa callback.
     */
    public function callback(Request $request)
    {
        try {
            \Log::info('M-Pesa callback received', [
                'request_data' => $request->all(),
                'ip' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            // Parse M-Pesa callback data
            $mpesa = app(\App\Services\MpesaService::class);
            $callbackData = $mpesa->parseCallback($request->all());

            \Log::info('M-Pesa callback parsed', ['parsed_data' => $callbackData]);

            $checkoutRequestId = $callbackData['checkout_request_id'];
            $status = $callbackData['status'];

            if (!$checkoutRequestId) {
                \Log::warning('Callback missing CheckoutRequestID', ['data' => $request->all()]);
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Missing CheckoutRequestID']);
            }

            // Find payment by CheckoutRequestID
            $payment = TenantPayment::where('checkout_request_id', $checkoutRequestId)
                ->orWhere('intasend_reference', $checkoutRequestId) // Fallback for older records
                ->orderByDesc('id')
                ->first();

            if (!$payment) {
                \Log::warning('Payment not found for callback', ['checkout_request_id' => $checkoutRequestId]);
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Payment not found']);
            }

            if ($payment->status === 'paid') {
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Already processed']);
            }

            if ($status === 'paid') {
                \Log::info('Payment confirmed via M-Pesa callback', [
                    'payment_id' => $payment->id,
                    'mpesa_receipt' => $callbackData['mpesa_receipt_number']
                ]);

                $payment->status = 'paid';
                $payment->checked = true;
                $payment->transaction_id = $callbackData['mpesa_receipt_number']; // Keep filling this for compatibility
                $payment->mpesa_receipt_number = $callbackData['mpesa_receipt_number'];
                $payment->result_code = $callbackData['result_code'];
                $payment->result_desc = $callbackData['result_desc'];
                $payment->response = array_merge($payment->response ?? [], $callbackData['raw_data']);
                $payment->paid_at = now();
                $payment->save();

                $package = $this->findTenantPackage($payment->hotspot_package_id);
                $this->handleSuccessfulPayment($payment, $package);

                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
            }

            // Handle failure/cancellation
            if (in_array($status, ['failed', 'cancelled'])) {
                $payment->status = $status;
                $payment->result_code = $callbackData['result_code'];
                $payment->result_desc = $callbackData['result_desc'];
                $payment->response = array_merge($payment->response ?? [], $callbackData['raw_data']);
                $payment->save();

                \Log::info('Payment marked as ' . $status, ['payment_id' => $payment->id]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Acknowledged']);
            }

            \Log::info('Payment not confirmed in callback', ['status' => $status]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Acknowledged']);

        } catch (\Exception $e) {
            \Log::error('M-Pesa callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Error processing callback']);
        }
    }

    /**
     * Handle post-payment success actions.
     */
    private function handleSuccessfulPayment(TenantPayment $payment,  TenantHotspot $package)
    {
        try {
            // Check if user exists (linked to payment or by phone)
            $user = null;
            if ($payment->user_id) {
                $user = NetworkUser::find($payment->user_id);
            }
            
            if (!$user) {
                $user = NetworkUser::where('phone', $payment->phone)
                    ->where('type', 'hotspot')
                    ->first();
            }

            if ($user) {
                // Update existing user (Activation or Renewal)
                if ($payment->hotspot_package_id) {
                    $user->hotspot_package_id = $package->id;
                    $user->package_id = null;
                } else {
                    $user->package_id = $package->id;
                    $user->hotspot_package_id = null;
                }

                $user->expires_at = $this->calculateExpiry($package);
                $user->status = 'active'; // Ensure status is active
                $user->save();

                \Log::info('Activated/Renewed hotspot user', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'payment_id' => $payment->id,
                    'status' => 'active'
                ]);
                return;
            }

            // Fallback: Create new user if not found
            $username = NetworkUser::generateUsername();
            $password = NetworkUser::generatePassword();

            $user = NetworkUser::create([
                'account_number' => 'NU' . mt_rand(1000000000, 9999999999),
                'username' => $username,
                'password' => $password,
                'phone' => $payment->phone,
                'type' => 'hotspot',
                'hotspot_package_id' => $package->id,
                'status' => 'active',
                'expires_at' => $this->calculateExpiry($package),
                'registered_at' => now(),
                'created_by' => $payment->created_by,
            ]);
            
            // Link user to payment
            $payment->user_id = $user->id;
            $payment->save();

            \Log::info('Created new hotspot user (fallback)', [
                'user_id' => $user->id,
                'username' => $username,
                'payment_id' => $payment->id
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to handle successful payment user creation', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Generate a unique account number.
     */
    private function generateAccountNumber(): string
    {
        do {
            $accountNumber = 'NU' . mt_rand(1000000000, 9999999999);
        } while (NetworkUser::where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }

    /**
     * Normalize phone number to 254xxxxxxxx format.
     */
    private function formatPhoneNumber(string $phone): ?string
    {
        if (preg_match('/^(07|01)\d{8}$/', $phone)) {
            return '254' . substr($phone, 1);
        }
        if (preg_match('/^254(7|1)\d{8}$/', $phone)) {
            return $phone;
        }
        return null;
    }

    private function findTenantPackage(int $id): TenantHotspot
    {
        // Bypass tenant scope as callback might come to a different domain
        // or tenant resolution might not work as expected in this context
        return TenantHotspot::withoutGlobalScopes()
            ->where('id', $id)
            ->firstOrFail();
    }

    private function calculateExpiry(TenantHotspot $package)
    {
        return match ($package->duration_unit) {
            'minutes' => now()->addMinutes($package->duration_value),
            'hours'   => now()->addHours($package->duration_value),
            'days'    => now()->addDays($package->duration_value),
            'weeks'   => now()->addWeeks($package->duration_value),
            'months'  => now()->addMonths($package->duration_value),
            default   => now()->addDays(1),
        };
    }
}
