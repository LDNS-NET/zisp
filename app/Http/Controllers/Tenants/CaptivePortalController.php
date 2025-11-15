<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\Tenants\NetworkUser;
use App\Models\Voucher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Tenants\TenantPayment as Payment;
use Illuminate\Support\Facades\Http;
use IntaSend\IntaSendPHP\Collection;
use App\Models\TenantSetting;
use App\Services\TenantPayoutService;
use Inertia\Inertia;
use App\Services\AfricaTalkingService;
use Illuminate\Support\Facades\Log;

class CaptivePortalController extends Controller
{
    protected TenantPayoutService $payoutService;

    public function __construct(TenantPayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
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
    public function packages()
    {
        $packages = Package::query()->where('type', 'hotspot')->get();
        return response()->json(['packages' => $packages]);
    }

    // POST /hotspot/login
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
        $user = NetworkUser::where('username', $request->username)
            ->where('type', 'hotspot')
            ->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        if ($user->expires_at && now()->greaterThan($user->expires_at)) {
            return response()->json(['success' => false, 'message' => 'Account expired'], 403);
        }
        return response()->json(['success' => true, 'user' => $user]);
    }

    // POST /hotspot/voucher
    /*public function voucher(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required|string',
        ]);

        $voucher = Voucher::where('code', $request->voucher_code)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired voucher'], 404);
        }

        $username = 'HS' . strtoupper(Str::random(6));
        $password = Str::random(8);

        $user = NetworkUser::create([
            'account_number' => $this->generateAccountNumber(),
            'username' => $username,
            'password' => bcrypt($password),
            'type' => 'hotspot',
            'package_id' => $voucher->package_id,
            'expires_at' => $voucher->expires_at,
            'registered_at' => now(),
        ]);

        $voucher->update([
            'status' => 'used',
            'used_by' => $user->id,
            'used_at' => now(),
        ]);

        return response()->json(['success' => true, 'user' => $user, 'plain_password' => $password]);
    }*/



