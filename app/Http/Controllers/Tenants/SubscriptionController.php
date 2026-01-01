<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Tenants\TenantPayment;
use App\Services\SubscriptionService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Show the renewal page.
     */
    public function showRenewal()
    {
        $tenant = tenant();
        $bill = $this->subscriptionService->calculateMonthlyBill($tenant);
        $subscription = $tenant->subscription;

        return Inertia::render('Subscription/Renew', [
            'bill' => $bill,
            'subscription' => $subscription,
            'tenant' => $tenant,
        ]);
    }

    /**
     * Initialize Paystack payment for renewal.
     */
    public function initializePayment(Request $request)
    {
        $tenant = tenant();
        $bill = $this->subscriptionService->calculateMonthlyBill($tenant);
        
        // Get Paystack keys for this tenant or use system defaults
        $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $tenant->id)
            ->where('provider', 'paystack')
            ->where('is_active', true)
            ->first();

        $paystackSecret = $gateway ? $gateway->paystack_secret_key : config('services.paystack.secret_key');
        $paystack = new PaystackService($paystackSecret);

        $reference = 'REN-' . strtoupper(uniqid()) . '-' . $tenant->id;

        $data = [
            'amount' => $bill['final_amount'] * 100, // Paystack expects amount in kobo/cents
            'email' => $tenant->email ?: 'billing@' . $tenant->subdomain . '.com',
            'reference' => $reference,
            'callback_url' => route('subscription.callback'),
            'metadata' => [
                'tenant_id' => $tenant->id,
                'type' => 'renewal',
            ],
        ];

        $response = $paystack->initializeTransaction($data);

        if ($response && $response['status']) {
            // Create a pending payment record
            TenantPayment::create([
                'tenant_id' => $tenant->id,
                'amount' => $bill['final_amount'],
                'currency' => $bill['currency'],
                'payment_method' => 'paystack',
                'receipt_number' => $reference,
                'status' => 'pending',
                'paystack_reference' => $reference,
            ]);

            return response()->json([
                'success' => true,
                'authorization_url' => $response['data']['authorization_url'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to initialize payment with Paystack.',
        ], 500);
    }

    /**
     * Handle Paystack callback.
     */
    public function handleCallback(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            return redirect()->route('dashboard')->with('error', 'No reference provided.');
        }

        $payment = TenantPayment::withoutGlobalScopes()
            ->where('paystack_reference', $reference)
            ->first();

        if (!$payment) {
            return redirect()->route('dashboard')->with('error', 'Payment record not found.');
        }

        $tenant = Tenant::find($payment->tenant_id);
        
        $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $tenant->id)
            ->where('provider', 'paystack')
            ->where('is_active', true)
            ->first();

        $paystackSecret = $gateway ? $gateway->paystack_secret_key : config('services.paystack.secret_key');
        $paystack = new PaystackService($paystackSecret);

        $response = $paystack->verifyTransaction($reference);

        if ($response && $response['status'] && $response['data']['status'] === 'success') {
            // Update payment record
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'checked' => true,
                'response' => $response['data'],
            ]);

            // Process renewal
            $this->subscriptionService->processRenewal($tenant, $payment->amount);

            return redirect()->route('dashboard')->with('success', 'Subscription renewed successfully.');
        }

        return redirect()->route('subscription.renew')->with('error', 'Payment verification failed.');
    }
}
