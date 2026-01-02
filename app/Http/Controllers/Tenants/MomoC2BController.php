<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Models\Package;
use App\Models\Tenants\TenantHotspot;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MomoC2BController extends Controller
{
    /**
     * Handle MTN MoMo C2B Callback
     * 
     * Note: MTN MoMo typically uses the 'externalId' field for the account number
     * in collection requests. For direct payments (if supported via their C2B/Widget flow),
     * we need to map their notification structure.
     */
    public function callback(Request $request)
    {
        $data = $request->all();
        
        Log::info('MoMo C2B Callback received', ['data' => $data]);

        // Standard MoMo notification fields
        $externalId = $data['externalId'] ?? null;
        $amount = $data['amount'] ?? 0;
        $currency = $data['currency'] ?? 'UGX';
        $transactionId = $data['financialTransactionId'] ?? null;
        $status = strtolower($data['status'] ?? '');
        $payer = $data['payer']['partyId'] ?? null;

        if (!$externalId || $status !== 'successful') {
            Log::warning('MoMo C2B: Invalid callback data or unsuccessful status', [
                'externalId' => $externalId,
                'status' => $status
            ]);
            return response()->json(['status' => 'ignored'], 200);
        }

        // Find user by account number
        $user = NetworkUser::withoutGlobalScopes()
            ->where('account_number', $externalId)
            ->first();

        if (!$user) {
            Log::error('MoMo C2B: User not found for account', ['account' => $externalId]);
            return response()->json(['status' => 'user_not_found'], 200);
        }

        try {
            // Check if payment already exists
            $existingPayment = TenantPayment::withoutGlobalScopes()
                ->where('momo_transaction_id', $transactionId)
                ->first();

            if ($existingPayment) {
                Log::info('MoMo C2B: Payment already processed', ['transaction_id' => $transactionId]);
                return response()->json(['status' => 'already_processed'], 200);
            }

            // Create payment record
            $payment = TenantPayment::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'phone' => $payer,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => 'momo_c2b',
                'momo_transaction_id' => $transactionId,
                'status' => 'paid',
                'checked' => true,
                'paid_at' => now(),
                'package_id' => $user->package_id,
                'hotspot_package_id' => $user->hotspot_package_id,
                'response' => $data
            ]);

            // Update user expiry
            $this->extendUserExpiry($user);

            Log::info('MoMo C2B: Payment processed successfully', [
                'payment_id' => $payment->id,
                'user_id' => $user->id
            ]);

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('MoMo C2B: Error processing payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Extend user expiry based on their package
     * (Shared logic with MpesaC2BController)
     */
    private function extendUserExpiry(NetworkUser $user)
    {
        if ($user->hotspot_package_id) {
            $package = TenantHotspot::withoutGlobalScopes()->find($user->hotspot_package_id);
        } else {
            $package = Package::withoutGlobalScopes()->find($user->package_id);
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

        $user->status = 'active';
        $user->save();

        // Unsuspend on MikroTik
        try {
            $tenantMikrotik = TenantMikrotik::withoutGlobalScopes()
                ->where('tenant_id', $user->tenant_id)
                ->first();
                
            if ($tenantMikrotik) {
                $mikrotik = new MikrotikService($tenantMikrotik);
                $mikrotik->unsuspendUser($user->type, $user->mikrotik_id ?? $user->username);
            }
        } catch (\Exception $e) {
            Log::error('MoMo C2B: Failed to unsuspend user on MikroTik', [
                'user_id' => $user->id, 
                'error' => $e->getMessage()
            ]);
        }
    }
}