    public function voucher(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required|string',
        ]);

        // Find voucher and ensure it's active + not expired
        $voucher = Voucher::where('code', $request->voucher_code)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired voucher'
            ], 404);
        }

        // Generate random hotspot user
        $username = 'HS' . strtoupper(Str::random(6));
        $plainPassword = Str::random(8);

        $networkUser = NetworkUser::create([
            'account_number' => $this->generateAccountNumber(),
            'username' => $username,
            'password' => bcrypt($plainPassword),
            'type' => 'hotspot',
            'package_id' => $voucher->package_id,
            'expires_at' => $voucher->expires_at,   // expiry comes from voucher
            'registered_at' => now(),
        ]);

        // Mark voucher as used
        $voucher->update([
            'status' => 'used',
            'used_by' => $networkUser->id,
            'used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'username' => $username,
                'password' => $plainPassword,
            ],
            'message' => 'Voucher accepted and account created',
        ]);
    }





    /*public function show()
    {
        $tenant = tenant();
        $businessName = $tenant?->getAttribute('business_name') ?? 'Hotspot';

        $packages = Package::where('type', 'hotspot')->get();

        return Inertia::render('Tenants/CaptivePortal/Index', [
            'businessName' => $businessName,
            'packages' => $packages,
        ]);
    }*/

    public function show()
    {
        $packages = Package::where('type', 'hotspot')->get();
        $tenant = tenant();
        return Inertia::render('CaptivePortal/Index', [
            'packages' => $packages,
            'business' => [
                'name' => $tenant->business_name,
                'phone' => $tenant->phone,
            ],
        ]);
    }



    // POST /hotspot/pay
    public function pay(Request $request)
    {
        try {
            $request->validate([
                'package_id' => 'required|exists:packages,id',
                'phone' => 'required|string',
            ]);
            $package = Package::findOrFail($request->package_id);
            $amount = $package->price;
            $phone = $request->phone;

            // Log the incoming request
            Log::info('STK Push initiation request', [
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
                Log::error('Invalid phone format for STK Push', ['phone' => $phone]);
                return response()->json(['success' => false, 'message' => 'Invalid phone number format. Use 07xxxxxxxx, 01xxxxxxxx, or 2547xxxxxxxx/2541xxxxxxxx.']);
            }

            $token = env('INTASEND_SECRET_KEY');
            $publishable_key = env('INTASEND_PUBLIC_KEY');
            $test = env('APP_ENV') !== 'production';

            // Generate api_ref — no tenant wallet required for direct collection
            $api_ref = 'HS-' . uniqid();
            $tenantId = tenant('id') ?? (request()->user() ? request()->user()->tenant_id : null);
            Log::info('Initiating STK push without tenant wallet requirement', ['tenant_id' => $tenantId]);

            // Use the latest IntaSend SDK mpesa_stk_push method
            $collection = new Collection();
            $collection->init([
                'token' => $token,
                'publishable_key' => $publishable_key,
                'test' => $test,
            ]);

            $email = 'customer@example.com';
            $narrative = 'Hotspot Package Purchase';

            // Use mpesa_stk_push helper (no wallet id) which matches vendor SDK
            $response = $collection->mpesa_stk_push($amount, $phone, $api_ref, $email);

            Log::info('IntaSend SDK mpesa_stk_push response', ['response' => json_decode(json_encode($response), true)]);

            if (empty($response->invoice)) {
                Log::error('IntaSend SDK error', ['response' => $response]);
                return response()->json(['success' => false, 'message' => 'Failed to initiate payment.']);
            }

            // Sanitize SDK response to array
            $respArray = json_decode(json_encode($response), true);
            $intasend_reference = null;
            if (isset($respArray['invoice'])) {
                if (is_array($respArray['invoice'])) {
                    $intasend_reference = $respArray['invoice']['invoice_id'] ?? ($respArray['invoice']['id'] ?? json_encode($respArray['invoice']));
                } else {
                    $intasend_reference = $respArray['invoice'];
                }
            }
            $intasend_checkout_id = $respArray['checkout_id'] ?? ($respArray['invoice']['checkout_id'] ?? null);

            // Attempt to persist payment but do not fail the STK flow if DB errors occur
            $paymentId = null;
            try {
                $payment = Payment::create([
                    'phone' => $phone,
                    'package_id' => $package->id,
                    'amount' => $amount,
                    'status' => 'pending',
                    'intasend_reference' => is_array($intasend_reference) ? json_encode($intasend_reference) : $intasend_reference,
                    'intasend_checkout_id' => is_array($intasend_checkout_id) ? json_encode($intasend_checkout_id) : $intasend_checkout_id,
                    'response' => $respArray,
                ]);
                $paymentId = $payment->id;
            } catch (\Exception $e) {
                Log::error('Failed to save TenantPayment after STK push', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'response' => $respArray]);
                // continue — STK push was initiated successfully, return success to frontend
            }

            if ($paymentId) {
                // Queue a job to check the payment status after a short delay
                \App\Jobs\CheckIntaSendPaymentStatus::dispatch($paymentId)->delay(now()->addSeconds(15));
            } else {
                // If we couldn't persist the payment record, but we have an intasend_reference, try to find the record later
                if (!empty($intasend_reference)) {
                    // Dispatch a job with the invoice identifier so the status checker can operate without a DB id
                    \App\Jobs\CheckIntaSendPaymentStatus::dispatch($intasend_reference)->delay(now()->addSeconds(15));
                }
            }

            return response()->json(['success' => true, 'message' => 'STK Push sent. Complete payment on your phone.', 'payment_id' => $paymentId]);
        } catch (\Exception $e) {
            Log::error('IntaSend SDK exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Payment error. ' . $e->getMessage()]);
        }
    }

    // POST /hotspot/payment/callback
    public function paymentCallback(Request $request, AfricaTalkingService $smsService)
    {
        try {
            // Log the raw callback for debugging
            Log::info('IntaSend payment callback received', ['payload' => $request->all(), 'headers' => $request->headers->all()]);

            // Optional HMAC signature verification (set INTASEND_WEBHOOK_SECRET in env)
            $webhookSecret = env('INTASEND_WEBHOOK_SECRET');
            $signature = $request->header('X-IntaSend-Signature') ?? $request->header('X-Intasend-Signature');
            if ($webhookSecret && $signature) {
                $computed = hash_hmac('sha256', $request->getContent(), $webhookSecret);
                if (!hash_equals($computed, $signature)) {
                    Log::warning('Invalid IntaSend webhook signature', ['computed' => $computed, 'signature' => $signature]);
                    return response()->json(['success' => false, 'message' => 'Invalid signature.'], 403);
                }
            }

            $payload = $request->all();

            // Try to locate payment by IntaSend identifiers first
            $payment = null;
            if (!empty($payload['invoice'])) {
                $payment = Payment::where('intasend_reference', $payload['invoice'])->first();
            }
            if (!$payment && !empty($payload['checkout_id'])) {
                $payment = Payment::where('intasend_checkout_id', $payload['checkout_id'])->first();
            }

            // Fallback to phone + package_id (legacy behaviour)
            if (!$payment && $request->has('phone') && $request->has('package_id')) {
                $payment = Payment::where('phone', $request->phone)
                    ->where('package_id', $request->package_id)
                    ->orderByDesc('id')
                    ->first();
            }

            if (!$payment) {
                Log::warning('Payment callback received but no matching payment found', ['payload' => $payload]);
                return response()->json(['success' => false, 'message' => 'No payment found.'], 404);
            }

            // Normalize status from payload
            $status = $payload['status'] ?? ($payload['data']['status'] ?? null);
            $transactionId = $payload['transaction_id'] ?? ($payload['data']['transaction_id'] ?? ($payload['data']['mpesa_receipt_number'] ?? null));

            // Update payment record with full payload and any identifiers
            if (!empty($payload['invoice'])) $payment->intasend_reference = $payload['invoice'];
            if (!empty($payload['checkout_id'])) $payment->intasend_checkout_id = $payload['checkout_id'];
            if ($transactionId) $payment->transaction_id = $transactionId;
            $payment->response = $payload;

            if ($status && in_array(strtoupper($status), ['PAID', 'SUCCESS', 'COMPLETED'])) {
                $payment->status = 'paid';
                $payment->paid_at = $payment->paid_at ?? now();
                $payment->save();

                $package = Package::find($payment->package_id);
                return $this->_handleSuccessfulPayment($payment, $package, $smsService);
            }

            // If not paid, persist response and return
            $payment->status = $payment->status ?? ($status ? strtolower($status) : $payment->status);
            $payment->save();
            return response()->json(['success' => true, 'message' => 'Callback processed.']);
        } catch (\Exception $e) {
            Log::error('IntaSend callback exception', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Payment status error. ' . $e->getMessage()]);
        }
    }

    /**
     * Handle all post-payment success actions.
     */
    private function _handleSuccessfulPayment(Payment $payment, Package $package, AfricaTalkingService $smsService)
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

        // --- Send Payment Confirmation SMS ---
        $notificationSettings = TenantSetting::where('category', 'notifications')->first()->settings ?? [];
        if (!empty($notificationSettings['payment_confirmation']['enabled'])) {
            $messageTemplate = $notificationSettings['payment_confirmation']['message'] ?? 'Thank you for your payment.';
            $placeholders = [
                '{name}' => $user->full_name ?? $user->username,
                '{package_name}' => $package->name,
                '{amount}' => $payment->amount,
                '{transaction_id}' => $payment->transaction_id,
            ];
            $message = str_replace(array_keys($placeholders), array_values($placeholders), $messageTemplate);
            if ($user->phone) {
                try {
                    $smsService->sendSMS([$user->phone], $message);
                    Log::info('Sent payment confirmation SMS', ['user_id' => $user->id, 'phone' => $user->phone]);
                } catch (\Exception $e) {
                    Log::error('Failed to send payment confirmation SMS', ['error' => $e->getMessage()]);
                }
            }
        }

        // --- Payout Logic ---
        $this->disburseToTenant($payment);

        return response()->json(['success' => true, 'user' => $user, 'plain_password' => $password]);
    }

    /**
     * Disburse 99% of payment to tenant's payout method using IntaSend Payout API
     */
    protected function disburseToTenant($payment)
    {
        $tenantId = tenant('id') ?? (request()->user() ? request()->user()->tenant_id : null);
        if (!$tenantId) return;
        $meta = ['payment_id' => $payment->id];
        app(\App\Services\TenantPayoutService::class)->disburse($payment->amount, $tenantId, $meta);
    }

    public function tenant()
    {
        $t = tenant();
        if (!$t) {
            return response()->json(['business_name' => 'Hotspot', 'phone' => '']);
        }
        return response()->json(['business_name' => $t->business_name, 'phone' => $t->phone]);
    }

    public function paymentStatus($identifier)
    {
        // Try numeric id first
        $payment = null;
        if (ctype_digit((string)$identifier)) {
            $payment = Payment::find((int)$identifier);
        }
        if (!$payment) {
            $payment = Payment::where('intasend_reference', $identifier)
                ->orWhere('intasend_checkout_id', $identifier)
                ->first();
        }

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        return response()->json(['success' => true, 'status' => $payment->status, 'payment' => $payment]);
    }
}
