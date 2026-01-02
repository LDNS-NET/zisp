<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Tenants\TenantHotspot;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\NetworkUser;
use App\Services\MomoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\CountryService;

class MomoController extends Controller
{
    protected $momoService;

    public function __construct(MomoService $momoService)
    {
        $this->momoService = $momoService;
    }

    /**
     * Initialize MoMo payment for hotspot.
     */
    public function checkout(Request $request)
    {
        try {
            $request->validate([
                'hotspot_package_id' => 'required|exists:tenant_hotspot_packages,id',
                'phone' => 'required|string',
            ]);

            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];
            $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

            $package = TenantHotspot::where('id', $request->hotspot_package_id)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            // Resolve MoMo credentials for this tenant
            $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $tenant->id)
                ->where('provider', 'momo')
                ->where('is_active', true)
                ->first();

            if (!$gateway || !$gateway->momo_api_user || !$gateway->momo_api_key || !$gateway->momo_subscription_key) {
                return response()->json([
                    'success' => false,
                    'message' => 'MTN MoMo is not configured for this provider.'
                ], 400);
            }

            $this->momoService->setCredentials([
                'api_user' => $gateway->momo_api_user,
                'api_key' => $gateway->momo_api_key,
                'subscription_key' => $gateway->momo_subscription_key,
                'environment' => $gateway->momo_env,
            ]);

            // Resolve country data
            $countryData = CountryService::getCountryData($tenant->country_code);
            $currency = $countryData['currency'] ?? 'UGX';
            $dialCode = $countryData['dial_code'] ?? '256';

            // Normalize phone
            $phone = $this->formatPhoneNumber($request->phone, $dialCode);
            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid phone number format for {$countryData['name']}."
                ], 400);
            }

            $reference = "HS|MOMO|{$package->id}|{$request->phone}|" . strtoupper(uniqid());

            $response = $this->momoService->requestToPay(
                $phone,
                $package->price,
                $reference,
                $currency,
                "Payment for {$package->name}",
                "Hotspot Access"
            );

            if ($response['success']) {
                // Create payment record
                $payment = TenantPayment::create([
                    'tenant_id' => $tenant->id,
                    'phone' => $phone,
                    'hotspot_package_id' => $package->id,
                    'amount' => $package->price,
                    'currency' => $currency,
                    'payment_method' => 'momo',
                    'receipt_number' => $reference,
                    'status' => 'pending',
                    'checkout_request_id' => $response['reference_id'],
                    'response' => $response,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment initiated. Please check your phone for the prompt.',
                    'payment_id' => $payment->id,
                    'reference_id' => $response['reference_id'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $response['message'] ?? 'Failed to initiate MoMo payment.'
            ], 500);

        } catch (\Exception $e) {
            Log::error('MoMo Checkout Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle MoMo callback.
     */
    public function callback(Request $request)
    {
        // MTN MoMo callbacks are usually sent to the URL provided in the developer portal
        // or can be polled. For simplicity and reliability, we'll implement polling first,
        // but this method can handle the POST if configured.
        Log::info('MoMo Callback received', ['data' => $request->all()]);
        return response()->json(['status' => 'ok']);
    }

    /**
     * Check payment status.
     */
    public function checkStatus($referenceId)
    {
        try {
            $payment = TenantPayment::withoutGlobalScopes()
                ->where('checkout_request_id', $referenceId)
                ->firstOrFail();

            $tenant = Tenant::find($payment->tenant_id);
            $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $tenant->id)
                ->where('provider', 'momo')
                ->first();

            $this->momoService->setCredentials([
                'api_user' => $gateway->momo_api_user,
                'api_key' => $gateway->momo_api_key,
                'subscription_key' => $gateway->momo_subscription_key,
                'environment' => $gateway->momo_env,
            ]);

            $statusResponse = $this->momoService->getRequestStatus($referenceId);

            if ($statusResponse['success']) {
                $status = $statusResponse['status']; // successful, failed, pending

                if ($status === 'successful' && $payment->status !== 'paid') {
                    $payment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'checked' => true,
                    ]);

                    // Handle successful payment (create user, etc.)
                    // We can reuse the logic from TenantHotspotController or move it to a service
                    $this->handleSuccessfulPayment($payment);
                } elseif ($status === 'failed') {
                    $payment->update(['status' => 'failed']);
                }

                return response()->json([
                    'success' => true,
                    'status' => $payment->status,
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Could not verify status.']);

        } catch (\Exception $e) {
            Log::error('MoMo Status Check Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function handleSuccessfulPayment($payment)
    {
        // This is a simplified version of TenantHotspotController@handleSuccessfulPayment
        // Ideally, this should be in a PaymentService to avoid duplication.
        $package = TenantHotspot::find($payment->hotspot_package_id);
        
        $existingUser = NetworkUser::withoutGlobalScopes()
            ->where('tenant_id', $payment->tenant_id)
            ->where('phone', $payment->phone)
            ->where('type', 'hotspot')
            ->first();

        if ($existingUser) {
            $baseDate = ($existingUser->expires_at && $existingUser->expires_at->isFuture()) ? $existingUser->expires_at : now();
            $existingUser->expires_at = $this->calculateExpiry($package, $baseDate);
            $existingUser->save();
            $payment->update(['user_id' => $existingUser->id]);
            return;
        }

        $username = NetworkUser::generateHotspotUsername($payment->tenant_id);
        $password = Str::random(8);

        $user = NetworkUser::create([
            'tenant_id' => $payment->tenant_id,
            'username' => $username,
            'password' => $password,
            'phone' => $payment->phone,
            'type' => 'hotspot',
            'hotspot_package_id' => $package->id,
            'expires_at' => $this->calculateExpiry($package),
            'registered_at' => now(),
        ]);

        $payment->update(['user_id' => $user->id]);
    }

    private function calculateExpiry($package, $baseDate = null)
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

    /**
     * Normalize phone number based on country dial code.
     */
    private function formatPhoneNumber(string $phone, string $dialCode): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (substr($phone, 0, 1) === '0') {
            return $dialCode . substr($phone, 1);
        }

        if (str_starts_with($phone, $dialCode)) {
            return $phone;
        }

        if (strlen($phone) === 9) {
            return $dialCode . $phone;
        }

        return null;
    }
}
