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
            $this->payment->update(['disbursement_status' => 'completed']);
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
                // Bank disbursement might need a different API or B2B
                $destination = $gateway->bank_account;
                $remarks .= " (Bank: {$gateway->bank_name})";
                break;
        }

        if (!$destination) {
            $this->payment->update([
                'disbursement_status' => 'failed',
                'disbursement_response' => ['error' => 'No destination resolved for payout method: ' . $payoutMethod]
            ]);
            return;
        }

        // For now, we use B2C for all disbursements as per user request "our api supports all b2c,b2b etc"
        // In a real scenario, we might switch between B2C and B2B/Paybill depending on the destination
        $response = $mpesa->b2cPayment($destination, $this->payment->amount, $remarks);

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
