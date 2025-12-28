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
            $tenantData['name'] = $settings->business_name ?: $tenantData['id']; // Fallback to ID if no name
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

            $api_ref = 'HS-' . uniqid();

            // Send STK Push request to IntaSend
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('INTASEND_SECRET_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://payment.intasend.com/api/v1/payment/mpesa-stk-push/', [
                'amount' => $amount,
                'phone_number' => $phone,
                'currency' => 'KES',
                'api_ref' => $api_ref,
                'email' => $request->email ?? 'customer@example.com',
            ]);

            $responseData = $response->json();
            \Log::info('IntaSend API response', [
                'status' => $response->status(),
                'body' => $responseData,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to initiate payment: ' . ($responseData['message'] ?? 'Unknown error'),
                ]);
            }

            // Save payment record
            $payment = TenantPayment::create([
                'phone' => $phone,
                'package_id' => $package->id,
                'amount' => $amount,
                'receipt_number' => $api_ref,
                'status' => 'pending',
                'checked' => false,
                'disbursement_type' => 'pending',
                'intasend_reference' => $responseData['id'] ?? $responseData['invoice'] ?? null,
                'intasend_checkout_id' => $responseData['checkout_id'] ?? null,
                'response' => $responseData,
                'created_by' => auth()->id(),
            ]);

            // Queue job to check payment status
            CheckIntaSendPaymentStatusJob::dispatch($payment)->delay(now()->addSeconds(30));

            return response()->json([
                'success' => true,
                'message' => 'STK Push sent. Complete payment on your phone.',
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
     * Handle IntaSend callback.
     */
    public function callback(Request $request)
    {
        try {
            \Log::info('IntaSend callback received', [
                'request_data' => $request->all(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            $request->validate([
                'phone' => 'required|string',
                'package_id' => 'required|exists:tenant_hotspot_packages,id',
            ]);

            \Log::info('Callback validation passed', [
                'phone' => $request->phone,
                'package_id' => $request->package_id
            ]);

            $payment = TenantPayment::where('phone', $request->phone)
                ->where('package_id', $request->package_id)
                ->orderByDesc('id')
                ->first();

            if (!$payment) {
                \Log::warning('No payment found for callback', [
                    'phone' => $request->phone,
                    'package_id' => $request->package_id
                ]);
                return response()->json(['success' => false, 'message' => 'No payment found.']);
            }

            \Log::info('Payment record found', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'created_at' => $payment->created_at
            ]);

            if ($payment->status === 'paid') {
                \Log::info('Payment already processed, checking for existing user', [
                    'payment_id' => $payment->id
                ]);

                // Already paid, return existing user if any
                $existingUser = NetworkUser::where('phone', $request->phone)
                    ->where('type', 'hotspot')
                    ->first();

                if ($existingUser) {
                    \Log::info('Existing user found, returning credentials', [
                        'user_id' => $existingUser->id,
                        'username' => $existingUser->username,
                        'expires_at' => $existingUser->expires_at
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment already processed',
                        'user' => [
                            'username' => $existingUser->username,
                            'password' => 'Password already set',
                            'expires_at' => $existingUser->expires_at->toDateTimeString(),
                        ]
                    ]);
                }

                \Log::info('Payment paid but no user exists, creating user', [
                    'payment_id' => $payment->id
                ]);

                // Create user if payment is paid but no user exists
                $package = $this->findTenantPackage($request->package_id);
                return $this->handleSuccessfulPayment($payment, $package);
            }

            \Log::info('Checking payment status with IntaSend', [
                'payment_id' => $payment->id,
                'intasend_reference' => $payment->intasend_reference
            ]);

            // Check payment status with IntaSend
            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.intasend.secret_key'),
                'Content-Type' => 'application/json',
            ])->get(config('services.intasend.base_url') . '/mpesa/transaction-status/', [
                        'invoice' => $payment->intasend_reference,
                    ]);

            $statusData = $statusResponse->json();

            \Log::info('IntaSend status check response', [
                'payment_id' => $payment->id,
                'status_code' => $statusResponse->status(),
                'response_body' => $statusData
            ]);

            if ($statusResponse->successful() && isset($statusData['status']) && $statusData['status'] === 'PAID') {
                \Log::info('Payment confirmed as PAID, updating payment and creating user', [
                    'payment_id' => $payment->id,
                    'intasend_status' => $statusData['status'],
                    'amount' => $statusData['amount'] ?? 'unknown'
                ]);

                $payment->status = 'paid';
                $payment->response = array_merge($payment->response ?? [], $statusData);
                $payment->paid_at = now();
                $payment->save();

                $package = $this->findTenantPackage($request->package_id);
                return $this->handleSuccessfulPayment($payment, $package);
            }

            \Log::info('Payment not yet confirmed', [
                'payment_id' => $payment->id,
                'intasend_status' => $statusData['status'] ?? 'unknown',
                'response' => $statusData
            ]);

            return response()->json(['success' => false, 'message' => 'Payment not confirmed yet.']);
        } catch (\Exception $e) {
            \Log::error('IntaSend callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json(['success' => false, 'message' => 'Payment status error. ' . $e->getMessage()]);
        }
    }
    /**
     * Handle post-payment success actions.
     */
    private function handleSuccessfulPayment(TenantPayment $payment,  TenantHotspot $package)
    {
        try {
            // Check if user already exists for this phone number
            $existingUser = NetworkUser::where('phone', $payment->phone)
                ->where('type', 'hotspot')
                ->first();

            if ($existingUser) {
                // Update existing user's package and expiry
                $existingUser->package_id = $package->id;
                $existingUser->expires_at = $this->calculateExpiry($package);
                $existingUser->save();

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
            $username = 'HS' . strtoupper(Str::random(6));
            $plainPassword = Str::random(8);

            // Create new network user
            $user = NetworkUser::create([
                'account_number' => $this->generateAccountNumber(),
                'username' => $username,
                'password' => bcrypt($plainPassword),
                'phone' => $payment->phone,
                'type' => 'hotspot',
                'package_id' => $package->id,
                'expires_at' => $this->calculateExpiry($package),
                'registered_at' => now(),
            ]);

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
                    'expires_at' => $user->expires_at->toDateTimeString(),
                    'package_name' => $package->name,
                    'duration_days' => $package->duration_value . ' ' . $package->duration_unit,
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
        $host = request()->getHost();
        $subdomain = explode('.', $host)[0];

        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

        return TenantHotspot::where('id', $id)
            ->where('tenant_id', $tenant->id)
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
