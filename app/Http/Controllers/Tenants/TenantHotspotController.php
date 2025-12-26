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
            'package_id' => 'required|exists:tenant_hotspot_packages,id',
            'phone' => 'required|string|regex:/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/',
        ]);

        $package = TenantHotspot::findOrFail($data['package_id']);

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
        $validated = $request->validate([
            'package_id' => 'required|exists:tenant_hotspot_packages,id',
            'phone' => 'required|string',
            'email' => 'nullable|email',
        ]);

        $package = TenantHotspot::findOrFail($validated['package_id']);
        $phone = $this->formatPhoneNumber($validated['phone']);
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'Invalid phone number format.']);
        }

        $api_ref = 'HS-' . uniqid();
        $credentials = [
            'token' => env('INTASEND_SECRET_KEY'),
            'publishable_key' => env('INTASEND_PUBLIC_KEY'),
        ];

        // Call IntaSend API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credentials['token'],
            'Content-Type' => 'application/json',
        ])->post('https://payment.intasend.com/api/v1/payment/mpesa-stk-push/', [
            'amount' => $package->price,
            'phone_number' => $phone,
            'currency' => 'KES',
            'api_ref' => $api_ref,
            'email' => $validated['email'] ?? 'customer@example.com',
        ]);

        $responseData = $response->json();

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . ($responseData['message'] ?? 'Unknown error'),
            ]);
        }

        // Store payment
        $payment = TenantPayment::create([
            'phone' => $phone,
            'package_id' => $package->id,
            'amount' => $package->price,
            'receipt_number' => $api_ref,
            'status' => 'pending',
            'checked' => false,
            'disbursement_type' => 'pending',
            'intasend_reference' => $responseData['id'] ?? $responseData['invoice'] ?? null,
            'intasend_checkout_id' => $responseData['checkout_id'] ?? null,
            'response' => $responseData,
        ]);

        CheckIntaSendPaymentStatusJob::dispatch($payment)->delay(now()->addSeconds(30));

        return response()->json(['success' => true, 'message' => 'STK Push sent. Complete payment on your phone.', 'payment_id' => $payment->id]);
    }

    /**
     * Handle IntaSend callback.
     */
    public function callback(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'package_id' => 'required|exists:tenant_hotspot_packages,id',
        ]);

        $payment = TenantPayment::where('phone', $request->phone)
            ->where('package_id', $request->package_id)
            ->latest('id')
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'No payment found.']);
        }

        if ($payment->status === 'paid') {
            $existingUser = NetworkUser::where('phone', $request->phone)->where('type', 'hotspot')->first();
            if ($existingUser) {
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

            $package = TenantHotspot::find($request->package_id);
            return $this->handleSuccessfulPayment($payment, $package);
        }

        // Check IntaSend status
        $statusResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.intasend.secret_key'),
            'Content-Type' => 'application/json',
        ])->get(config('services.intasend.base_url') . '/mpesa/transaction-status/', [
            'invoice' => $payment->intasend_reference,
        ]);

        $statusData = $statusResponse->json();

        if ($statusResponse->successful() && isset($statusData['status']) && $statusData['status'] === 'PAID') {
            $payment->update([
                'status' => 'paid',
                'response' => array_merge($payment->response ?? [], $statusData),
                'paid_at' => now(),
            ]);

            $package = TenantHotspot::find($request->package_id);
            return $this->handleSuccessfulPayment($payment, $package);
        }

        return response()->json(['success' => false, 'message' => 'Payment not confirmed yet.']);
    }

    /**
     * Handle post-payment success actions.
     */
    private function handleSuccessfulPayment(TenantPayment $payment, TenantHotspot $package)
    {
        $existingUser = NetworkUser::where('phone', $payment->phone)->where('type', 'hotspot')->first();

        if ($existingUser) {
            $existingUser->update([
                'package_id' => $package->id,
                'expires_at' => now()->addDays($package->duration_value),
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

        $username = 'HS' . strtoupper(Str::random(6));
        $plainPassword = Str::random(8);

        $user = NetworkUser::create([
            'account_number' => $this->generateAccountNumber(),
            'username' => $username,
            'password' => bcrypt($plainPassword),
            'phone' => $payment->phone,
            'type' => 'hotspot',
            'package_id' => $package->id,
            'expires_at' => now()->addDays($package->duration_value),
            'registered_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment successful! Your hotspot account has been created.',
            'user' => [
                'username' => $username,
                'password' => $plainPassword,
                'expires_at' => $user->expires_at->toDateTimeString(),
                'package_name' => $package->name,
                'duration_days' => $package->duration_value,
            ]
        ]);
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
