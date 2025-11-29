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
use IntaSend\IntaSendPHP\Collection;
use Illuminate\Support\Facades\Log;

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

            $collection = new Collection();
            $collection->init($credentials);

            $api_ref = 'HS-' . uniqid();
            $tenantId = tenant('id') ?? (request()->user() ? request()->user()->tenant_id : null);
            $tenant = \App\Models\Tenant::find($tenantId);
            if (!$tenant || !$tenant->wallet_id) {
                \Log::error('No wallet_id found for tenant', ['tenant_id' => $tenantId]);
                return response()->json(['success' => false, 'message' => 'No wallet ID configured for this tenant. Please contact support.']);
            }
            $walletId = $tenant->wallet_id;
            \Log::info('Using IntaSend wallet_id', ['wallet_id' => $walletId, 'tenant_id' => $tenantId]);

            $response = $collection->create(
                $amount,
                $phone,
                'KES',
                'MPESA_STK_PUSH',
                $api_ref,
                '', // name (optional)
                $request->email ?? 'customer@example.com', // email (optional)
                [
                    'wallet_id' => $walletId,
                ]
            );

            \Log::info('IntaSend SDK response', ['response' => json_decode(json_encode($response), true)]);

            if (empty($response->invoice)) {
                \Log::error('IntaSend SDK error', ['response' => $response]);
                return response()->json(['success' => false, 'message' => 'Failed to initiate payment.']);
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
                'intasend_reference' => $response->invoice,
                'intasend_checkout_id' => $response->checkout_id ?? null,
                'response' => json_decode(json_encode($response), true),
            ]);

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
                // Already paid, create user if not already created
                $package = Package::find($request->package_id);
                return $this->handleSuccessfulPayment($payment, $package);
            }
            
            // SSL verification enabled for production and secure local dev
            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.intasend.secret_key'),
                'Content-Type' => 'application/json',
            ])->get(config('services.intasend.base_url') . '/mpesa/transaction-status/', [
                    'invoice' => $payment->intasend_reference,
                ]);
                
            $statusData = $statusResponse->json();
            
            if ($statusResponse->successful() && isset($statusData['status']) && $statusData['status'] === 'PAID') {
                $payment->status = 'paid';
                $payment->response = $statusData;
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
        $username = 'HS' . strtoupper(Str::random(6));
        $password = Str::random(8);

        $user = NetworkUser::create([
            'account_number' => $this->generateAccountNumber(),
            'username' => $username,
            'password' => bcrypt($password),
            'phone' => $payment->phone,
            'type' => 'hotspot',
            'package_id' => $package->id,
            'expires_at' => now()->addDays($package->duration),
            'registered_at' => now(),
        ]);

        return response()->json(['success' => true, 'user' => $user, 'plain_password' => $password]);
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
