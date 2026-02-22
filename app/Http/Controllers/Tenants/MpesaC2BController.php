<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Models\Package;
use App\Models\Tenants\TenantHotspot;
use App\Services\MpesaService;
use App\Services\Tenants\RenewalService;
use App\Jobs\ProcessDisbursementJob;
use Illuminate\Support\Facades\Log;

class MpesaC2BController extends Controller
{
    /**
     * M-Pesa C2B Validation Endpoint
     */
    public function validation(Request $request, MpesaService $mpesa)
    {
        // Security: Validate source IP
        if (!$mpesa->isValidSourceIp($request->ip())) {
            Log::warning('M-Pesa C2B Validation: Unauthorized IP attempt', [
                'ip' => $request->ip(),
                'data' => $request->all()
            ]);
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Rejected: Unauthorized source'
            ], 403);
        }

        $data = $mpesa->parseC2B($request->all());
        $accountNumber = $data['bill_ref_number'];

        Log::info('M-Pesa C2B Validation received', [
            'account_number' => $accountNumber,
            'amount' => $data['trans_amount'],
            'phone' => $data['msisdn'],
            'trans_id' => $data['trans_id'],
            'ip' => $request->ip()
        ]);

        $user = NetworkUser::withoutGlobalScopes()
            ->where('account_number', $accountNumber)
            ->first();

        if ($user) {
            Log::info('M-Pesa C2B Validation: User found', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'account' => $accountNumber,
                'username' => $user->username,
                'amount' => $data['trans_amount']
            ]);
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted'
            ]);
        }

        Log::warning('M-Pesa C2B Validation: User not found', [
            'account' => $accountNumber,
            'amount' => $data['trans_amount'],
            'trans_id' => $data['trans_id']
        ]);
        return response()->json([
            'ResultCode' => 1,
            'ResultDesc' => 'Rejected'
        ]);
    }

    /**
     * M-Pesa C2B Confirmation Endpoint
     */
    public function confirmation(Request $request, MpesaService $mpesa)
    {
        // Security: Validate source IP
        if (!$mpesa->isValidSourceIp($request->ip())) {
            Log::warning('M-Pesa C2B Confirmation: Unauthorized IP attempt', [
                'ip' => $request->ip(),
                'data' => $request->all()
            ]);
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Rejected: Unauthorized source'
            ], 403);
        }

        $data = $mpesa->parseC2B($request->all());
        $accountNumber = $data['bill_ref_number'];

        Log::info('M-Pesa C2B Confirmation received', [
            'trans_id' => $data['trans_id'],
            'account_number' => $accountNumber,
            'amount' => $data['trans_amount'],
            'phone' => $data['msisdn'],
            'business_shortcode' => $data['business_shortcode'],
            'ip' => $request->ip()
        ]);

        $user = NetworkUser::withoutGlobalScopes()
            ->where('account_number', $accountNumber)
            ->first();

        if (!$user) {
            Log::error('M-Pesa C2B Confirmation: User not found for account', [
                'account' => $accountNumber,
                'amount' => $data['trans_amount'],
                'trans_id' => $data['trans_id'],
                'phone' => $data['msisdn'],
                'business_shortcode' => $data['business_shortcode']
            ]);
            // Still return success to M-Pesa to prevent retries of invalid data
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        }

        Log::info('M-Pesa C2B Confirmation: User identified', [
            'user_id' => $user->id,
            'username' => $user->username,
            'tenant_id' => $user->tenant_id,
            'account' => $accountNumber
        ]);

        try {
            // Check if payment already exists
            $existingPayment = TenantPayment::withoutGlobalScopes()
                ->where('receipt_number', $data['trans_id'])
                ->first();

            if ($existingPayment) {
                Log::info('M-Pesa C2B Confirmation: Payment already processed', [
                    'trans_id' => $data['trans_id'],
                    'payment_id' => $existingPayment->id,
                    'tenant_id' => $existingPayment->tenant_id
                ]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
            }

            // Resolve package
            $packageId = $user->package_id;
            $hotspotPackageId = $user->hotspot_package_id;

            // Create payment record
            $payment = TenantPayment::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'phone' => $user->phone, // Use profile phone (Safaricom hashes MSISDN in production)
                'amount' => $data['trans_amount'],
                'currency' => 'KES',
                'payment_method' => 'mpesa_c2b',
                'receipt_number' => $data['trans_id'],
                'status' => 'paid',
                'checked' => true,
                'paid_at' => now(),
                'package_id' => $packageId,
                'hotspot_package_id' => $hotspotPackageId,
                'disbursement_status' => 'pending', // Will be updated if using custom API
                'response' => $data['raw_data']
            ]);

            Log::info('M-Pesa C2B Confirmation: Payment record created', [
                'payment_id' => $payment->id,
                'tenant_id' => $payment->tenant_id,
                'user_id' => $payment->user_id,
                'amount' => $payment->amount,
                'receipt' => $payment->receipt_number
            ]);

            // Update user expiry and handle balance/renewals
            $this->extendUserExpiry($user, $data['trans_amount'], app(RenewalService::class));

            // Trigger disbursement if using default API
            $this->handleDisbursement($payment);

            Log::info('M-Pesa C2B Confirmation: Payment processed successfully', [
                'payment_id' => $payment->id,
                'tenant_id' => $payment->tenant_id,
                'user_id' => $user->id,
                'username' => $user->username,
                'amount' => $payment->amount,
                'expires_at' => $user->fresh()->expires_at?->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('M-Pesa C2B Confirmation: Error processing payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * Extend user expiry using RenewalService
     */
    private function extendUserExpiry(NetworkUser $user, float $amount, RenewalService $renewalService)
    {
        // Use the RenewalService to handle cycles and wallet balance
        $result = $renewalService->processPayment($user, $amount);

        if (!$result['success']) {
            Log::warning('C2B: RenewalService could not process payment', [
                'user_id' => $user->id,
                'amount' => $amount,
                'message' => $result['message']
            ]);
            // Fallback: stay active but no additional extension if service fails
        }

        // Unsuspend on MikroTik
        try {
            $tenantMikrotik = \App\Models\Tenants\TenantMikrotik::where('tenant_id', $user->tenant_id)->first();
            if ($tenantMikrotik) {
                $mikrotik = new \App\Services\MikrotikService($tenantMikrotik);
                $mikrotik->unsuspendUser($user->type, $user->mikrotik_id ?? $user->username);
            }
        } catch (\Exception $e) {
            Log::error('C2B: Failed to unsuspend user on MikroTik', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Handle automatic disbursement if using default API
     */
    private function handleDisbursement(TenantPayment $payment)
    {
        $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $payment->tenant_id)
            ->where('provider', 'mpesa')
            ->where('use_own_api', true)
            ->where('is_active', true)
            ->first();

        if ($gateway) {
            // Tenant uses their own API, no disbursement needed
            $status = ($gateway->mpesa_env === 'sandbox') ? 'testing' : 'completed';
            $payment->update(['disbursement_status' => $status]);
        } else {
            // Use default API, check if sandbox
            if (config('mpesa.environment') === 'sandbox') {
                $payment->update(['disbursement_status' => 'testing']);
            } else {
                ProcessDisbursementJob::dispatch($payment);
            }
        }
    }
}
