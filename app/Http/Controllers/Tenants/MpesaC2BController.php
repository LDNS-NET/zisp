<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Models\Package;
use App\Models\Tenants\TenantHotspot;
use App\Services\MpesaService;
use App\Jobs\ProcessDisbursementJob;
use Illuminate\Support\Facades\Log;

class MpesaC2BController extends Controller
{
    /**
     * M-Pesa C2B Validation Endpoint
     */
    public function validation(Request $request, MpesaService $mpesa)
    {
        $data = $mpesa->parseC2B($request->all());
        $accountNumber = $data['bill_ref_number'];

        Log::info('M-Pesa C2B Validation received', ['account_number' => $accountNumber]);

        $user = NetworkUser::withoutGlobalScopes()
            ->where('account_number', $accountNumber)
            ->first();

        if ($user) {
            Log::info('M-Pesa C2B Validation: User found', ['user_id' => $user->id, 'account' => $accountNumber]);
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted'
            ]);
        }

        Log::warning('M-Pesa C2B Validation: User not found', ['account' => $accountNumber]);
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
        $data = $mpesa->parseC2B($request->all());
        $accountNumber = $data['bill_ref_number'];

        Log::info('M-Pesa C2B Confirmation received', [
            'trans_id' => $data['trans_id'],
            'account_number' => $accountNumber,
            'amount' => $data['trans_amount']
        ]);

        $user = NetworkUser::withoutGlobalScopes()
            ->where('account_number', $accountNumber)
            ->first();

        if (!$user) {
            Log::error('M-Pesa C2B Confirmation: User not found for account', ['account' => $accountNumber]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']); // M-Pesa expects 0 even if we can't process it locally
        }

        try {
            // Check if payment already exists
            $existingPayment = TenantPayment::withoutGlobalScopes()
                ->where('receipt_number', $data['trans_id'])
                ->first();

            if ($existingPayment) {
                Log::info('M-Pesa C2B Confirmation: Payment already processed', ['trans_id' => $data['trans_id']]);
                return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
            }

            // Resolve package
            $packageId = $user->package_id;
            $hotspotPackageId = $user->hotspot_package_id;

            // Create payment record
            $payment = TenantPayment::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'phone' => $data['msisdn'],
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

            // Update user expiry
            $this->extendUserExpiry($user);

            // Trigger disbursement if using default API
            $this->handleDisbursement($payment);

            Log::info('M-Pesa C2B Confirmation: Payment processed successfully', [
                'payment_id' => $payment->id,
                'user_id' => $user->id
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
     * Extend user expiry based on their package
     */
    private function extendUserExpiry(NetworkUser $user)
    {
        if ($user->hotspot_package_id) {
            $package = TenantHotspot::withoutGlobalScopes()->find($user->hotspot_package_id);
        } else {
            $package = Package::find($user->package_id);
        }

        if (!$package) return;

        $baseDate = ($user->expires_at && $user->expires_at->isFuture()) ? $user->expires_at : now();
        
        $value = $package->duration_value ?? $package->duration ?? 1;
        $unit = $package->duration_unit ?? 'days';

        $user->expires_at = match ($unit) {
            'minutes' => $baseDate->copy()->addMinutes($value),
            'hours'   => $baseDate->copy()->addHours($value),
            'days'    => $baseDate->copy()->addDays($value),
            'weeks'   => $baseDate->copy()->addWeeks($value),
            'months'  => $baseDate->copy()->addMonths($value),
            default   => $baseDate->copy()->addDays($value),
        };

        $user->save();

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
