<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Services\MomoService;
use App\Models\Tenants\TenantPayment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RenewalController extends Controller
{
    protected $momoService;

    public function __construct(MomoService $momoService)
    {
        $this->momoService = $momoService;
    }

    public function index()
    {
        $user = Auth::guard('customer')->user();
        $user->load(['package', 'hotspotPackage']);
        
        return Inertia::render('Customer/Renew', [
            'user' => $user,
            'package' => $user->type === 'hotspot' ? $user->hotspotPackage : $user->package,
        ]);
    }

    public function initiateMomoPayment(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'months' => 'required|integer|min:1',
        ]);

        $user = Auth::guard('customer')->user();
        $package = $user->type === 'hotspot' ? $user->hotspotPackage : $user->package;
        
        if (!$package) {
            return response()->json(['success' => false, 'message' => 'No active package found.'], 400);
        }

        $amount = $package->price * $request->months;
        $currency = tenant()->currency ?? 'KES'; 
        
        $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('provider', 'momo')
            ->where('is_active', true)
            ->first();

        if (!$gateway) {
            return response()->json(['success' => false, 'message' => 'MoMo payment not configured.'], 400);
        }

        $this->momoService->setCredentials([
            'api_user' => $gateway->momo_api_user,
            'api_key' => $gateway->momo_api_key,
            'subscription_key' => $gateway->momo_subscription_key,
            'environment' => $gateway->momo_env,
        ]);

        $reference = "REN|MOMO|{$user->id}|{$request->phone}|" . strtoupper(uniqid());
        
        // Basic normalization
        $phone = preg_replace('/[^0-9]/', '', $request->phone);

        $response = $this->momoService->requestToPay(
            $phone,
            $amount,
            $reference,
            $currency,
            "Renewal for {$user->username}",
            "Internet Renewal"
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
                'response' => $response,
                // Store metadata in response or separate column if available. 
                // Using response array to store metadata for now as we don't have metadata column
                'response' => array_merge($response, [
                    'metadata' => [
                        'type' => 'renewal',
                        'months' => $request->months,
                        'package_id' => $package->id
                    ]
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated. Please check your phone.',
                'reference_id' => $response['reference_id'],
                'payment_id' => $payment->id
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $response['message'] ?? 'Payment initiation failed.'
        ], 500);
    }

    public function checkPaymentStatus($referenceId)
    {
        $payment = DB::table('tenant_payments')
            ->where('checkout_request_id', $referenceId)
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json([
                'success' => true,
                'status' => 'paid',
                'message' => 'Payment successful!',
                'user' => Auth::guard('customer')->user()->fresh()
            ]);
        }

        // Check with MTN if pending
        if ($payment->status === 'pending') {
            $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $payment->tenant_id)
                ->where('provider', 'momo')
                ->first();

            if ($gateway) {
                $this->momoService->setCredentials([
                    'api_user' => $gateway->momo_api_user,
                    'api_key' => $gateway->momo_api_key,
                    'subscription_key' => $gateway->momo_subscription_key,
                    'environment' => $gateway->momo_env,
                ]);

                $status = $this->momoService->getTransactionStatus($referenceId);

                if ($status && isset($status['status'])) {
                    if ($status['status'] === 'SUCCESSFUL') {
                        // Mark as paid
                        DB::table('tenant_payments')
                            ->where('id', $payment->id)
                            ->update([
                                'status' => 'paid', 
                                'paid_at' => now(),
                                'response' => json_encode($status) // Update response with latest status
                            ]);
                        
                        // Process Renewal
                        $this->processRenewal($payment);

                        return response()->json([
                            'success' => true,
                            'status' => 'paid',
                            'message' => 'Payment successful!',
                            'user' => Auth::guard('customer')->user()->fresh()
                        ]);
                    } elseif ($status['status'] === 'FAILED') {
                        DB::table('tenant_payments')
                            ->where('id', $payment->id)
                            ->update(['status' => 'failed']);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'status' => $payment->status,
            'message' => 'Payment is ' . $payment->status
        ]);
    }

    protected function processRenewal($payment)
    {
        $user = \App\Models\Tenants\NetworkUser::find($payment->user_id);
        if (!$user) return;

        // Extract months from metadata stored in response
        $response = is_string($payment->response) ? json_decode($payment->response, true) : $payment->response;
        $months = $response['metadata']['months'] ?? 1;

        $currentExpiry = $user->expires_at && $user->expires_at->isFuture() 
            ? $user->expires_at 
            : now();
            
        $user->expires_at = $currentExpiry->addMonths((int)$months);
        $user->save();
    }
}
