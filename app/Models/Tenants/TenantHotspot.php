<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantHotspot extends Model
{
    protected $table = 'tenant_hotspot_packages';

    protected $fillable = [
        'tenant_id',
        'package_id',
        'name',
        'duration_value',
        'duration_unit',
        'price',
        'device_limit',
        'upload_speed',
        'download_speed',
        'burst_limit',
        'created_by',
        //'domain',
    ];

    public function package()
    {
        return $this->belongsTo(\App\Models\Package::class, 'package_id');
    }

    protected $casts = [
        'duration_value' => 'integer',
        'price' => 'float',
        'device_limit' => 'integer',
        'upload_speed' => 'integer',
        'download_speed' => 'integer',
        'burst_limit' => 'integer',
    ];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            } else {
                foreach (['customer', 'web'] as $guard) {
                    if (auth()->guard($guard)->hasUser()) {
                        $user = auth()->guard($guard)->user();
                        if ($guard === 'web' && ($user->is_super_admin ?? false)) {
                            return;
                        }
                        $query->where('tenant_id', $user->tenant_id);
                        return;
                    }

                    if ($guard === 'web' && auth()->guard('web')->check()) {
                        $user = auth()->guard('web')->user();
                        if ($user->is_super_admin ?? false) {
                            return;
                        }
                        $query->where('tenant_id', $user->tenant_id);
                        return;
                    }
                }

                // Fallback for public routes (hotspot page)
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
    }
}
