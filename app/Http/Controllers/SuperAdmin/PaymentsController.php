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
use App\Models\SuperAdminActivity;


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
        
        SuperAdminActivity::log(
            'payment.disbursed',
            "Triggered manual disbursement for payment #{$payment->id} ({$payment->currency} {$payment->amount})",
            $payment,
            [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'tenant_id' => $tenant->id,
            ]
        );

        return back()->with('success', 'Manual disbursement triggered successfully.');
    }

    public function destroy($id)
    {
        $payment = TenantPayment::withoutGlobalScopes()->findOrFail($id);
        $paymentData = [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'phone' => $payment->phone,
        ];
        
        $payment->delete();
        
        SuperAdminActivity::log(
            'payment.deleted',
            "Deleted payment #{$paymentData['id']} ({$paymentData['currency']} {$paymentData['amount']})",
            null,
            $paymentData
        );

        return redirect()->route('superadmin.payments.index')->with('success', 'Payment deleted successfully.');
    }

    /**
     * Export payments to CSV
     */
    public function export(Request $request)
    {
        $query = TenantPayment::withoutGlobalScopes()->with(['tenant.paymentGateways', 'user']);

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('checkout_request_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        $payments = $query->orderBy('created_at', 'desc')->get();

        // Create CSV
        $filename = 'payments_export_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($payments) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID',
                'Receipt Number',
                'Phone',
                'Amount',
                'Currency',
                'Payment Method',
                'Status',
                'Disbursement Status',
                'Tenant Name',
                'User Name',
                'Paid At',
                'Created At',
            ]);

            // CSV Data
            foreach ($payments as $payment) {
                fputcsv($file, [
                    $payment->id,
                    $payment->receipt_number ?? '',
                    $payment->phone,
                    $payment->amount,
                    $payment->currency,
                    $payment->payment_method,
                    $payment->status,
                    $payment->disbursement_status ?? '',
                    $payment->tenant?->name ?? '',
                    $payment->user?->name ?? '',
                    $payment->paid_at?->format('Y-m-d H:i:s') ?? '',
                    $payment->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        SuperAdminActivity::log(
            'payments.exported',
            "Exported {$payments->count()} payments to CSV",
            null,
            ['count' => $payments->count(), 'filters' => $request->only(['search', 'status', 'method'])]
        );

        return response()->stream($callback, 200, $headers);
    }
}
