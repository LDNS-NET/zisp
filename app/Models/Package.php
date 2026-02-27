<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\UsesTenantConnection;

class Package extends Model
{
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'mikrotik_profile',
        'price',
        'device_limit',
        'duration_value',
        'duration_unit',
        'upload_speed',
        'download_speed',
        'burst_limit',
        'created_by',
        'tenant_id',
        'hotspot_category_id',
    ];

    public function hotspotCategory()
    {
        return $this->belongsTo(\App\Models\Tenants\HotspotCategory::class, 'hotspot_category_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            $tenantId = null;
            if (tenant()) {
                $tenantId = tenant()->id;
            } else {
                foreach (['customer', 'web'] as $guard) {
                    if (auth()->guard($guard)->hasUser()) {
                        $tenantId = auth()->guard($guard)->user()->tenant_id;
                        break;
                    }

                    if ($guard === 'web' && auth()->guard('web')->check()) {
                        $tenantId = auth()->guard('web')->user()->tenant_id;
                        break;
                    }
                }
            }

            if ($tenantId) {
                $query->where(function($q) use ($tenantId) {
                    $q->where('packages.tenant_id', $tenantId)
                      ->orWhereNull('packages.tenant_id');
                });
            }
        });

        static::creating(function ($model) {
            // Generate UUID for new records
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }

            if (auth()->guard('web')->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
            
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                }
            }
        });
    }

    protected $appends = ['duration_in_days'];

    public function getDurationInDaysAttribute(): int|float|null
    {
        return match ($this->duration_unit) {
            'minutes' => round($this->duration_value / 1440, 2),
            'hours' => round($this->duration_value / 24, 2),
            'days' => $this->duration_value,
            'weeks' => $this->duration_value * 7,
            'months' => $this->duration_value * 30,
            default => null,
        };
    }

}

