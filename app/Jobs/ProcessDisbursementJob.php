<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDisbursementJob implements ShouldQueue
{
    use Queueable;
    use \Illuminate\Queue\InteractsWithQueue;
    use \Illuminate\Queue\SerializesModels;

    protected $payment;

    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\Tenants\TenantPayment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->payment->disbursement_status !== 'pending') {
            return;
        }

        $tenantId = $this->payment->tenant_id;
        $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$gateway) {
            \Log::warning('No active payment gateway found for disbursement', ['payment_id' => $this->payment->id]);
            return;
        }

        // If tenant uses their own API, we don't disburse (they already received the money)
        if ($gateway->use_own_api) {
            $status = ($gateway->mpesa_env === 'sandbox') ? 'testing' : 'completed';
            $this->payment->update(['disbursement_status' => $status]);
            return;
        }

        // Check if system M-Pesa is in sandbox mode
        if (config('mpesa.environment') === 'sandbox') {
            $this->payment->update([
                'disbursement_status' => 'testing',
                'disbursement_response' => ['message' => 'Disbursement skipped: System is in Sandbox/Testing mode.']
            ]);
            return;
        }

        $this->payment->update(['disbursement_status' => 'processing']);

        $mpesa = app(\App\Services\MpesaService::class);
        
        // Resolve payout details based on method
        $payoutMethod = $gateway->payout_method;
        $destination = null;
        $remarks = "Disbursement for Payment #{$this->payment->receipt_number}";

        switch ($payoutMethod) {
            case 'mpesa_phone':
                $destination = $gateway->phone_number;
                break;
            case 'till':
                $destination = $gateway->till_number;
                break;
            case 'paybill':
                $destination = $gateway->paybill_business_number;
                $remarks .= " (Acc: {$gateway->paybill_account_number})";
                break;
            case 'bank':
                // For bank, destination is the bank's paybill/business number
                // and account is the tenant's bank account number
                $destination = $gateway->bank_paybill;
                $remarks .= " (Bank: {$gateway->bank_name}, Acc: {$gateway->bank_account})";
                break;
        }

        if (!$destination) {
            $this->payment->update([
                'disbursement_status' => 'failed',
                'disbursement_response' => ['error' => 'No destination resolved for payout method: ' . $payoutMethod]
            ]);
            return;
        }

        // For bank, we use B2B. For others, we use B2C.
        if ($payoutMethod === 'bank') {
            $response = $mpesa->b2bPayment($destination, $this->payment->amount, $gateway->bank_account, $remarks);
        } else {
            $response = $mpesa->b2cPayment($destination, $this->payment->amount, $remarks);
        }

        if ($response['success']) {
            $this->payment->update([
                'disbursement_status' => 'completed',
                'disbursement_transaction_id' => $response['conversation_id'] ?? null,
                'disbursement_response' => $response['response'] ?? []
            ]);
        } else {
            $this->payment->update([
                'disbursement_status' => 'failed',
                'disbursement_response' => $response['response'] ?? ['error' => $response['message']]
            ]);
            
            \Log::error('Disbursement failed', [
                'payment_id' => $this->payment->id,
                'response' => $response
            ]);
        }
    }
}
