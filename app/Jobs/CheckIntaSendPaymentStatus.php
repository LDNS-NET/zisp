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

    // Payment model
    public $payment;

    /**
     * Create a new job instance.
     */
    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Accept both TenantPayment model or numeric ID for backwards compatibility
        if (is_numeric($this->payment)) {
            $this->payment = Payment::find($this->payment);
        }

        if (!$this->payment) {
            Log::warning('CheckIntaSendPaymentStatus: payment not found');
            return;
        }

        // Skip if already processed
        if (in_array($this->payment->status, ['paid', 'failed', 'cancelled'])) {
            return;
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

            // Use payment's IntaSend reference
            if (!$this->payment->intasend_reference) {
                Log::warning('CheckIntaSendPaymentStatus: no invoice identifier available', ['payment_id' => $this->payment->id]);
                return;
            }

            $response = $collection->status($this->payment->intasend_reference);
            $resp = json_decode(json_encode($response), true);
            Log::info('CheckIntaSendPaymentStatus response', ['payment_id' => $this->payment->id, 'resp' => $resp]);

            // Extract status from known response shapes
            $status = $resp['invoice']['state'] ?? $resp['data']['status'] ?? $resp['status'] ?? null;
            $status = $status ? strtoupper($status) : null;

            if (in_array($status, ['PAID', 'SUCCESS', 'COMPLETED', 'COMPLETE'])) {
                $this->payment->status = 'paid';
                $this->payment->checked = true;
                $this->payment->paid_at = $this->payment->paid_at ?? now();
                $this->payment->transaction_id = $resp['invoice']['mpesa_reference'] ?? $resp['data']['transaction_id'] ?? $resp['id'] ?? $this->payment->transaction_id;
                $this->payment->response = array_merge($this->payment->response ?? [], $resp);
                $this->payment->save();
                return;
            }

            if (in_array($status, ['PENDING', 'PROCESSING'])) {
                Log::info('Payment still pending', ['payment_id' => $this->payment->id, 'status' => $status]);
                return;
            }

            // Treat everything else as failed
            $this->payment->status = 'failed';
            $this->payment->response = array_merge($this->payment->response ?? [], $resp);
            $this->payment->save();

        } catch (\Exception $e) {
            Log::error('CheckIntaSendPaymentStatus exception', ['payment_id' => $this->payment->id, 'error' => $e->getMessage()]);
            // Let the queue retry according to retry settings
            throw $e;
        }
    }

}
