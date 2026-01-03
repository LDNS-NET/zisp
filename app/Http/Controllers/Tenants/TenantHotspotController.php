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
use App\Services\CountryService;

use App\Models\Tenants\TenantGeneralSetting;
use App\Services\PaymentProcessingService;
use App\Services\PaymentGatewayService;

class TenantHotspotController extends Controller
{
    protected $paymentProcessingService;
    protected $paymentGatewayService;

    public function __construct(PaymentProcessingService $paymentProcessingService, PaymentGatewayService $paymentGatewayService)
    {
        $this->paymentProcessingService = $paymentProcessingService;
        $this->paymentGatewayService = $paymentGatewayService;
    }
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
            $tenantData['logo'] = $settings->logo ? '/storage/' . $settings->logo : null;
            $tenantData['support_phone'] = $settings->support_phone ?: ($settings->primary_phone ?: $tenant->phone);
            $tenantData['support_email'] = $settings->support_email ?: $settings->primary_email;
        } else {
            $tenantData['support_phone'] = $tenant->phone;
        }

        // Get packages belonging to this tenant
        $packages = TenantHotspot::where('tenant_id', $tenant->id)->get();

        // Get configured payment gateways for this tenant
        $gateways = \App\Models\TenantPaymentGateway::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('provider')
            ->toArray();

        // Filter out bank as it's not configured
        $gateways = array_filter($gateways, fn($g) => $g !== 'bank');

        // Return to Inertia
        return inertia('Hotspot/Index', [
            'tenant' => $tenantData,
            'packages' => $packages,
            'country' => $tenant->country_code ?? 'KE',
            'paymentMethods' => array_values($gateways), // Re-index array
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
            $tenantData['support_phone'] = $settings->support_phone ?: ($settings->primary_phone ?: $tenant->phone);
            $tenantData['support_email'] = $settings->support_email ?: $settings->primary_email;
        } else {
            $tenantData['support_phone'] = $tenant->phone;
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
     * Process checkout for hotspot packages using PaymentGatewayService.
     */
    public function checkout(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'hotspot_package_id' => 'required|exists:tenant_hotspot_packages,id',
                'phone' => 'required|string',
                'email' => 'nullable|email',
                'payment_method' => 'nullable|string|in:mpesa,momo,paystack',
            ]);


            // Get current tenant from subdomain
            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];
            $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

            // Find hotspot package
            $package = $this->findTenantPackage($request->hotspot_package_id);
            $amount = $package->price;

            // Determine payment method (default to mpesa for KE, or first available)
            $paymentMethod = $request->payment_method ?? ($tenant->country_code === 'KE' ? 'mpesa' : 'momo');

            // Create a temporary NetworkUser object for PaymentGatewayService
            $tempUser = new NetworkUser();
            $tempUser->tenant_id = $tenant->id;
            $tempUser->phone = $request->phone;
            $tempUser->email = $request->email;
            $tempUser->type = 'hotspot';
            $tempUser->exists = false; // Mark as not persisted

            // Prepare metadata
            $metadata = [
                'hotspot_package_id' => $package->id,
                'type' => 'hotspot',
            ];

            // Use PaymentGatewayService to initiate payment
            $response = $this->paymentGatewayService->initiatePayment(
                $tempUser,
                $amount,
                $request->phone,
                'hotspot',
                $metadata,
                $paymentMethod
            );

            if ($response['success']) {
                \Log::info('Hotspot payment initiated', [
                    'method' => $paymentMethod,
                    'reference' => $response['reference_id'] ?? null,
                    'payment_id' => $response['payment_id'] ?? null,
                ]);

                return response()->json($response);
            }

            return response()->json($response, 400);

        } catch (\Exception $e) {
            \Log::error('Hotspot checkout exception', [
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

                if ($payment->hotspot_package_id || $payment->package_id) {
                    $this->paymentProcessingService->processSuccess($payment);
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
     * Normalize phone number based on country dial code.
     */
    private function formatPhoneNumber(string $phone, string $dialCode = '254'): ?string
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/\D/', '', $phone);

        // If it starts with 0, replace with dial code
        if (substr($phone, 0, 1) === '0') {
            return $dialCode . substr($phone, 1);
        }

        // If it already starts with dial code, return as is
        if (str_starts_with($phone, $dialCode)) {
            return $phone;
        }

        // If it's a short number (e.g. 7xxxxxxxx), add dial code
        if (strlen($phone) === 9) {
            return $dialCode . $phone;
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
}
