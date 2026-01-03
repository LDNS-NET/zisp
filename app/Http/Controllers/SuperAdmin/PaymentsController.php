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
        $query = TenantPayment::withoutGlobalScopes()->with('tenant');

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
        $payment = TenantPayment::withoutGlobalScopes()->with('tenant')->findOrFail($id);

        return Inertia::render('SuperAdmin/Payments/Show', [
            'payment' => $payment,
        ]);
    }

    public function destroy($id)
    {
        $payment = TenantPayment::withoutGlobalScopes()->findOrFail($id);
        $payment->delete();

        return redirect()->route('superadmin.payments.index')->with('success', 'Payment deleted successfully.');
    }
}
