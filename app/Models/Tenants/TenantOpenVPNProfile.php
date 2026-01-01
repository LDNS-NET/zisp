<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantOpenVPNProfile extends Model
{
    protected $table = 'tenant_openvpn_profiles';

    protected $fillable = [
        'config_path',
        'client_cert_path',
        'client_key_path',
        'ca_cert_path',
        'status',
        'tenant_id',
    ];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            } elseif (auth()->check()) {
                $user = auth()->user();
                if (!$user->is_super_admin && $user->tenant_id) {
                    $query->where('tenant_id', $user->tenant_id);
                }
            } else {
                // Fallback for public routes
                $host = request()->getHost();
                $subdomain = explode('.', $host)[0];
                $centralDomains = config('tenancy.central_domains', []);
                
                if (!in_array($host, $centralDomains)) {
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $query->where('tenant_id', $tenant->id);
                    }
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                } else {
                    $host = request()->getHost();
                    $subdomain = explode('.', $host)[0];
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $model->tenant_id = $tenant->id;
                    }
                }
            }
        });
    }

    protected $casts = [
        'status' => 'string',
    ];

    // Relationships
    public function routers(): HasMany
    {
        return $this->hasMany(TenantMikrotik::class, 'openvpn_profile_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}
