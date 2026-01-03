<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Services\CountryService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionService
{
    /**
     * Create a new subscription for a tenant.
     */
    public function createSubscription(Tenant $tenant, int $trialDays = 10): TenantSubscription
    {
        return TenantSubscription::createForTenant($tenant, $trialDays);
    }

    /**
     * Check if a tenant's subscription is active.
     */
    public function isSubscriptionActive(Tenant $tenant): bool
    {
        $subscription = TenantSubscription::where('tenant_id', $tenant->id)->first();
        
        if (!$subscription) {
            return false;
        }

        return $subscription->isActive() || $subscription->isOnTrial();
    }

    /**
     * Get subscription status for a tenant.
     */
    public function getSubscriptionStatus(Tenant $tenant): array
    {
        $subscription = TenantSubscription::where('tenant_id', $tenant->id)->first();
        
        if (!$subscription) {
            $subscription = $this->createSubscription($tenant);
        }

        return [
            'subscription' => $subscription,
            'is_active' => $subscription->isActive(),
            'is_on_trial' => $subscription->isOnTrial(),
            'is_expired' => $subscription->isExpired(),
            'is_past_due' => $subscription->isPastDue(),
            'trial_days_remaining' => $subscription->getTrialDaysRemaining(),
            'current_period_days_remaining' => $subscription->getCurrentPeriodDaysRemaining(),
            'next_billing_date' => $subscription->next_billing_date,
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
        ];
    }

    /**
     * Process subscription renewal after successful payment.
     */
    public function processRenewal(Tenant $tenant, float $amount): bool
    {
        try {
            $subscription = TenantSubscription::where('tenant_id', $tenant->id)->first();
            
            if (!$subscription) {
                Log::info('Creating missing subscription record during renewal', [
                    'tenant_id' => $tenant->id,
                ]);
                $subscription = $this->createSubscription($tenant);
            }

            // Start new billing period
            $subscription->startNewPeriod();

            // Sync with User model for all tenant users
            \App\Models\User::where('tenant_id', $tenant->id)->update([
                'subscription_expires_at' => $subscription->current_period_ends_at,
                'is_suspended' => false,
            ]);

            Log::info('Subscription renewed successfully', [
                'tenant_id' => $tenant->id,
                'amount' => $amount,
                'new_period_end' => $subscription->current_period_ends_at,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Subscription renewal failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mark subscription as expired.
     */
    public function markAsExpired(Tenant $tenant): bool
    {
        try {
            $subscription = TenantSubscription::where('tenant_id', $tenant->id)->first();
            
            if ($subscription) {
                $subscription->markAsExpired();
                
                // Sync with User model for all tenant users
                \App\Models\User::where('tenant_id', $tenant->id)->update([
                    'is_suspended' => true,
                ]);

                Log::info('Subscription marked as expired', [
                    'tenant_id' => $tenant->id,
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to mark subscription as expired', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mark subscription as past due.
     */
    public function markAsPastDue(Tenant $tenant): bool
    {
        try {
            $subscription = TenantSubscription::where('tenant_id', $tenant->id)->first();
            
            if ($subscription) {
                $subscription->markAsPastDue();
                
                Log::info('Subscription marked as past due', [
                    'tenant_id' => $tenant->id,
                    'failed_payment_count' => $subscription->failed_payment_count,
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to mark subscription as past due', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get subscriptions that are ending soon.
     */
    public function getSubscriptionsEndingSoon(int $days = 3): \Illuminate\Database\Eloquent\Collection
    {
        return TenantSubscription::endingSoon($days)->with('tenant')->get();
    }

    /**
     * Get expired subscriptions.
     */
    public function getExpiredSubscriptions(): \Illuminate\Database\Eloquent\Collection
    {
        return TenantSubscription::expired()->with('tenant')->get();
    }

    /**
     * Send subscription reminder notifications.
     */
    public function sendSubscriptionReminders(): void
    {
        $endingSoon = $this->getSubscriptionsEndingSoon(3);
        
        foreach ($endingSoon as $subscription) {
            // You can implement SMS/email notifications here
            Log::info('Subscription ending soon reminder', [
                'tenant_id' => $subscription->tenant_id,
                'days_remaining' => $subscription->getCurrentPeriodDaysRemaining(),
            ]);
        }
    }

    /**
     * Process expired subscriptions (mark as expired).
     */
    public function processExpiredSubscriptions(): void
    {
        $expiredSubscriptions = TenantSubscription::where('current_period_ends_at', '<', now())
            ->where('status', 'active')
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->markAsExpired();
            
            // Sync with User model for all tenant users
            \App\Models\User::where('tenant_id', $subscription->tenant_id)->update([
                'is_suspended' => true,
            ]);

            Log::info('Processed expired subscription', [
                'tenant_id' => $subscription->tenant_id,
            ]);
        }
    }

    /**
     * Get subscription analytics.
     */
    public function getSubscriptionAnalytics(): array
    {
        $totalSubscriptions = TenantSubscription::count();
        $activeSubscriptions = TenantSubscription::active()->count();
        $expiredSubscriptions = TenantSubscription::expired()->count();
        $trialSubscriptions = TenantSubscription::where('status', 'trial')->count();

        return [
            'total' => $totalSubscriptions,
            'active' => $activeSubscriptions,
            'expired' => $expiredSubscriptions,
            'trial' => $trialSubscriptions,
            'active_percentage' => $totalSubscriptions > 0 ? round(($activeSubscriptions / $totalSubscriptions) * 100, 2) : 0,
        ];
    }

    /**
     * Calculate the monthly bill for a tenant.
     */
    public function calculateMonthlyBill(Tenant $tenant): array
    {
        $countryCode = $tenant->country_code ?: 'KE';
        
        // Fetch pricing plan for the country
        $plan = \App\Models\PricingPlan::where('country_code', $countryCode)
            ->where('is_active', true)
            ->first();

        if ($plan) {
            $pppoeRate = $plan->pppoe_price_per_month;
            $hotspotRate = $plan->hotspot_price_percentage / 100;
            $minimumPay = $plan->minimum_pay;
            $currency = $plan->currency;
            $exchangeRate = $plan->exchange_rate;
        }

        // Fallback to CountryService defaults if no plan exists
        if (!$plan) {
            $countryData = CountryService::getCountryData($countryCode);
            $pppoeRate = $countryData['pppoe_rate'];
            $hotspotRate = $countryData['hotspot_rate']; // This is a decimal e.g. 0.03
            $minimumPay = $countryData['minimum_pay'];
            $currency = $countryData['currency'];
            $exchangeRate = $countryData['exchange_rate'] ?? 1.0;
        }

        // Count active PPPoE users
        $pppoeUserCount = NetworkUser::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', 'pppoe')
            ->where('status', 'active')
            ->count();

        $pppoeAmount = $pppoeUserCount * $pppoeRate;

        // Calculate hotspot income for the last 30 days
        $hotspotIncome = TenantPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('hotspot_package_id')
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount');

        $hotspotAmount = $hotspotIncome * $hotspotRate;

        $totalAmount = $pppoeAmount + $hotspotAmount;

        $finalAmount = max($totalAmount, $minimumPay);

        // Calculate KES equivalent using the exchange rate
        // exchange_rate is "Local Currency per 1 KES"
        $finalAmountKes = ($currency === 'KES') ? $finalAmount : ($finalAmount / ($exchangeRate ?: 1));

        return [
            'pppoe_users' => $pppoeUserCount,
            'pppoe_rate' => $pppoeRate,
            'pppoe_amount' => $pppoeAmount,
            'hotspot_income' => $hotspotIncome,
            'hotspot_rate' => $hotspotRate,
            'hotspot_amount' => $hotspotAmount,
            'total_calculated' => $totalAmount,
            'minimum_pay' => $minimumPay,
            'final_amount' => $finalAmount,
            'final_amount_kes' => $finalAmountKes,
            'currency' => $currency,
        ];
    }
}
