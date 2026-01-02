<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Services\PaymentGatewayService;
use Illuminate\Support\Facades\DB;

class UpgradeController extends Controller
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
        
        if ($user->type === 'hotspot') {
            $currentPackage = \App\Models\Tenants\TenantHotspot::withoutGlobalScopes()->find($user->hotspot_package_id);
        } else {
            $currentPackage = \App\Models\Package::withoutGlobalScopes()->find($user->package_id);
        }

        $tenantId = $currentPackage->tenant_id ?? $user->tenant_id;

        if ($user->type === 'hotspot') {
            $packages = \App\Models\Tenants\TenantHotspot::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('id', '!=', $user->hotspot_package_id)
                ->get();
        } else {
            $packages = \App\Models\Package::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('type', $user->type)
                ->where('id', '!=', $user->package_id)
                ->get();
        }

        $gateways = \App\Models\TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->pluck('provider')
            ->toArray();

        if (($user->tenant->country_code ?? 'KE') === 'KE' && !in_array('mpesa', $gateways)) {
            $gateways[] = 'mpesa';
        }

        return Inertia::render('Customer/Upgrade', [
            'user' => $user,
            'currentPackage' => $currentPackage,
            'packages' => $packages,
            'paymentMethods' => array_values(array_unique($gateways)),
            'country' => $user->tenant->country_code ?? 'KE',
            'currency' => $user->tenant->currency ?? 'KES',
        ]);
    }

    public function initiatePayment(Request $request)
    {
        $user = Auth::guard('customer')->user();

        $request->validate([
            'phone' => 'required|string',
            'package_id' => 'required',
            'payment_method' => 'required|string|in:momo,mpesa',
        ]);

        if ($user->type === 'hotspot') {
            $newPackage = \App\Models\Tenants\TenantHotspot::withoutGlobalScopes()->findOrFail($request->package_id);
            $metadata = ['type' => 'upgrade', 'hotspot_package_id' => $newPackage->id];
        } else {
            $newPackage = \App\Models\Package::withoutGlobalScopes()->findOrFail($request->package_id);
            $metadata = ['type' => 'upgrade', 'package_id' => $newPackage->id];
        }
        
        $amount = $newPackage->price;
        
        $result = $this->paymentService->initiatePayment(
            $user,
            $amount,
            $request->phone,
            'upgrade',
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
