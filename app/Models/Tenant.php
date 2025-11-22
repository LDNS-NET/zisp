<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'username',
        'subdomain',
        'database',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'username',
            'email',
            'phone',
        ];
    }

    /**
     * Get the subscription for the tenant.
     */
    public function subscription()
    {
        return $this->hasOne(TenantSubscription::class, 'tenant_id', 'id');
    }

    /**
     * Check if the tenant has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription && $this->subscription->isActive();
    }

    /**
     * Check if the tenant is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->subscription && $this->subscription->isOnTrial();
    }

    /**
     * Check if the tenant's subscription is expired.
     */
    public function isSubscriptionExpired(): bool
    {
        return $this->subscription && $this->subscription->isExpired();
    }
}

