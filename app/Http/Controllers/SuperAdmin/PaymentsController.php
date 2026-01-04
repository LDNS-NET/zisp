<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenant;
use App\Models\SuperAdmin\Payments;
use App\Models\Tenants\NetworkUser;


class PaymentsController extends Controller
{
    public function index(Request $request) {
        $query = TenantPayment::withoutGlobalScopes()->with(['tenant.paymentGateways', 'user']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('checkout_request_id', 'like', "%{$search}%");
            });
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Method
        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('SuperAdmin/Payments/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status', 'method']),
        ]);
    }

    public function show($id) {
        $payment = TenantPayment::withoutGlobalScopes()->with(['tenant.paymentGateways', 'user'])->findOrFail($id);

        return Inertia::render('SuperAdmin/Payments/Show', [
            'payment' => $payment,
        ]);
    }

    public function disburse($id)
    {
        $payment = TenantPayment::withoutGlobalScopes()->with('tenant.paymentGateways')->findOrFail($id);

        // Eligibility checks
        if ($payment->status !== 'paid') {
            return back()->with('error', 'Only paid transactions can be disbursed.');
        }

        if ($payment->payment_method !== 'mpesa') {
            return back()->with('error', 'Only M-Pesa transactions are eligible for system disbursement.');
        }

        if (!in_array($payment->disbursement_status, ['pending', 'failed'])) {
            return back()->with('error', 'Disbursement is already ' . ($payment->disbursement_status ?: 'processed') . '.');
        }

        $tenant = $payment->tenant;
        if (!$tenant || $tenant->country_code !== 'KE') {
            return back()->with('error', 'System disbursement is only available for Kenyan tenants.');
        }

        $gateway = $tenant->paymentGateways()
            ->where('provider', 'mpesa')
            ->where('is_active', true)
            ->first();

        if (!$gateway) {
            return back()->with('error', 'No active M-Pesa gateway found for this tenant.');
        }

        if ($gateway->use_own_api) {
            return back()->with('error', 'Tenant uses their own M-Pesa API. System disbursement is not required.');
        }

        // Trigger disbursement
        \App\Jobs\ProcessDisbursementJob::dispatch($payment);

        return back()->with('success', 'Manual disbursement triggered successfully.');
    }

    public function destroy($id)
    {
        $payment = TenantPayment::withoutGlobalScopes()->findOrFail($id);
        $payment->delete();

        return redirect()->route('superadmin.payments.index')->with('success', 'Payment deleted successfully.');
    }
}
