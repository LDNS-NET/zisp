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
        
        $currentPackageId = $user->package_id ?? $user->hotspot_package_id;
        
        $packages = \App\Models\Package::where('id', '!=', $currentPackageId)
            ->where('type', $user->type)
            ->get();

        $gateways = \App\Models\TenantPaymentGateway::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->pluck('provider')
            ->toArray();

        if (($user->tenant->country_code ?? 'KE') === 'KE' && !in_array('mpesa', $gateways)) {
            $gateways[] = 'mpesa';
        }

        return Inertia::render('Customer/Upgrade', [
            'user' => $user,
            'currentPackage' => $user->type === 'hotspot' ? $user->hotspotPackage : $user->package,
            'packages' => $packages,
            'paymentMethods' => array_values(array_unique($gateways)),
            'country' => $user->tenant->country_code ?? 'KE',
            'currency' => $user->tenant->currency ?? 'KES',
        ]);
    }

    public function initiatePayment(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'payment_method' => 'required|string|in:momo,mpesa',
        ]);

        $user = Auth::guard('customer')->user();
        $newPackage = \App\Models\Package::findOrFail($request->package_id);
        
        $amount = $newPackage->price;
        
        $result = $this->paymentService->initiatePayment(
            $user,
            $amount,
            $request->phone,
            'upgrade',
            ['type' => 'upgrade', 'package_id' => $newPackage->id],
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
