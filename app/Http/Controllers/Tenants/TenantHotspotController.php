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

            // Encode api_ref: HS|package_id|phone|uniqid
            $api_ref = "HS|{$package->id}|{$phone}|" . strtoupper(uniqid());

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
                'callback_url' => "https://{$host}/hotspot/callback", // Explicitly direct callback to this tenant
            ]);

            $responseData = $response->json();
            \Log::info('IntaSend API response', [
                'status' => $response->status(),
                'body' => $responseData,
                'api_ref' => $api_ref,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to initiate payment: ' . ($responseData['message'] ?? 'Unknown error'),
                ]);
            }

            // Create payment record immediately with pending status
            $payment = TenantPayment::create([
                'phone' => $phone,
                'hotspot_package_id' => $package->id,
                'package_id' => null,
                'amount' => $amount,
                'receipt_number' => $api_ref,
                'status' => 'pending',
                'checked' => false,
                'disbursement_type' => 'pending',
                'intasend_reference' => $responseData['invoice']['id'] ?? $responseData['id'] ?? null,
                'intasend_checkout_id' => $responseData['invoice']['checkout_id'] ?? $responseData['checkout_id'] ?? null,
                'response' => $responseData,
            ]);

            \Log::info('Payment record created', [
                'payment_id' => $payment->id,
                'api_ref' => $api_ref,
                'intasend_reference' => $payment->intasend_reference,
            ]);

            // Queue job to check payment status (using payment ID)
            CheckIntaSendPaymentStatusJob::dispatch($payment)->delay(now()->addSeconds(30));

            return response()->json([
                'success' => true,
                'message' => 'STK Push sent. Complete payment on your phone.',
                'payment_id' => $payment->id, // Use actual payment ID for polling
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
     * Check payment status on demand.
     */
    /**
     * Check payment status on demand.
     */
    public function checkPaymentStatus($identifier)
    {
        try {
            // Try to find the payment record
            $payment = TenantPayment::where('id', $identifier)
                ->orWhere('intasend_reference', $identifier)
                ->orWhere('receipt_number', $identifier)
                ->orderByDesc('id')
                ->first();

            if ($payment && $payment->status === 'paid') {
                 $user = NetworkUser::where('phone', $payment->phone)->where('type', 'hotspot')->first();
                 if ($user) {
                     return response()->json([
                        'success' => true,
                         'status' => 'paid',
                         'user' => [
                            'username' => $user->username,
                            'expires_at' => $user->expires_at->toDateTimeString(),
                         ]
                     ]);
                 }
                return response()->json(['success' => true, 'status' => 'paid']);
            }

            // If no payment record or not paid, check IntaSend
            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('INTASEND_SECRET_KEY'),
                'Content-Type' => 'application/json',
            ])->get('https://payment.intasend.com/api/v1/payment/mpesa/transaction-status/', [
                'invoice' => $payment ? $payment->intasend_reference : $identifier,
            ]);

            $statusData = $statusResponse->json();
            
            // Robust status extraction
            $status = $statusData['invoice']['state'] ?? $statusData['status'] ?? $statusData['state'] ?? null;
            $status = $status ? strtoupper($status) : null;

            if ($statusResponse->successful() && ($status === 'PAID' || $status === 'COMPLETE' || $status === 'SUCCESS')) {
                 \Log::info('Payment confirmed via polling', ['identifier' => $identifier, 'status' => $status]);
                
                 if (!$payment) {
                     $apiRef = $statusData['invoice']['api_ref'] ?? $identifier;
                     $payment = $this->createPaymentFromApiRef($apiRef, $statusData);
                 } else {
                     $payment->status = 'paid';
                     $payment->checked = true;
                     $payment->transaction_id = $statusData['invoice']['mpesa_reference'] ?? $statusData['id'] ?? $statusData['transaction_id'] ?? $payment->transaction_id;
                     $payment->response = array_merge($payment->response ?? [], $statusData);
                     $payment->paid_at = now();
                     $payment->save();
                 }

                 if ($payment) {
                     $package = $this->findTenantPackage($payment->hotspot_package_id);
                     return $this->handleSuccessfulPayment($payment, $package);
                 }
            }

            // Handle failure
            if ($statusResponse->successful() && in_array($status, ['FAILED', 'CANCELLED', 'REJECTED'])) {
                if (!$payment) {
                    $apiRef = $statusData['invoice']['api_ref'] ?? $identifier;
                    $payment = $this->createPaymentFromApiRef($apiRef, $statusData, 'failed');
                } else {
                    $payment->status = 'failed';
                    $payment->response = array_merge($payment->response ?? [], $statusData);
                    $payment->save();
                }
            }

            return response()->json([
                'success' => true, 
                'status' => $payment ? $payment->status : 'pending'
            ]);

        } catch (\Exception $e) {
             \Log::error('Check status exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
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
                'timestamp' => now()->toISOString()
            ]);

            $invoiceId = $request->input('invoice_id');
            $apiRef = $request->input('api_ref');
            $state = strtoupper($request->input('state') ?? '');

            if (!$invoiceId && !$apiRef) {
                \Log::warning('Callback missing identifiers', ['data' => $request->all()]);
                return response()->json(['success' => false, 'message' => 'Missing identifiers']);
            }

            $payment = TenantPayment::where('intasend_reference', $invoiceId)
                ->orWhere('receipt_number', $apiRef)
                ->orderByDesc('id')
                ->first();

            if ($payment && $payment->status === 'paid') {
                return response()->json(['success' => true, 'message' => 'Already processed']);
            }

            if ($state === 'COMPLETE' || $state === 'SUCCESS' || $state === 'PAID') {
                \Log::info('Payment confirmed via callback', ['api_ref' => $apiRef, 'state' => $state]);

                if (!$payment) {
                    $payment = $this->createPaymentFromApiRef($apiRef, $request->all());
                } else {
                    $payment->status = 'paid';
                    $payment->checked = true;
                    $payment->transaction_id = $request->input('mpesa_reference') ?? $request->input('id') ?? $payment->transaction_id;
                    $payment->response = array_merge($payment->response ?? [], $request->all());
                    $payment->paid_at = now();
                    $payment->save();
                }

                if ($payment) {
                    $package = $this->findTenantPackage($payment->hotspot_package_id);
                    return $this->handleSuccessfulPayment($payment, $package);
                }
            }

            // Handle failure in callback
            if (in_array($state, ['FAILED', 'CANCELLED', 'REJECTED'])) {
                if (!$payment) {
                    $this->createPaymentFromApiRef($apiRef, $request->all(), 'failed');
                } else {
                    $payment->status = 'failed';
                    $payment->response = array_merge($payment->response ?? [], $request->all());
                    $payment->save();
                }
            }

            \Log::info('Payment not confirmed in callback', ['api_ref' => $apiRef, 'state' => $state]);
            return response()->json(['success' => false, 'message' => 'Payment not confirmed.']);
        } catch (\Exception $e) {
            \Log::error('IntaSend callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Error processing callback']);
        }
    }

    /**
     * Create a payment record from encoded api_ref.
     */
    private function createPaymentFromApiRef($apiRef, $statusData, $status = 'paid')
    {
        // Format: HS|{package_id}|{phone}|{uniqid}
        $parts = explode('|', $apiRef);
        if (count($parts) >= 3 && $parts[0] === 'HS') {
            $packageId = $parts[1];
            $phone = $parts[2];
            
            return TenantPayment::create([
                'phone' => $phone,
                'hotspot_package_id' => $packageId,
                'package_id' => null,
                'amount' => $statusData['invoice']['amount'] ?? $statusData['amount'] ?? 0,
                'receipt_number' => $apiRef,
                'status' => $status,
                'checked' => ($status === 'paid'),
                'paid_at' => ($status === 'paid') ? now() : null,
                'transaction_id' => $statusData['invoice']['mpesa_reference'] ?? $statusData['mpesa_reference'] ?? $statusData['id'] ?? null,
                'intasend_reference' => $statusData['invoice']['id'] ?? $statusData['invoice_id'] ?? $statusData['id'] ?? null,
                'intasend_checkout_id' => $statusData['invoice']['checkout_id'] ?? $statusData['checkout_id'] ?? null,
                'response' => $statusData,
                'created_by' => \App\Models\User::first()?->id,
            ]);
        }
        
        return null;
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
                $existingUser->hotspot_package_id = $package->id;
                $existingUser->package_id = null;
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
                'hotspot_package_id' => $package->id,
                'package_id' => null,
                'expires_at' => $this->calculateExpiry($package),
                'registered_at' => now(),
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
