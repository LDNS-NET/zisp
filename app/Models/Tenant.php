<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Illuminate\Support\Facades\Config;
use IntaSend\IntaSendPHP\APIService;
use IntaSend\IntaSendPHP\Wallet;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDomains, HasDatabase;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'username',
        'country',
        'country_code',
        'currency',
        'currency_name',
        'dial_code',
        'wallet_id',
    ];

    public function configure()
    {
        // 🧠 Point the tenant connection to the correct SQLite file
        Config::set('database.connections.tenant.database', $this->databasePath());
    }

    protected function databasePath(): string
    {
        // 🔐 Store DB in tenants folder with tenant ID as filename
        return database_path("tenants/{$this->id}.sqlite");
    }

    // Removed booted() wallet creation logic. Wallets are now created in controllers before tenant creation.

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'username',
            'email',
            'phone',
            'country',
            'country_code',
            'currency',
            'currency_name',
            'dial_code',
            'wallet_id',
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

