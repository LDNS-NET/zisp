<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Services\PaymentGatewayService;
use Illuminate\Support\Facades\DB;

class RenewalController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentGatewayService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function index()
    {
        $user = Auth::guard('customer')->user();
        $user->load(['package', 'hotspotPackage', 'tenant']);
        
        $gateways = \App\Models\TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->pluck('provider')
            ->toArray();

        // Filter out bank as it's not configured
        $gateways = array_filter($gateways, fn($g) => $g !== 'bank');
        
        if ($user->type === 'hotspot') {
            $package = \App\Models\Tenants\TenantHotspot::withoutGlobalScopes()->find($user->hotspot_package_id);
        } else {
            $package = \App\Models\Package::withoutGlobalScopes()->find($user->package_id);
        }

        return Inertia::render('Customer/Renew', [
            'user' => $user,
            'package' => $package,
            'paymentMethods' => array_values(array_unique($gateways)),
            'country' => $user->tenant->country_code ?? 'KE',
            'currency' => $user->tenant->currency ?? 'KES',
        ]);
    }

    public function initiatePayment(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'months' => 'required|integer|min:1',
            'payment_method' => 'required|string|in:momo,mpesa,paystack,flutterwave',
        ]);

        $user = Auth::guard('customer')->user();
        if ($user->type === 'hotspot') {
            $package = \App\Models\Tenants\TenantHotspot::withoutGlobalScopes()->find($user->hotspot_package_id);
        } else {
            $package = \App\Models\Package::withoutGlobalScopes()->find($user->package_id);
        }
        
        if (!$package) {
            return response()->json(['success' => false, 'message' => 'No active package found.'], 400);
        }

        $amount = $package->price * $request->months;
        
        $metadata = [
            'type' => 'renewal',
            'months' => $request->months,
        ];

        if ($user->type === 'hotspot') {
            $metadata['hotspot_package_id'] = $package->id;
        } else {
            $metadata['package_id'] = $package->id;
        }

        $result = $this->paymentService->initiatePayment(
            $user,
            $amount,
            $request->phone,
            'renewal',
            $metadata,
            $request->payment_method
        );

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 500);
    }

    public function checkPaymentStatus($referenceId)
    {
        $payment = DB::table('tenant_payments')
            ->where('checkout_request_id', $referenceId)
            ->orWhere('receipt_number', $referenceId)
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
        
        return response()->json([
            'success' => true,
            'status' => $payment->status,
            'message' => 'Payment is ' . $payment->status
        ]);
    }
}
