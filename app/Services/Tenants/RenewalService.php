<?php

namespace App\Services\Tenants;

use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\PackageRenewal;
use App\Models\Package;
use App\Models\Tenants\TenantHotspot;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RenewalService
{
    /**
     * Process a payment for a user, extending their package and updating balance.
     *
     * @param NetworkUser $user
     * @param float $amountPaid
     * @return array
     */
    public function processPayment(NetworkUser $user, float $amountPaid)
    {
        try {
            // Resolve the current package
            $package = $user->package ?: $user->hotspotPackage;
            
            if (!$package) {
                return [
                    'success' => false,
                    'message' => 'User does not have an assigned package to renew.'
                ];
            }

            $price = (float) $package->price;
            if ($price <= 0) {
                return [
                    'success' => false,
                    'message' => 'Package has an invalid price (0 or less).'
                ];
            }

            // Combine payment with existing wallet balance
            $totalFunds = (float) $user->wallet_balance + $amountPaid;
            
            // Calculate how many cycles we can afford
            $cycles = (int) floor($totalFunds / $price);
            $remainder = $totalFunds - ($cycles * $price);

            if ($cycles === 0) {
                // Not enough for even one renewal, just add to balance
                $user->wallet_balance = $remainder;
                $user->save();

                return [
                    'success' => true,
                    'message' => "Amount (KES $amountPaid) added to wallet. Total balance: KES " . number_format($remainder, 2),
                    'cycles_activated' => 0,
                    'new_balance' => $remainder
                ];
            }

            // Start extending expiry
            // Base date is now or current expiry if it's in the future
            $baseDate = ($user->expires_at && $user->expires_at->isFuture()) ? $user->expires_at : Carbon::now();

            $activatedRenewals = [];

            for ($i = 0; $i < $cycles; $i++) {
                $startAt = $baseDate->copy();
                $endAt = $this->calculateExpiry($package, $startAt);

                $renewal = PackageRenewal::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'amount_paid' => $price,
                    'started_at' => $startAt,
                    'expires_at' => $endAt,
                    'status' => 'active',
                ]);

                $activatedRenewals[] = $renewal;
                $baseDate = $endAt; // Next cycle starts where this one ends
            }

            // Update user
            $user->expires_at = $baseDate;
            $user->wallet_balance = $remainder;
            $user->status = 'active';
            $user->save();

            Log::info('Package renewals processed', [
                'user_id' => $user->id,
                'cycles' => $cycles,
                'new_expiry' => $baseDate->toDateTimeString(),
                'new_balance' => $remainder
            ]);

            return [
                'success' => true,
                'message' => "Successfully renewed " . ($cycles > 1 ? "$cycles cycles" : "package") . ". New expiry: " . $baseDate->format('d M Y H:i'),
                'cycles_activated' => $cycles,
                'new_balance' => $remainder,
                'renewals' => $activatedRenewals
            ];

        } catch (\Exception $e) {
            Log::error('RenewalService Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while processing renewals: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate expiry date based on package duration.
     */
    protected function calculateExpiry($package, Carbon $baseDate)
    {
        $value = (int) ($package->duration_value ?? $package->duration ?? 1);
        $unit = $package->duration_unit ?? 'days';

        return match ($unit) {
            'minutes' => $baseDate->copy()->addMinutes($value),
            'hours'   => $baseDate->copy()->addHours($value),
            'days'    => $baseDate->copy()->addDays($value),
            'weeks'   => $baseDate->copy()->addWeeks($value),
            'months'  => $baseDate->copy()->addMonths($value),
            default   => $baseDate->copy()->addDays($value),
        };
    }
}
