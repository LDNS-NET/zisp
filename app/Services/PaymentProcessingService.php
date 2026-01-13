<?php

namespace App\Services;

use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Models\Package;
use App\Models\Tenants\TenantHotspot;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentProcessingService
{
    /**
     * Process a successful payment.
     */
    public function processSuccess(TenantPayment $payment)
    {
        try {
            Log::info('Processing successful payment', ['payment_id' => $payment->id, 'method' => $payment->payment_method]);

            $isHotspot = (bool)$payment->hotspot_package_id;
            $package = $this->resolvePackage($payment);

            if (!$package) {
                Log::error('Package not found for payment', ['payment_id' => $payment->id]);
                return false;
            }

            $user = $this->resolveUser($payment, $isHotspot);

            if ($user) {
                $this->updateExistingUser($user, $package, $payment, $isHotspot);
            } elseif ($isHotspot) {
                $this->createNewHotspotUser($payment, $package);
            } else {
                Log::warning('PPPoE user not found for payment', ['phone' => $payment->phone, 'payment_id' => $payment->id]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    protected function resolvePackage($payment)
    {
        if ($payment->hotspot_package_id) {
            return TenantHotspot::withoutGlobalScopes()
                ->where('id', $payment->hotspot_package_id)
                ->where('tenant_id', $payment->tenant_id)
                ->first();
        }
        return Package::withoutGlobalScopes()->find($payment->package_id);
    }

    protected function resolveUser($payment, $isHotspot)
    {
        $query = NetworkUser::withoutGlobalScopes()->where('tenant_id', $payment->tenant_id);

        if ($payment->user_id) {
            return $query->where('id', $payment->user_id)->first();
        }

        return $query->where('phone', $payment->phone)
            ->where('type', $isHotspot ? 'hotspot' : 'pppoe')
            ->first();
    }

    protected function updateExistingUser($user, $package, $payment, $isHotspot)
    {
        $upgradeType = $payment->response['metadata']['upgrade_type'] ?? null;
        $isUpgrade = ($payment->response['metadata']['type'] ?? '') === 'upgrade';

        if ($isUpgrade && $upgradeType === 'immediate') {
            // Immediate upgrade: Switch package, keep expiry
            if ($isHotspot) {
                $user->hotspot_package_id = $package->id;
                $user->package_id = null;
            } else {
                $user->package_id = $package->id;
                $user->hotspot_package_id = null;
            }
            // No change to expires_at (they only paid the difference for the remainder)
        } elseif ($isUpgrade && $upgradeType === 'after_expiry') {
            // After expiry upgrade: Store pending, extend expiry
            $originalExpiry = ($user->expires_at && $user->expires_at->isFuture()) ? $user->expires_at : now();
            
            if ($isHotspot) {
                $user->pending_hotspot_package_id = $package->id;
                $user->pending_package_id = null;
            } else {
                $user->pending_package_id = $package->id;
                $user->pending_hotspot_package_id = null;
            }
            
            $user->pending_package_activation_at = $originalExpiry;
            $user->expires_at = $this->calculateExpiry($package, $originalExpiry);
        } else {
            // Regular renewal
            if ($isHotspot) {
                $user->hotspot_package_id = $package->id;
                $user->package_id = null;
            } else {
                $user->package_id = $package->id;
                $user->hotspot_package_id = null;
            }

            $baseDate = ($user->expires_at && $user->expires_at->isFuture()) ? $user->expires_at : now();
            
            // Handle multi-month renewals for PPPoE
            $months = 1;
            if (!$isHotspot && isset($payment->response['metadata']['months'])) {
                $months = (int)$payment->response['metadata']['months'];
            }

            $user->expires_at = $this->calculateExpiry($package, $baseDate, $months);
        }

        $user->status = 'active';
        $user->save();

        if (!$payment->user_id) {
            $payment->update(['user_id' => $user->id]);
        }

        $this->unsuspendOnMikrotik($user);
    }

    protected function createNewHotspotUser($payment, $package)
    {
        $username = NetworkUser::generateHotspotUsername($payment->tenant_id);
        $password = Str::random(8);

        $user = NetworkUser::create([
            'tenant_id' => $payment->tenant_id,
            'username' => $username,
            'password' => $password,
            'phone' => $payment->phone,
            'type' => 'hotspot',
            'hotspot_package_id' => $package->id,
            'expires_at' => $this->calculateExpiry($package),
            'registered_at' => now(),
            'status' => 'active',
        ]);

        $payment->update(['user_id' => $user->id]);
        
        $this->unsuspendOnMikrotik($user);

        Log::info('New hotspot user created', ['username' => $username]);
    }

    protected function calculateExpiry($package, $baseDate = null, $multiplier = 1)
    {
        $value = ($package->duration_value ?? $package->duration ?? 1) * $multiplier;
        $unit = $package->duration_unit ?? 'days';
        $base = $baseDate ?: now();

        return match ($unit) {
            'minutes' => $base->copy()->addMinutes($value),
            'hours'   => $base->copy()->addHours($value),
            'days'    => $base->copy()->addDays($value),
            'weeks'   => $base->copy()->addWeeks($value),
            'months'  => $base->copy()->addMonths($value),
            default   => $base->copy()->addDays($value),
        };
    }

    protected function unsuspendOnMikrotik(NetworkUser $user)
    {
        try {
            $tenantMikrotik = \App\Models\Tenants\TenantMikrotik::where('tenant_id', $user->tenant_id)->first();
            if ($tenantMikrotik) {
                $mikrotik = new \App\Services\MikrotikService($tenantMikrotik);
                $mikrotik->unsuspendUser($user->type, $user->mikrotik_id ?? $user->username);
            }
        } catch (\Exception $e) {
            Log::error('Mikrotik unsuspend failed', ['user' => $user->username, 'error' => $e->getMessage()]);
        }
    }
}
