<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\TenantPayment;
use App\Services\PaymentProcessingService;
use Illuminate\Support\Facades\Log;

class TinypesaController extends Controller
{
    /**
     * Handle Tinypesa Callback
     */
    public function callback(Request $request)
    {
        Log::info('Tinypesa: Callback received', $request->all());

        // Tinypesa sends JSON body
        $data = $request->input('Body.stkCallback');
        
        if (!$data) {
            // Sometimes it sends flat structure or different format depending on version
            $data = $request->all();
        }

        // Check if successful
        // Tinypesa mimics M-Pesa structure often or has its own. 
        // Standard Tinypesa V1 webhook:
        // {
        //     "Body": {
        //         "stkCallback": {
        //             "MerchantRequestID": "...",
        //             "CheckoutRequestID": "...",
        //             "ResultCode": 0,
        //             "ResultDesc": "The service request is processed successfully.",
        //             "CallbackMetadata": { ... }
        //         }
        //     }
        // }
        
        // However, checking docs/behavior, sometimes it's flat. 
        // Let's assume M-Pesa style structure since it proxies M-Pesa.
        
        $checkoutRequestId = $data['CheckoutRequestID'] ?? $request->input('Body.stkCallback.CheckoutRequestID');
        $resultCode = $data['ResultCode'] ?? $request->input('Body.stkCallback.ResultCode');

        if (!$checkoutRequestId) {
            Log::error('Tinypesa: Missing CheckoutRequestID');
            return response()->json(['status' => 'error'], 400);
        }

        $payment = TenantPayment::where('checkout_request_id', $checkoutRequestId)->first();

        if (!$payment) {
            Log::error('Tinypesa: Payment not found for CheckoutRequestID: ' . $checkoutRequestId);
            return response()->json(['status' => 'ignored']);
        }

        if ($resultCode == 0) {
            $payment->update([
                'status' => 'paid',
                'disbursement_status' => 'completed',
                'paid_at' => now(),
                'response' => array_merge($payment->response ?? [], $request->all())
            ]);

            // Automatic connection on success (same as M-Pesa/Paystack)
            if ($payment->hotspot_package_id || $payment->package_id) {
                app(PaymentProcessingService::class)->processSuccess($payment);
            }
        } else {
            // Failed
            $payment->update([
                'status' => 'failed',
                'response' => array_merge($payment->response ?? [], $request->all())
            ]);
        }

        return response()->json(['status' => 'success']);
    }
}
