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
        $tenant = tenant() ?: auth()->user()->tenant;
        
        if (!$tenant) {
            return redirect()->route('dashboard')->with('error', 'Tenant context not found.');
        }

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
        $tenant = tenant() ?: auth()->user()->tenant;

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context not found.',
            ], 400);
        }

        $bill = $this->subscriptionService->calculateMonthlyBill($tenant);
        
        // Get Paystack keys for this tenant or use system defaults
        $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $tenant->id)
            ->where('provider', 'paystack')
            ->where('is_active', true)
            ->first();

        $paystackSecret = $gateway ? $gateway->paystack_secret_key : config('services.paystack.secret_key');

        if (!$paystackSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Paystack is not configured. Please contact support or check your payment settings.',
            ], 400);
        }

        $paystack = new PaystackService([
            'secret_key' => $paystackSecret,
            'public_key' => $gateway?->paystack_public_key ?? config('services.paystack.public_key'),
        ]);

        $reference = 'REN-' . strtoupper(uniqid()) . '-' . $tenant->id;

        $response = $paystack->initializeTransaction(
            $tenant->email ?: 'billing@' . $tenant->subdomain . '.com',
            $bill['final_amount'],
            $reference,
            $bill['currency'] ?? 'KES',
            [
                'tenant_id' => $tenant->id,
                'type' => 'renewal',
                'callback_url' => route('subscription.callback'),
            ]
        );

        if ($response && $response['success']) {
            TenantPayment::create([
                'tenant_id' => $tenant->id,
                'phone' => $tenant->phone ?: '0000000000',
                'amount' => $bill['final_amount'],
                'currency' => $bill['currency'],
                'payment_method' => 'paystack',
                'receipt_number' => $reference,
                'status' => 'pending',
                'paystack_reference' => $reference,
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

        if (!$paystackSecret) {
            return redirect()->route('subscription.renew')->with('error', 'Paystack configuration missing.');
        }

        $paystack = new PaystackService([
            'secret_key' => $paystackSecret,
            'public_key' => $gateway?->paystack_public_key ?? config('services.paystack.public_key'),
        ]);

        $response = $paystack->verifyTransaction($reference);

        if ($response && $response['success'] && $response['status'] === 'success') {
            // Update payment record
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'checked' => true,
                'response' => $response,
            ]);

            // Process renewal
            $this->subscriptionService->processRenewal($tenant, $payment->amount);

            return redirect()->route('dashboard')->with('success', 'Subscription renewed successfully.');
        }

        return redirect()->route('subscription.renew')->with('error', 'Payment verification failed.');
    }
}
