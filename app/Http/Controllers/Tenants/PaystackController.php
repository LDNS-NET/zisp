<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PaystackService;
use App\Models\Tenants\TenantPayment;
use App\Models\TenantPaymentGateway;
use App\Models\Tenant;
use App\Services\PaymentProcessingService;
use Illuminate\Support\Facades\Log;

class PaystackController extends Controller
{
    protected $paystackService;
    protected $paymentProcessingService;

    public function __construct(PaystackService $paystackService, PaymentProcessingService $paymentProcessingService)
    {
        $this->paystackService = $paystackService;
        $this->paymentProcessingService = $paymentProcessingService;
    }

    /**
     * Verify Paystack payment
     */
    public function verify(Request $request, $reference)
    {
        try {
            Log::info('Paystack: Verifying payment', ['reference' => $reference]);

            // Find payment by reference
            $payment = TenantPayment::where('receipt_number', $reference)
                ->orWhere('checkout_request_id', $reference)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // If already paid, return success
            if ($payment->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment already verified'
                ]);
            }

            // Get tenant's Paystack credentials
            $gateway = TenantPaymentGateway::where('tenant_id', $payment->tenant_id)
                ->where('provider', 'paystack')
                ->where('is_active', true)
                ->first();

            if (!$gateway) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paystack not configured'
                ], 400);
            }

            // Set credentials and verify
            $this->paystackService->setCredentials([
                'secret_key' => $gateway->paystack_secret_key,
                'public_key' => $gateway->paystack_public_key,
            ]);

            $result = $this->paystackService->verifyTransaction($reference);

            if (!$result || !$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification failed'
                ]);
            }

            // Update payment status
            if ($result['status'] === 'success') {
                $payment->status = 'paid';
                $payment->paid_at = now();
                $payment->response = array_merge($payment->response ?? [], $result);
                $payment->save();

                // Process payment (create/extend user)
                if ($payment->hotspot_package_id || $payment->package_id) {
                    $this->paymentProcessingService->processSuccess($payment);
                }

                // Return user credentials if hotspot
                if ($payment->hotspot_package_id) {
                    $user = \App\Models\Tenants\NetworkUser::where('phone', $payment->phone)
                        ->where('type', 'hotspot')
                        ->first();

                    if ($user) {
                        return response()->json([
                            'success' => true,
                            'status' => 'paid',
                            'user' => [
                                'username' => $user->username,
                                'password' => $user->password,
                                'expires_at' => $user->expires_at->toDateTimeString(),
                            ]
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment successful'
                ]);
            }

            return response()->json([
                'success' => false,
                'status' => $result['status'],
                'message' => 'Payment not successful'
            ]);

        } catch (\Exception $e) {
            Log::error('Paystack: Verification exception', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification error'
            ], 500);
        }
    }

    /**
     * Handle Paystack webhook
     */
    public function webhook(Request $request)
    {
        try {
            // Get raw payload and signature
            $payload = $request->getContent();
            $signature = $request->header('X-Paystack-Signature');

            if (!$signature) {
                Log::warning('Paystack: Webhook missing signature');
                return response()->json(['message' => 'No signature'], 400);
            }

            // Get event data
            $event = json_decode($payload, true);
            
            if (!$event || !isset($event['event'])) {
                Log::warning('Paystack: Invalid webhook payload');
                return response()->json(['message' => 'Invalid payload'], 400);
            }

            // Find payment by reference
            $reference = $event['data']['reference'] ?? null;
            
            if (!$reference) {
                Log::warning('Paystack: Webhook missing reference');
                return response()->json(['message' => 'No reference'], 400);
            }

            $payment = TenantPayment::where('receipt_number', $reference)->first();

            if (!$payment) {
                Log::warning('Paystack: Payment not found for webhook', ['reference' => $reference]);
                return response()->json(['message' => 'Payment not found'], 404);
            }

            // Get tenant's Paystack credentials for signature verification
            $gateway = TenantPaymentGateway::where('tenant_id', $payment->tenant_id)
                ->where('provider', 'paystack')
                ->where('is_active', true)
                ->first();

            if (!$gateway) {
                Log::warning('Paystack: Gateway not found for webhook');
                return response()->json(['message' => 'Gateway not configured'], 400);
            }

            // Verify signature
            $this->paystackService->setCredentials([
                'secret_key' => $gateway->paystack_secret_key,
                'public_key' => $gateway->paystack_public_key,
            ]);

            if (!$this->paystackService->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Paystack: Invalid webhook signature');
                return response()->json(['message' => 'Invalid signature'], 400);
            }

            // Handle event
            if ($event['event'] === 'charge.success') {
                if ($payment->status !== 'paid') {
                    $payment->status = 'paid';
                    $payment->paid_at = now();
                    $payment->response = array_merge($payment->response ?? [], $event['data']);
                    $payment->save();

                    // Process payment
                    if ($payment->hotspot_package_id || $payment->package_id) {
                        $this->paymentProcessingService->processSuccess($payment);
                    }

                    Log::info('Paystack: Payment processed via webhook', ['reference' => $reference]);
                }
            }

            return response()->json(['message' => 'Webhook processed'], 200);

        } catch (\Exception $e) {
            Log::error('Paystack: Webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'Webhook error'], 500);
        }
    }
}
