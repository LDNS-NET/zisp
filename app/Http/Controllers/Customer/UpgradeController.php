<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Services\MomoService;
use App\Models\Tenants\TenantPayment;
use Illuminate\Support\Facades\DB;

class UpgradeController extends Controller
{
    protected $momoService;
    protected $mpesaService;

    public function __construct(MomoService $momoService, \App\Services\MpesaService $mpesaService)
    {
        $this->momoService = $momoService;
        $this->mpesaService = $mpesaService;
    }

    public function index()
    {
        $user = Auth::guard('customer')->user();
        $user->load(['package', 'hotspotPackage']);
        
        $currentPackageId = $user->package_id ?? $user->hotspot_package_id;
        
        $packages = \App\Models\Package::where('id', '!=', $currentPackageId)
            ->where('type', $user->type)
            ->get();

        $gateways = \App\Models\TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->get(['provider', 'label', 'is_active']);

        return Inertia::render('Customer/Upgrade', [
            'user' => $user,
            'currentPackage' => $user->type === 'hotspot' ? $user->hotspotPackage : $user->package,
            'packages' => $packages,
            'gateways' => $gateways,
        ]);
    }

    public function initiatePayment(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'provider' => 'required|string|in:momo,mpesa',
        ]);

        if ($request->provider === 'momo') {
            return $this->initiateMomoPayment($request);
        } elseif ($request->provider === 'mpesa') {
            return $this->initiateMpesaPayment($request);
        }

        return response()->json(['success' => false, 'message' => 'Invalid provider.'], 400);
    }

    protected function initiateMomoPayment(Request $request)
    {
        $user = Auth::guard('customer')->user();
        $newPackage = \App\Models\Package::findOrFail($request->package_id);
        
        $amount = $newPackage->price;
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

        $reference = "UPG|MOMO|{$user->id}|{$request->phone}|" . strtoupper(uniqid());
        $phone = preg_replace('/[^0-9]/', '', $request->phone);

        $response = $this->momoService->requestToPay(
            $phone,
            $amount,
            $reference,
            $currency,
            "Upgrade to {$newPackage->name}",
            "Package Upgrade"
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
                'response' => array_merge($response, [
                    'metadata' => [
                        'type' => 'upgrade',
                        'new_package_id' => $newPackage->id
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

    protected function initiateMpesaPayment(Request $request)
    {
        $user = Auth::guard('customer')->user();
        $newPackage = \App\Models\Package::findOrFail($request->package_id);
        
        $amount = $newPackage->price;
        $currency = tenant()->currency ?? 'KES'; 
        
        $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('provider', 'mpesa')
            ->where('is_active', true)
            ->first();

        if (!$gateway) {
            return response()->json(['success' => false, 'message' => 'M-Pesa payment not configured.'], 400);
        }

        $this->mpesaService->setCredentials([
            'consumer_key' => $gateway->mpesa_consumer_key,
            'consumer_secret' => $gateway->mpesa_consumer_secret,
            'passkey' => $gateway->mpesa_passkey,
            'shortcode' => $gateway->mpesa_shortcode,
            'environment' => $gateway->mpesa_env,
        ]);

        $reference = "UPG|MPESA|{$user->id}|" . strtoupper(uniqid());
        $phone = $request->phone;

        $response = $this->mpesaService->stkPush(
            $phone,
            $amount,
            $reference,
            "Upgrade to {$newPackage->name}"
        );

        if ($response['success']) {
            $payment = TenantPayment::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'phone' => $phone,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => 'mpesa',
                'receipt_number' => $reference,
                'status' => 'pending',
                'checkout_request_id' => $response['checkout_request_id'],
                'response' => array_merge($response, [
                    'metadata' => [
                        'type' => 'upgrade',
                        'new_package_id' => $newPackage->id
                    ]
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'STK Push sent. Please check your phone.',
                'reference_id' => $response['checkout_request_id'],
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

        if ($payment->status === 'pending') {
            if ($payment->payment_method === 'momo') {
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
                            DB::table('tenant_payments')
                                ->where('id', $payment->id)
                                ->update([
                                    'status' => 'paid', 
                                    'paid_at' => now(),
                                    'response' => json_encode($status)
                                ]);
                            
                            $this->processUpgrade($payment);

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
            } elseif ($payment->payment_method === 'mpesa') {
                $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $payment->tenant_id)
                    ->where('provider', 'mpesa')
                    ->first();

                if ($gateway) {
                    $this->mpesaService->setCredentials([
                        'consumer_key' => $gateway->mpesa_consumer_key,
                        'consumer_secret' => $gateway->mpesa_consumer_secret,
                        'passkey' => $gateway->mpesa_passkey,
                        'shortcode' => $gateway->mpesa_shortcode,
                        'environment' => $gateway->mpesa_env,
                    ]);

                    $status = $this->mpesaService->queryTransactionStatus($referenceId);

                    if ($status['success']) {
                        if ($status['status'] === 'paid') {
                            DB::table('tenant_payments')
                                ->where('id', $payment->id)
                                ->update([
                                    'status' => 'paid', 
                                    'paid_at' => now(),
                                    'response' => json_encode($status)
                                ]);
                            
                            $this->processUpgrade($payment);

                            return response()->json([
                                'success' => true,
                                'status' => 'paid',
                                'message' => 'Payment successful!',
                                'user' => Auth::guard('customer')->user()->fresh()
                            ]);
                        } elseif ($status['status'] === 'failed' || $status['status'] === 'cancelled') {
                            DB::table('tenant_payments')
                                ->where('id', $payment->id)
                                ->update(['status' => 'failed']);
                        }
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

    protected function processUpgrade($payment)
    {
        $user = \App\Models\Tenants\NetworkUser::find($payment->user_id);
        if (!$user) return;

        $response = is_string($payment->response) ? json_decode($payment->response, true) : $payment->response;
        $newPackageId = $response['metadata']['new_package_id'];
        $newPackage = \App\Models\Package::find($newPackageId);

        if ($user->type === 'hotspot') {
            $user->hotspot_package_id = $newPackageId;
        } else {
            $user->package_id = $newPackageId;
        }
        
        $seconds = 0;
        $val = $newPackage->duration_value;
        switch ($newPackage->duration_unit) {
            case 'minutes': $seconds = $val * 60; break;
            case 'hours':   $seconds = $val * 3600; break;
            case 'days':    $seconds = $val * 86400; break;
            case 'weeks':   $seconds = $val * 604800; break;
            case 'months':  $seconds = $val * 2592000; break;
        }
        
        $user->expires_at = now()->addSeconds($seconds);
        $user->save();
    }
}
