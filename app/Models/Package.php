<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\UsesTenantConnection;
use App\Models\Tenants\TenantHotspot;

class Package extends Model
{
    protected $fillable = [
        'name',
        'type',
        'price',
        'device_limit',
        'duration_value',
        'duration_unit',
        'upload_speed',
        'download_speed',
        'burst_limit',
        'created_by',
    ];
    protected static function booted()
    {
        static::addGlobalScope('created_by', function ($query) {
            if (auth()->check()) {
                $query->where('created_by', auth()->id());
            }
        });
        static::creating(function ($model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });

        // Sync hotspot packages to tenant_hotspot table
        static::created(function ($package) {
            if ($package->type === 'hotspot') {
                TenantHotspot::create([
                    'tenant_id' => tenant('id'),
                    'name' => $package->name,
                    'duration_value' => $package->duration_value,
                    'duration_unit' => $package->duration_unit,
                    'price' => $package->price,
                    'device_limit' => $package->device_limit,
                    'upload_speed' => $package->upload_speed,
                    'download_speed' => $package->download_speed,
                    'burst_limit' => $package->burst_limit,
                    'created_by' => $package->created_by,
                    'domain' => request()->getHost(),
                ]);
            }
        });

        static::updated(function ($package) {
            if ($package->type === 'hotspot') {
                TenantHotspot::where('name', $package->name)
                    ->where('tenant_id', tenant('id'))
                    ->update([
                        'duration_value' => $package->duration_value,
                        'duration_unit' => $package->duration_unit,
                        'price' => $package->price,
                        'device_limit' => $package->device_limit,
                        'upload_speed' => $package->upload_speed,
                        'download_speed' => $package->download_speed,
                        'burst_limit' => $package->burst_limit,
                        'created_by' => $package->created_by,
                    ]);
            }
        });

        static::deleting(function ($package) {
            if ($package->type === 'hotspot') {
                try {
                    TenantHotspot::where('name', $package->name)
                        ->where('tenant_id', tenant('id'))
                        ->delete();
                } catch (\Exception $e) {
                    \Log::error('Failed to delete related TenantHotspot: ' . $e->getMessage(), [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'tenant_id' => tenant('id', 'unknown'),
                    ]);
                }
            }
        });
    }

    protected $appends = ['duration_in_days'];

    public function getDurationInDaysAttribute(): int|float|null
    {
        return match ($this->duration_unit) {
            'minutes' => round($this->duration_value / 1440, 2),
            'hours'   => round($this->duration_value / 24, 2),
            'days'    => $this->duration_value,
            'weeks'   => $this->duration_value * 7,
            'months'  => $this->duration_value * 30,
            default   => null,
        };
    }

}

