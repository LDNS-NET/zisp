<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Tenants\TenantPayment as Payment;
use IntaSend\IntaSendPHP\Collection;

class CheckIntaSendPaymentStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Identifier can be a numeric payment ID or an IntaSend invoice/checkout string
    public $identifier;

    /**
     * Create a new job instance.
     */
    public function __construct($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $identifier = $this->identifier;

        // Try to resolve a Payment model when possible
        $payment = null;
        if (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier))) {
            $payment = Payment::find((int)$identifier);
        }

        if (!$payment && is_string($identifier)) {
            // Treat the identifier as an IntaSend invoice, checkout id, or api_ref
            $payment = Payment::where('intasend_reference', $identifier)
                ->orWhere('intasend_checkout_id', $identifier)
                ->orWhere('receipt_number', $identifier)
                ->first();
        }

        $token = env('INTASEND_SECRET_KEY');
        $publishable_key = env('INTASEND_PUBLIC_KEY');
        $test = env('INTASEND_TEST_ENV', false);

        try {
            $collection = new Collection();
            $collection->init([
                'token' => $token,
                'publishable_key' => $publishable_key,
                'test' => $test,
            ]);

            // If we have a payment model, prefer its invoice id; otherwise use the identifier directly
            $invoice = null;
            if ($payment && !empty($payment->intasend_reference)) {
                $invoice = $payment->intasend_reference;
            } elseif (is_string($identifier)) {
                $invoice = $identifier;
            }

            if (!$invoice) {
                Log::warning('CheckIntaSendPaymentStatus: no invoice identifier available', ['identifier' => $identifier]);
                return;
            }

            $response = $collection->status($invoice);
            $resp = json_decode(json_encode($response), true);
            Log::info('CheckIntaSendPaymentStatus response', ['identifier' => $identifier, 'resp' => $resp]);

            // Extract status from known response shapes
            $status = $resp['invoice']['state'] ?? $resp['data']['status'] ?? $resp['status'] ?? null;
            $status = $status ? strtoupper($status) : null;

            // If we don't have a payment record yet and the status is final, create it
            if (!$payment && in_array($status, ['PAID', 'SUCCESS', 'COMPLETED', 'COMPLETE', 'FAILED', 'CANCELLED', 'REJECTED'])) {
                $apiRef = $resp['invoice']['api_ref'] ?? $resp['api_ref'] ?? (str_contains($identifier, '|') ? $identifier : null);
                
                if ($apiRef) {
                    $payment = $this->createPaymentFromApiRef($apiRef, $resp, in_array($status, ['PAID', 'SUCCESS', 'COMPLETED', 'COMPLETE']) ? 'paid' : 'failed');
                } else {
                    // Fallback for non-encoded api_ref
                    $payment = Payment::create([
                        'phone' => $resp['invoice']['customer_phone'] ?? $resp['data']['phone'] ?? null,
                        'package_id' => null,
                        'amount' => $resp['invoice']['amount'] ?? $resp['data']['amount'] ?? 0,
                        'status' => in_array($status, ['PAID', 'SUCCESS', 'COMPLETED', 'COMPLETE']) ? 'paid' : 'failed',
                        'intasend_reference' => $invoice,
                        'intasend_checkout_id' => $resp['invoice']['checkout_id'] ?? $resp['checkout_id'] ?? null,
                        'transaction_id' => $resp['invoice']['mpesa_reference'] ?? $resp['data']['transaction_id'] ?? null,
                        'response' => $resp,
                        'paid_at' => in_array($status, ['PAID', 'SUCCESS', 'COMPLETED', 'COMPLETE']) ? now() : null,
                    ]);
                }

                Log::info('Created TenantPayment from IntaSend status response', ['payment_id' => $payment->id]);
                return;
            }

            if (!$payment) {
                // Nothing more to do if we couldn't resolve or create a payment yet
                Log::info('No payment model found for identifier and status is not final', ['identifier' => $identifier, 'status' => $status]);
                return;
            }

            // If payment exists and already resolved, skip
            if (in_array($payment->status, ['paid', 'failed', 'cancelled'])) {
                return;
            }

            if (in_array($status, ['PAID', 'SUCCESS', 'COMPLETED', 'COMPLETE'])) {
                $payment->status = 'paid';
                $payment->checked = true;
                $payment->paid_at = $payment->paid_at ?? now();
                $payment->transaction_id = $resp['invoice']['mpesa_reference'] ?? $resp['data']['transaction_id'] ?? $resp['id'] ?? $payment->transaction_id;
                $payment->response = array_merge($payment->response ?? [], $resp);
                $payment->save();
                return;
            }

            if (in_array($status, ['PENDING', 'PROCESSING'])) {
                Log::info('Payment still pending', ['payment_id' => $payment->id, 'status' => $status]);
                return;
            }

            // Treat everything else as failed
            $payment->status = 'failed';
            $payment->response = array_merge($payment->response ?? [], $resp);
            $payment->save();

        } catch (\Exception $e) {
            Log::error('CheckIntaSendPaymentStatus exception', ['identifier' => $identifier, 'error' => $e->getMessage()]);
            // Let the queue retry according to retry settings
            throw $e;
        }
    }

    /**
     * Create a payment record from encoded api_ref.
     */
    private function createPaymentFromApiRef($apiRef, $statusData, $status = 'paid')
    {
        // Format: HS|{package_id}|{phone}|{uniqid} or PK|{package_id}|{phone}|{uniqid}
        $parts = explode('|', $apiRef);
        if (count($parts) >= 3) {
            $prefix = $parts[0];
            $packageId = $parts[1];
            $phone = $parts[2];
            
            $data = [
                'phone' => $phone,
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
            ];

            if ($prefix === 'HS') {
                $data['hotspot_package_id'] = $packageId;
                $data['package_id'] = null;
            } else {
                $data['package_id'] = $packageId;
                $data['hotspot_package_id'] = null;
            }
            
            return Payment::create($data);
        }
        
        return null;
    }


}
