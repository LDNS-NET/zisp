<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\UsesTenantConnection;
use App\Models\Tenants\TenantHotspot;
use Illuminate\Support\Facades\Request;

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
            if ($package->type === 'hotspot' && function_exists('tenant')) {
                try {
                    $tenantId = tenant('id');
                    if ($tenantId) {
                        TenantHotspot::create([
                            'tenant_id' => $tenantId,
                            'name' => $package->name,
                            'duration_value' => $package->duration_value,
                            'duration_unit' => $package->duration_unit,
                            'price' => $package->price,
                            'device_limit' => $package->device_limit,
                            'upload_speed' => $package->upload_speed,
                            'download_speed' => $package->download_speed,
                            'burst_limit' => $package->burst_limit,
                            'created_by' => $package->created_by,
                            'domain' => Request::host(),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error creating TenantHotspot: ' . $e->getMessage(), [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        });

        static::updated(function ($package) {
            if ($package->type === 'hotspot' && function_exists('tenant')) {
                try {
                    $tenantId = tenant('id');
                    if ($tenantId) {
                        TenantHotspot::where('name', $package->name)
                            ->where('tenant_id', $tenantId)
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
                } catch (\Exception $e) {
                    \Log::error('Error updating TenantHotspot: ' . $e->getMessage(), [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        });

        static::deleting(function ($package) {
            if ($package->type === 'hotspot' && function_exists('tenant')) {
                try {
                    $tenantId = tenant('id');
                    if ($tenantId) {
                        TenantHotspot::where('name', $package->name)
                            ->where('tenant_id', $tenantId)
                            ->delete();
                    }
                } catch (\Exception $e) {
                    \Log::error('Error deleting TenantHotspot: ' . $e->getMessage(), [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'trace' => $e->getTraceAsString()
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

