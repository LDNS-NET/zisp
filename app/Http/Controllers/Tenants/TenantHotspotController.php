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

        // Get packages belonging to this tenant
        $packages = TenantHotspot::where('tenant_id', $tenant->id)->get();

        // Return to Inertia
        return inertia('Hotspot/Index', [
            'tenant' => $tenant,
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

        $package = TenantHotspot::findOrFail($data['id']);
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
            $request->validate([
                'package_id' => 'required|exists:tenant_hotspot_packages,id',
                'phone' => 'required|string',
                'email' => 'nullable|email',
            ]);

            $package = TenantHotspot::findOrFail($request->package_id);
            $amount = $package->price;
            $phone = $request->phone;

            // Log the incoming request
            \Log::info('STK Push initiation request', [
                'package_id' => $request->package_id,
                'phone' => $phone,
                'amount' => $amount,
            ]);

            // Accept phone in 07xxxxxxxx or 01xxxxxxxx or 2547xxxxxxxx or 2541xxxxxxxx
            if (preg_match('/^(07|01)\d{8}$/', $phone)) {
                // Convert to 2547xxxxxxxx or 2541xxxxxxxx
                $phone = '254' . substr($phone, 1);
            }
            if (!preg_match('/^254(7|1)\d{8}$/', $phone)) {
                \Log::error('Invalid phone format for STK Push', ['phone' => $phone]);
                return response()->json(['success' => false, 'message' => 'Invalid phone number format. Use 07xxxxxxxx, 01xxxxxxxx, or 2547xxxxxxxx/2541xxxxxxxx.']);
            }

            $credentials = [
                'token' => env('INTASEND_SECRET_KEY'),
                'publishable_key' => env('INTASEND_PUBLIC_KEY'),
                'test' => env('APP_ENV') !== 'production',
            ];

            // Mask credentials for logging
            $maskedCreds = $credentials;
            $maskedCreds['token'] = substr($credentials['token'] ?? '', 0, 6) . '...';
            $maskedCreds['publishable_key'] = substr($credentials['publishable_key'] ?? '', 0, 6) . '...';
            \Log::info('Using IntaSend credentials', $maskedCreds);

            $api_ref = 'HS-' . uniqid();

            // Use direct HTTP API call instead of SDK to avoid wallet requirement
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $credentials['token'],
                'Content-Type' => 'application/json',
            ])->post('https://payment.intasend.com/api/v1/payment/mpesa-stk-push/', [
                        'amount' => $amount,
                        'phone_number' => $phone,
                        'currency' => 'KES',
                        'api_ref' => $api_ref,
                        'email' => $request->email ?? 'customer@example.com',
                    ]);

            $responseData = $response->json();
            \Log::info('IntaSend API response', ['response' => $responseData]);

            if (!$response->successful()) {
                \Log::error('IntaSend API error', ['response' => $responseData]);
                return response()->json(['success' => false, 'message' => 'Failed to initiate payment: ' . ($responseData['message'] ?? 'Unknown error')]);
            }

            // Store payment request
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
            ]);

            // Dispatch job to check payment status and create user automatically
            CheckIntaSendPaymentStatusJob::dispatch($payment)->delay(now()->addSeconds(30));

            return response()->json(['success' => true, 'message' => 'STK Push sent. Complete payment on your phone.', 'payment_id' => $payment->id]);
        } catch (\Exception $e) {
            \Log::error('IntaSend SDK exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Payment error. ' . $e->getMessage()]);
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
                $package = TenantHotspot::find($request->package_id);
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

                $package = TenantHotspot::find($request->package_id);
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
                $existingUser->expires_at = now()->addDays($package->duration);
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
                'expires_at' => now()->addDays($package->duration),
                'registered_at' => now(),
            ]);

            \Log::info('Created new hotspot user', [
                'user_id' => $user->id,
                'username' => $username,
                'phone' => $payment->phone,
                'package' => $package->name,
                'duration' => $package->duration,
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
                    'duration_days' => $package->duration,
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
}
