<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\UsesTenantConnection;

class Package extends Model
{
    protected $fillable = [
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
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            } elseif (auth()->guard('customer')->check()) {
                $user = auth()->guard('customer')->user();
                if ($user->tenant_id) {
                    $query->where('tenant_id', $user->tenant_id);
                }
            } elseif (auth()->check()) {
                $user = auth()->user();
                if ($user->tenant_id) {
                    $query->where('tenant_id', $user->tenant_id);
                }
            }
        });

        static::addGlobalScope('created_by', function ($query) {
            if (auth()->guard('web')->check()) {
                $query->where('created_by', auth()->id());
            }
        });

        static::creating(function ($model) {
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

