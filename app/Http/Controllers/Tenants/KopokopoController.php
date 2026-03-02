<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\TenantPayment;
use App\Services\PaymentProcessingService;
use Illuminate\Support\Facades\Log;

class KopokopoController extends Controller
{
    /**
     * Handle KopoKopo webhook (callback for STK Push / incoming payments).
     * Called by KopoKopo when payment is completed; triggers automatic connection like M-Pesa.
     */
    public function webhook(Request $request)
    {
        Log::info('KopoKopo: Webhook received', ['payload_keys' => array_keys($request->all())]);

        $data = $request->all();
        $event = $data['event'] ?? $data;
        $resource = $event['resource'] ?? $event['data']['attributes']['event']['resource'] ?? [];
        $reference = $resource['reference'] ?? $resource['metadata']['reference'] ?? ($data['metadata']['reference'] ?? null);
        $status = strtolower($resource['status'] ?? $event['attributes']['status'] ?? $data['status'] ?? '');

        if (!$reference) {
            Log::warning('KopoKopo: Webhook missing reference', ['data' => $data]);
            return response()->json(['status' => 'ignored'], 200);
        }

        $payment = TenantPayment::withoutGlobalScopes()
            ->where('payment_method', 'kopokopo')
            ->where(function ($q) use ($reference) {
                $q->where('receipt_number', $reference)
                    ->orWhere('transaction_id', $reference);
            })
            ->first();

        if (!$payment) {
            Log::warning('KopoKopo: Payment not found for reference', ['reference' => $reference]);
            return response()->json(['status' => 'ignored'], 200);
        }

        if ($payment->status === 'paid') {
            Log::info('KopoKopo: Payment already processed', ['payment_id' => $payment->id]);
            return response()->json(['status' => 'success'], 200);
        }

        if (!in_array($status, ['received', 'success', 'completed'], true)) {
            Log::info('KopoKopo: Webhook status not success', ['status' => $status, 'reference' => $reference]);
            return response()->json(['status' => 'ack'], 200);
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'response' => array_merge($payment->response ?? [], ['webhook' => $data]),
        ]);

        if ($payment->hotspot_package_id || $payment->package_id) {
            app(PaymentProcessingService::class)->processSuccess($payment);
        }

        Log::info('KopoKopo: Payment processed via webhook', ['payment_id' => $payment->id, 'reference' => $reference]);
        return response()->json(['status' => 'success'], 200);
    }
}
