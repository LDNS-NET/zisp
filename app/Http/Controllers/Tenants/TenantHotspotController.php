<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\TenantHotspot;
use App\Http\Requests\StoreTenantHotspotRequest;
use App\Http\Requests\UpdateTenantHotspotRequest;
use App\Models\Package;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\NetworkUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Tenants\TenantPaymentController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Jobs\CheckIntaSendPaymentStatusJob;

class TenantHotspotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $packages = Package::where('type', 'hotspot')
            ->orderBy('name')
            ->get();

        return Inertia::render('Hotspot/Index', [
            'packages' => $packages,
        ]);
    }
   
    /**
     * Process hotspot package purchase with STK Push
     */
    public function purchaseSTKPush(Request $request)
    {
        $data = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'phone' => 'required|string|regex:/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/',
        ]);

        $package = Package::findOrFail($data['package_id']);

        // Forward to TenantPaymentController for processing
        $paymentController = new TenantPaymentController();
        $request->merge([
            'phone' => $data['phone'],
            'package_id' => $data['package_id'],
            'amount' => $package->price,
        ]);

        return $paymentController->processSTKPush($request);
    }

    /**
     * Process IntaSend checkout for hotspot packages
     */
    public function checkout(Request $request)
    {
        try {
            $request->validate([
                'package_id' => 'required|exists:packages,id',
                'phone' => 'required|string',
                'email' => 'nullable|email',
            ]);

            $package = Package::findOrFail($request->package_id);
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
     * IntaSend callback handler
     */
    public function callback(Request $request)
    {
        try {
            $request->validate([
                'phone' => 'required|string',
                'package_id' => 'required|exists:packages,id',
            ]);
            
            $payment = TenantPayment::where('phone', $request->phone)
                ->where('package_id', $request->package_id)
                ->orderByDesc('id')
                ->first();
                
            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'No payment found.']);
            }
            
            if ($payment->status === 'paid') {
                // Already paid, return existing user if any
                $existingUser = NetworkUser::where('phone', $request->phone)
                    ->where('type', 'hotspot')
                    ->first();
                    
                if ($existingUser) {
                    return response()->json([
                        'success' => true, 
                        'message' => 'Payment already processed',
                        'user' => [
                            'username' => $existingUser->username,
                            'password' => 'Password already set',
                        ]
                    ]);
                }
                
                // Create user if payment is paid but no user exists
                $package = Package::find($request->package_id);
                return $this->handleSuccessfulPayment($payment, $package);
            }
            
            // Check payment status with IntaSend
            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.intasend.secret_key'),
                'Content-Type' => 'application/json',
            ])->get(config('services.intasend.base_url') . '/mpesa/transaction-status/', [
                    'invoice' => $payment->intasend_reference,
                ]);
                
            $statusData = $statusResponse->json();
            
            if ($statusResponse->successful() && isset($statusData['status']) && $statusData['status'] === 'PAID') {
                $payment->status = 'paid';
                $payment->response = array_merge($payment->response ?? [], $statusData);
                $payment->paid_at = now();
                $payment->save();
                
                $package = Package::find($request->package_id);
                return $this->handleSuccessfulPayment($payment, $package);
            }
            
            return response()->json(['success' => false, 'message' => 'Payment not confirmed yet.']);
        } catch (\Exception $e) {
            \Log::error('IntaSend callback exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Payment status error. ' . $e->getMessage()]);
        }
    }

    /**
     * Handle all post-payment success actions.
     */
    private function handleSuccessfulPayment(TenantPayment $payment, Package $package)
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
     * Generate a system-wide unique account number for NetworkUser.
     */
    private function generateAccountNumber(): string
    {
        do {
            // Example: 10-digit random number prefixed with 'NU'
            $accountNumber = 'NU' . mt_rand(1000000000, 9999999999);
        } while (NetworkUser::where('account_number', $accountNumber)->exists());
        return $accountNumber;
    }
}
