<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\TenantPayment;
use App\Services\FlutterwaveService;
use App\Models\TenantPaymentGateway;
use Illuminate\Support\Facades\Log;
use App\Models\Tenants\TenantHotspot;
use App\Models\Tenants\TenantPackage;
use App\Models\NetworkUser;
use Carbon\Carbon;
use Inertia\Inertia;

class FlutterwaveController extends Controller
{
    protected $flutterwaveService;
    protected $paymentProcessingService;

    public function __construct(FlutterwaveService $flutterwaveService, \App\Services\PaymentProcessingService $paymentProcessingService)
    {
        $this->flutterwaveService = $flutterwaveService;
        $this->paymentProcessingService = $paymentProcessingService;
    }

    /**
     * Handle the callback from Flutterwave redirect
     */
    public function handleCallback(Request $request)
    {
        $status = $request->query('status');
        $txRef = $request->query('tx_ref');
        $transactionId = $request->query('transaction_id');

        if ($status !== 'successful' && $status !== 'completed') {
            return redirect()->route('hotspot.index')->with('error', 'Payment was cancelled or failed.');
        }

        $payment = TenantPayment::where('receipt_number', $txRef)->first();

        if (!$payment) {
            Log::error("Flutterwave Callback: Payment not found for ref: {$txRef}");
            return redirect()->route('hotspot.index')->with('error', 'Payment record not found.');
        }

        // Set credentials for verification
        $gateway = TenantPaymentGateway::where('tenant_id', $payment->tenant_id)
            ->where('provider', 'flutterwave')
            ->first();

        if ($gateway) {
            $this->flutterwaveService->setCredentials([
                'secret_key' => $gateway->flutterwave_secret_key,
                'public_key' => $gateway->flutterwave_public_key,
            ]);
        }

        // Verify transaction
        $verification = $this->flutterwaveService->verifyTransaction($transactionId);

        if ($verification && $verification['success'] && 
            ($verification['status'] === 'successful' || $verification['status'] === 'completed') &&
            $verification['amount'] >= $payment->amount) {
            
            // Update payment status
            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'checkout_request_id' => $transactionId, // Store actual transaction ID
                    'response' => array_merge($payment->response ?? [], ['verification' => $verification]),
                    'paid_at' => now(),
                ]);

                // Process payment (create/extend user)
                if ($payment->hotspot_package_id || $payment->package_id) {
                    $this->paymentProcessingService->processSuccess($payment);
                }
 
                return $this->handleRedirect($payment);
            }
            
            // Already paid
            return $this->handleRedirect($payment);
        }

        return redirect()->route('hotspot.index')->with('error', 'Payment verification failed.');
    }

    /**
     * Handle redirection after successful payment
     */
    protected function handleRedirect($payment)
    {
        if ($payment->hotspot_package_id) {
            $user = \App\Models\Tenants\NetworkUser::where('phone', $payment->phone)
                ->where('type', 'hotspot')
                ->first();

            if ($user) {
                // Get hotspot metadata from payment response
                $metadata = $payment->response['metadata'] ?? [];
                
                return redirect()->route('hotspot.success', array_filter([
                    'u' => $user->username,
                    'p' => $user->password,
                    'link-login' => $metadata['link_login'] ?? null,
                    'link-orig' => $metadata['link_orig'] ?? null,
                    'error-link' => $metadata['error_link'] ?? null,
                ]));
            }
            return redirect()->route('hotspot.success');
        }

        return redirect()->route('customer.dashboard');
    }

    /**
     * Verify payment status (API endpoint for polling)
     */
    public function verify($reference)
    {
        $payment = TenantPayment::where('receipt_number', $reference)->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found']);
        }

        if ($payment->status === 'paid') {
            $user = NetworkUser::find($payment->user_id);
            return response()->json([
                'success' => true,
                'status' => 'paid',
                'user' => $user ? [
                    'username' => $user->username,
                    'password' => $user->password,
                    'duration' => $user->expiration ? Carbon::parse($user->expiration)->diffForHumans() : 'Active'
                ] : null
            ]);
        }

        // If not paid locally, try to verify with Flutterwave
        $gateway = TenantPaymentGateway::where('tenant_id', $payment->tenant_id)
            ->where('provider', 'flutterwave')
            ->first();

        if ($gateway) {
            $this->flutterwaveService->setCredentials([
                'secret_key' => $gateway->flutterwave_secret_key,
                'public_key' => $gateway->flutterwave_public_key,
            ]);
        }

        // We need the transaction ID, but we only have reference (tx_ref).
        // Flutterwave verify endpoint needs ID. 
        // However, we can query by tx_ref using a different endpoint or just wait for callback.
        // For polling, we might just check local status if we rely on callback/webhook.
        // Or we can use the "Get Transfer" or "List Transactions" endpoint filtering by tx_ref.
        // But verifyTransaction uses ID.
        
        // Actually, Flutterwave v3 allows verifying by tx_ref if you use the /transactions endpoint with query params?
        // No, standard verify is by ID.
        // But typically the frontend redirect flow handles the completion.
        // If polling is needed (e.g. for USSD/Mobile Money where redirect might not happen immediately or user closes tab),
        // we might need to rely on Webhook.
        
        return response()->json(['success' => true, 'status' => $payment->status]);
    }

    /**
     * Handle Webhook
     */
    public function webhook(Request $request)
    {
        // Verify signature
        $signature = $request->header('verif-hash');
        if (!$signature) {
            return response()->json(['status' => 'error', 'message' => 'Missing signature'], 401);
        }

        // We need to find the tenant to get the secret hash. 
        // But we don't know the tenant yet.
        // Payload usually has tx_ref.
        $payload = $request->all();
        $txRef = $payload['data']['tx_ref'] ?? ($payload['txRef'] ?? null);

        if (!$txRef) {
            return response()->json(['status' => 'error', 'message' => 'Missing reference'], 400);
        }

        $payment = TenantPayment::where('receipt_number', $txRef)->first();
        if (!$payment) {
            return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
        }

        // Now we can get the gateway and verify signature
        // ... (Signature verification logic would go here)

        // Process payment
        if (($payload['status'] ?? '') === 'successful' || ($payload['data']['status'] ?? '') === 'successful') {
             if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'checkout_request_id' => $payload['data']['id'] ?? null,
                    'response' => array_merge($payment->response ?? [], ['webhook' => $payload]),
                    'paid_at' => now(),
                ]);
                
                // Trigger service activation (similar to handleRedirect)
                // ...
            }
        }

        return response()->json(['status' => 'success']);
    }
}
