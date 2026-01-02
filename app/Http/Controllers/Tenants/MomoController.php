<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Tenants\TenantHotspot;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\NetworkUser;
use App\Services\MomoService;
use App\Services\PaymentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\CountryService;

class MomoController extends Controller
{
    protected $momoService;
    protected $processingService;

    public function __construct(MomoService $momoService, PaymentProcessingService $processingService)
    {
        $this->momoService = $momoService;
        $this->processingService = $processingService;
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
            $phone = $this->formatPhoneNumber($request->phone, $dialCode, $gateway->momo_env === 'sandbox');
            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid phone number format for {$countryData['name']}."
                ], 400);
            }


            $reference = "HS|MOMO|{$package->id}|{$request->phone}|" . strtoupper(uniqid());

            Log::info('MoMo Request Initiated', [
                'original_phone' => $request->phone,
                'normalized_phone' => $phone,
                'environment' => $gateway->momo_env,
                'currency' => $currency
            ]);

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

                Log::info('MoMo Payment Record Created', [
                    'id' => $payment->id,
                    'checkout_request_id' => $payment->checkout_request_id,
                    'tenant_id' => $payment->tenant_id
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
        Log::info('MoMo Callback received', ['data' => $request->all()]);
        return response()->json(['status' => 'ok']);
    }

    /**
     * Check payment status.
     */
    public function checkStatus($referenceId)
    {
        try {
            Log::info('MoMo Status Check Attempt', [
                'referenceId' => $referenceId,
            ]);
            
            $payment = \Illuminate\Support\Facades\DB::table('tenant_payments')
                ->where('checkout_request_id', $referenceId)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Payment record not found.'
                ], 404);
            }
            
            Log::info('MoMo Payment Record Found', ['id' => $payment->id]);

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
                    \Illuminate\Support\Facades\DB::table('tenant_payments')
                        ->where('id', $payment->id)
                        ->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'checked' => true,
                        ]);

                    // Handle successful payment via processing service
                    $this->processingService->processSuccess(TenantPayment::find($payment->id));
                } elseif ($status === 'failed') {
                    \Illuminate\Support\Facades\DB::table('tenant_payments')
                        ->where('id', $payment->id)
                        ->update(['status' => 'failed']);
                }

                return response()->json([
                    'success' => true,
                    'status' => $status === 'successful' ? 'paid' : ($status === 'failed' ? 'failed' : 'pending'),
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Could not verify status.']);

        } catch (\Exception $e) {
            Log::error('MoMo Status Check Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Normalize phone number based on country dial code.
     */
    private function formatPhoneNumber(string $phone, string $dialCode, bool $isSandbox = false): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if ($isSandbox && str_starts_with($phone, '46')) {
            return $phone;
        }

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
