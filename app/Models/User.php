<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;


class User extends Authenticatable //implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    // NOTE: This model is used for super admins in the central DB and for tenant admins in tenant DBs only.
    // Do not use for WiFi users (network users), which are managed in the network_users table per tenant.

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'username',
        'country',
        'country_code',
        'currency',
        'currency_name',
        'dial_code',
        'tenant_id',
        'subscription_expires_at',
        'is_super_admin',
        'role',
        'email_verified_at',
        'last_login_at',
        'is_suspended',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_super_admin' => 'boolean',
        'subscription_expires_at' => 'datetime',
        'is_suspended' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getIsSuperAdminAttribute(): bool
    {
        return $this->attributes['is_super_admin'] ?? false;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenantGeneralSetting()
    {
        return $this->hasOne(TenantGeneralSetting::class, 'tenant_id', 'tenant_id');
    }
}
