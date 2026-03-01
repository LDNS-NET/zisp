<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Tenants\TenantPayment;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsPurchaseController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Initialize Paystack payment for SMS units.
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:50',
        ]);

        $tenant = tenant() ?: auth()->user()->tenant;

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context not found.',
            ], 400);
        }

        // ALWAYS use system-wide Paystack credentials for SMS purchases
        $paystackSecret = config('services.paystack.secret_key');
        $paystackPublic = config('services.paystack.public_key');

        if (!$paystackSecret) {
            return response()->json([
                'success' => false,
                'message' => 'System Paystack is not configured. Please contact support.',
            ], 400);
        }

        $this->paystackService->setCredentials([
            'secret_key' => $paystackSecret,
            'public_key' => $paystackPublic,
        ]);

        $reference = 'SMS-' . strtoupper(uniqid()) . '-' . $tenant->id;

        $response = $this->paystackService->initializeTransaction(
            $tenant->email ?: 'billing@' . $tenant->subdomain . '.com',
            $request->amount,
            $reference,
            'KES',
            [
                'tenant_id' => $tenant->id,
                'type' => 'sms_purchase',
                'callback_url' => route('sms.purchase.callback'),
            ]
        );

        if ($response && $response['success']) {
            TenantPayment::create([
                'tenant_id' => $tenant->id,
                'phone' => $tenant->phone ?: '0000000000',
                'amount' => $request->amount,
                'currency' => 'KES',
                'payment_method' => 'paystack',
                'receipt_number' => $reference,
                'status' => 'pending',
                'paystack_reference' => $reference,
                'response' => ['type' => 'sms_purchase']
            ]);

            return response()->json([
                'success' => true,
                'authorization_url' => $response['authorization_url'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to initialize payment with Paystack.',
        ], 500);
    }

    /**
     * Handle Paystack callback for SMS purchase.
     */
    public function handleCallback(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return redirect()->route('sms.gateway')->with('error', 'No reference provided.');
        }

        $payment = TenantPayment::withoutGlobalScopes()
            ->where('paystack_reference', $reference)
            ->first();

        if (!$payment) {
            return redirect()->route('sms.gateway')->with('error', 'Payment record not found.');
        }

        if ($payment->status === 'paid') {
            return redirect()->route('sms.gateway')->with('success', 'SMS credits purchased successfully.');
        }

        $paystackSecret = config('services.paystack.secret_key');
        if (!$paystackSecret) {
            return redirect()->route('sms.gateway')->with('error', 'System Paystack configuration missing.');
        }

        $this->paystackService->setCredentials(['secret_key' => $paystackSecret]);
        $response = $this->paystackService->verifyTransaction($reference);

        if ($response && $response['success'] && $response['status'] === 'success') {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'checked' => true,
                'response' => array_merge($payment->response ?? [], $response),
            ]);

            // Credit the tenant
            $tenant = Tenant::find($payment->tenant_id);
            if ($tenant) {
                $tenant->increment('sms_balance', $payment->amount);
                Log::info("[SMS] Credited {$payment->amount} KES to tenant {$tenant->id} SMS balance.");
            }

            return redirect()->route('sms.gateway')->with('success', 'SMS credits purchased successfully.');
        }

        return redirect()->route('sms.gateway')->with('error', 'Payment verification failed.');
    }
}
