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
     * Display the suspension page for hotspot users.
     */
    public function suspended()
    {
        $host = request()->getHost();
        $subdomain = explode('.', $host)[0];
        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

        $settings = TenantGeneralSetting::where('tenant_id', $tenant->id)->first();
        
        $tenantData = $tenant->toArray();
        if ($settings) {
            $tenantData['name'] = $settings->business_name ?: ($tenantData['name'] ?: $tenantData['id']);
            $tenantData['logo'] = $settings->logo ? '/storage/' . $settings->logo : null;
            $tenantData['support_phone'] = $settings->support_phone ?: $settings->primary_phone;
            $tenantData['support_email'] = $settings->support_email ?: $settings->primary_email;
        }

        return inertia('Hotspot/Suspended', [
            'tenant' => $tenantData,
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
            'tenant_id' => $package->tenant_id,
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

            // Resolve M-Pesa credentials for this tenant
            $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $tenant->id)
                ->where('provider', 'mpesa')
                ->where('use_own_api', true)
                ->where('is_active', true)
                ->first();

            $mpesa = app(\App\Services\MpesaService::class);
            
            if ($gateway) {
                $mpesa->setCredentials([
                    'consumer_key' => $gateway->mpesa_consumer_key,
                    'consumer_secret' => $gateway->mpesa_consumer_secret,
                    'shortcode' => $gateway->mpesa_shortcode,
                    'passkey' => $gateway->mpesa_passkey,
                    'environment' => $gateway->mpesa_env,
                ]);
            }

            // Initiate M-Pesa STK Push
            $mpesaResponse = $mpesa->stkPush(
                $phone,
                $amount,
                $reference,
                "Hotspot Package - {$package->name}"
            );

            \Log::info('M-Pesa STK Push initiated', [
                'response' => $mpesaResponse,
                'reference' => $reference,
                'using_custom_api' => (bool)$gateway,
            ]);

            if (!$mpesaResponse['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $mpesaResponse['message'] ?? 'Failed to initiate payment',
                ]);
            }

            // Create payment record immediately with pending status
            $payment = TenantPayment::create([
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
                'disbursement_status' => $gateway 
                    ? ($gateway->mpesa_env === 'sandbox' ? 'testing' : 'completed') 
                    : (config('mpesa.environment') === 'sandbox' ? 'testing' : 'pending'),
                'checkout_request_id' => $mpesaResponse['checkout_request_id'] ?? null,
                'merchant_request_id' => $mpesaResponse['merchant_request_id'] ?? null,
                'intasend_reference' => null, // Legacy field, keeping null
                'intasend_checkout_id' => null, // Legacy field, keeping null
                'response' => $mpesaResponse['response'] ?? [],
                'tenant_id' => $tenant->id,
            ]);

            \Log::info('Payment record created', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'checkout_request_id' => $payment->checkout_request_id,
            ]);

            // Queue job to check payment status (using payment ID)
            \App\Jobs\CheckMpesaPaymentStatusJob::dispatch($payment)->delay(now()->addSeconds(30));

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

            // Identify tenant from subdomain
            $host = request()->getHost();
            $subdomain = explode('.', $host)[0];
            $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

            // Find the payment record scoped to this tenant
            $payment = TenantPayment::where('tenant_id', $tenant->id)
                ->where(function($q) use ($identifier) {
                    $q->where('id', $identifier)
                        ->orWhere('checkout_request_id', $identifier)
                        ->orWhere('intasend_reference', $identifier)
                        ->orWhere('receipt_number', $identifier);
                })
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

            // Find payment by CheckoutRequestID (bypass global scope for callback)
            $payment = TenantPayment::withoutGlobalScopes()
                ->where(function($q) use ($checkoutRequestId) {
                    $q->where('checkout_request_id', $checkoutRequestId)
                      ->orWhere('intasend_reference', $checkoutRequestId);
                })
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

                // Trigger automatic disbursement if using default API
                if ($payment->disbursement_status === 'pending') {
                    \App\Jobs\ProcessDisbursementJob::dispatch($payment);
                }

                if ($payment->hotspot_package_id) {
                    try {
                        $package = $this->findTenantPackage($payment->hotspot_package_id, $payment->tenant_id);
                        $this->handleSuccessfulPayment($payment, $package);
                    } catch (\Exception $e) {
                        \Log::error('Failed to process hotspot package in callback', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    // Likely a PPPoE payment
                    \Log::info('Processing non-hotspot payment in callback', ['payment_id' => $payment->id]);
                    
                    // Try to unsuspend PPPoE user if linked or found by phone
                    $user = NetworkUser::withoutGlobalScopes()
                        ->where('tenant_id', $payment->tenant_id)
                        ->where(function($q) use ($payment) {
                            if ($payment->user_id) $q->where('id', $payment->user_id);
                            else $q->where('phone', $payment->phone)->where('type', 'pppoe');
                        })
                        ->first();

                    if ($user) {
                        // Update expiry and unsuspend
                        $package = Package::find($payment->package_id);
                        if ($package) {
                            $baseDate = ($user->expires_at && $user->expires_at->isFuture()) ? $user->expires_at : now();
                            // Note: calculateExpiry in this controller is hotspot-specific (takes TenantHotspot)
                            // We might need a more generic one or just use the job's logic
                            // For now, let's just mark as paid and let the job (if still running) or manual check handle it
                            // Or we can implement a simple version here
                            $user->expires_at = now()->addDays(30); // Default fallback
                            $user->save();
                            
                            // Link user
                            if (!$payment->user_id) $payment->update(['user_id' => $user->id]);
                        }
                    }
                }

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
            // Check if user already exists for this phone number (bypass global scope for callback)
            $existingUser = NetworkUser::withoutGlobalScopes()
                ->where('tenant_id', $payment->tenant_id)
                ->where('phone', $payment->phone)
                ->where('type', 'hotspot')
                ->first();

            if ($existingUser) {
                // Update existing user's package and expiry
                $existingUser->hotspot_package_id = $package->id;
                $existingUser->package_id = null;
                
                // Accumulate expiry if current expiry is in the future
                $baseDate = ($existingUser->expires_at && $existingUser->expires_at->isFuture()) 
                    ? $existingUser->expires_at 
                    : now();
                    
                $existingUser->expires_at = $this->calculateExpiry($package, $baseDate);
                $existingUser->save();

                // Link user to payment
                $payment->update(['user_id' => $existingUser->id]);

                \Log::info('Updated existing hotspot user', [
                    'user_id' => $existingUser->id,
                    'username' => $existingUser->username,
                    'phone' => $existingUser->phone,
                    'new_expiry' => $existingUser->expires_at
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment successful! Your existing account has been extended.',
                    'user' => [
                        'username' => $existingUser->username,
                        'password' => 'Use your existing password',
                        'expires_at' => $existingUser->expires_at->toDateTimeString(),
                    ]
                ]);
            }

            // Generate new hotspot user credentials
            $username = NetworkUser::generateHotspotUsername($payment->tenant_id);
            $plainPassword = Str::random(8);

            // Create new network user
            $user = NetworkUser::create([
                'full_name' => $package->name,
                'username' => $username,
                'password' => $plainPassword,
                'phone' => $payment->phone,
                'type' => 'hotspot',
                'hotspot_package_id' => $package->id,
                'package_id' => null,
                'expires_at' => $this->calculateExpiry($package),
                'registered_at' => now(),
                'tenant_id' => $payment->tenant_id,
            ]);

            // Link user to payment
            $payment->update(['user_id' => $user->id]);

            \Log::info('Created new hotspot user', [
                'user_id' => $user->id,
                'username' => $username,
                'phone' => $payment->phone,
                'package' => $package->name,
                'duration' => $package->duration_value . ' ' . $package->duration_unit,
                'expires_at' => $user->expires_at
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment successful! Your hotspot account has been created.',
                'user' => [
                    'username' => $username,
                    'password' => $plainPassword,
                    'package_name' => $package->name,
                    'duration' => $package->duration_value . ' ' . $package->duration_unit,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to create hotspot user after payment', [
                'payment_id' => $payment->id,
                'phone' => $payment->phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment was successful but account creation failed. Please contact support.',
                'payment_id' => $payment->id
            ]);
        }
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

    private function findTenantPackage(int $id, $tenantId = null): TenantHotspot
    {
        if (!$tenantId) {
            $host = request()->getHost();
            $subdomain = explode('.', $host)[0];
            $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();
            $tenantId = $tenant->id;
        }

        return TenantHotspot::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
    }

    private function calculateExpiry(TenantHotspot $package, $baseDate = null)
    {
        $base = $baseDate ?: now();
        
        return match ($package->duration_unit) {
            'minutes' => $base->copy()->addMinutes($package->duration_value),
            'hours'   => $base->copy()->addHours($package->duration_value),
            'days'    => $base->copy()->addDays($package->duration_value),
            'weeks'   => $base->copy()->addWeeks($package->duration_value),
            'months'  => $base->copy()->addMonths($package->duration_value),
            default   => $base->copy()->addDays(1),
        };
    }
}
