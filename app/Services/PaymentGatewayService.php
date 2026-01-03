<?php

namespace App\Services;

use App\Models\TenantPaymentGateway;
use App\Models\Tenants\TenantPayment;
use App\Services\MomoService;
use App\Services\MpesaService;
use App\Services\PaystackService;
use App\Services\CountryService;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    protected $momoService;
    protected $mpesaService;
    protected $paystackService;

    public function __construct(MomoService $momoService, MpesaService $mpesaService, PaystackService $paystackService)
    {
        $this->momoService = $momoService;
        $this->mpesaService = $mpesaService;
        $this->paystackService = $paystackService;
    }

    public function initiatePayment($user, $amount, $phone, $type, $metadata, $method)
    {
        $tenantId = $user->tenant_id;
        $tenant = $user->tenant;
        $countryCode = $tenant->country_code ?? 'KE';
        $countryData = CountryService::getCountryData($countryCode);
        $currency = $countryData['currency'] ?? 'KES';
        $dialCode = $countryData['dial_code'] ?? '254';

        // Normalize phone
        $phone = $this->formatPhoneNumber($phone, $dialCode);
        if (!$phone) {
            return ['success' => false, 'message' => "Invalid phone number format for {$countryData['name']}."];
        }

        if ($method === 'momo') {
            return $this->initiateMomo($user, $amount, $phone, $currency, $type, $metadata);
        } elseif ($method === 'mpesa') {
            return $this->initiateMpesa($user, $amount, $phone, $currency, $type, $metadata);
        } elseif ($method === 'paystack') {
            return $this->initiatePaystack($user, $amount, $phone, $currency, $type, $metadata);
        }

        return ['success' => false, 'message' => 'Unsupported payment method.'];
    }

    protected function initiateMomo($user, $amount, $phone, $currency, $type, $metadata)
    {
        $gateway = TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('provider', 'momo')
            ->where('is_active', true)
            ->first();

        if (!$gateway) {
            return ['success' => false, 'message' => 'MoMo payment not configured.'];
        }

        $this->momoService->setCredentials([
            'api_user' => $gateway->momo_api_user,
            'api_key' => $gateway->momo_api_key,
            'subscription_key' => $gateway->momo_subscription_key,
            'environment' => $gateway->momo_env,
        ]);

        $reference = strtoupper(uniqid("{$type}|MOMO|"));
        
        $response = $this->momoService->requestToPay(
            $phone,
            $amount,
            $reference,
            $currency,
            ucfirst($type) . " for {$user->username}",
            "Internet Payment"
        );

        if ($response['success']) {
            $payment = TenantPayment::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'phone' => $phone,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => 'momo',
                'receipt_number' => $reference,
                'status' => 'pending',
                'checkout_request_id' => $response['reference_id'],
                'package_id' => $metadata['package_id'] ?? ($metadata['new_package_id'] ?? null),
                'hotspot_package_id' => $metadata['hotspot_package_id'] ?? null,
                'response' => array_merge($response, ['metadata' => $metadata]),
            ]);

            return [
                'success' => true,
                'message' => 'Payment initiated. Please check your phone.',
                'reference_id' => $response['reference_id'],
                'payment_id' => $payment->id
            ];
        }

        return ['success' => false, 'message' => $response['message'] ?? 'Payment initiation failed.'];
    }

    protected function initiateMpesa($user, $amount, $phone, $currency, $type, $metadata)
    {
        $gateway = TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('provider', 'mpesa')
            ->where('use_own_api', true)
            ->where('is_active', true)
            ->first();

        if ($gateway) {
            $this->mpesaService->setCredentials([
                'consumer_key' => $gateway->mpesa_consumer_key,
                'consumer_secret' => $gateway->mpesa_consumer_secret,
                'shortcode' => $gateway->mpesa_shortcode,
                'passkey' => $gateway->mpesa_passkey,
                'environment' => $gateway->mpesa_env,
            ]);
        } elseif ($user->tenant->country_code !== 'KE') {
             return ['success' => false, 'message' => 'M-Pesa is not configured for this provider.'];
        }

        $receiptNumber = strtoupper(uniqid("{$type}|MPESA|"));

        try {
            $response = $this->mpesaService->stkPush(
                $phone,
                $amount,
                $receiptNumber,
                ucfirst($type) . " - {$user->username}"
            );

            if ($response['success']) {
                $payment = TenantPayment::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'phone' => $phone,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => 'mpesa',
                    'receipt_number' => $receiptNumber,
                    'status' => 'pending',
                    'checkout_request_id' => $response['checkout_request_id'] ?? null,
                    'merchant_request_id' => $response['merchant_request_id'] ?? null,
                    'package_id' => $metadata['package_id'] ?? ($metadata['new_package_id'] ?? null),
                    'hotspot_package_id' => $metadata['hotspot_package_id'] ?? null,
                    'response' => array_merge($response, ['metadata' => $metadata]),
                    'disbursement_status' => $gateway 
                        ? ($gateway->mpesa_env === 'sandbox' ? 'testing' : 'completed') 
                        : (config('mpesa.environment') === 'sandbox' ? 'testing' : 'pending'),
                ]);

                // Dispatch status check job
                \App\Jobs\CheckMpesaPaymentStatusJob::dispatch($payment)->delay(now()->addSeconds(30));

                return [
                    'success' => true,
                    'message' => 'STK Push sent. Please check your phone.',
                    'reference_id' => $payment->checkout_request_id ?? $receiptNumber, // Use checkout_id for status check if possible, or receipt
                    'payment_id' => $payment->id
                ];
            }

            return ['success' => false, 'message' => $response['message'] ?? 'STK Push failed.'];

        } catch (\Exception $e) {
            Log::error('M-Pesa STK Push failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to initiate M-Pesa payment.'];
        }
    }

    private function formatPhoneNumber(string $phone, string $dialCode): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (substr($phone, 0, 1) === '0') {
            return $dialCode . substr($phone, 1);
        }

        if (str_starts_with($phone, $dialCode)) {
            return $phone;
        }

        if (strlen($phone) === 9) { // Assuming 9 digits without leading 0 or country code
            return $dialCode . $phone;
        }

        return null;
    }

    protected function initiatePaystack($user, $amount, $phone, $currency, $type, $metadata)
    {
        $gateway = TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('provider', 'paystack')
            ->where('is_active', true)
            ->first();

        if (!$gateway || !$gateway->paystack_secret_key) {
            return ['success' => false, 'message' => 'Paystack payment not configured.'];
        }

        $this->paystackService->setCredentials([
            'secret_key' => $gateway->paystack_secret_key,
            'public_key' => $gateway->paystack_public_key,
        ]);

        // Use phone as email if no email provided (Paystack requires email)
        $email = $user->email ?? $phone . '@customer.local';
        $reference = $this->paystackService->generateReference(strtoupper($type));
        
        $response = $this->paystackService->initializeTransaction(
            $email,
            $amount,
            $reference,
            array_merge($metadata, [
                'user_id' => $user->id,
                'username' => $user->username,
                'phone' => $phone,
            ])
        );

        if ($response['success']) {
            $payment = TenantPayment::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'phone' => $phone,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => 'paystack',
                'receipt_number' => $reference,
                'status' => 'pending',
                'checkout_request_id' => $response['access_code'],
                'package_id' => $metadata['package_id'] ?? ($metadata['new_package_id'] ?? null),
                'hotspot_package_id' => $metadata['hotspot_package_id'] ?? null,
                'response' => array_merge($response, ['metadata' => $metadata]),
            ]);

            return [
                'success' => true,
                'message' => 'Payment initialized. Complete payment in the popup.',
                'reference_id' => $reference,
                'access_code' => $response['access_code'],
                'public_key' => $gateway->paystack_public_key,
                'payment_id' => $payment->id
            ];
        }

        return ['success' => false, 'message' => $response['message'] ?? 'Payment initiation failed.'];
    }
}
