<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantDevice extends Model
{
    protected $table = 'tenant_devices';

    protected $fillable = [
        'tenant_id',
        'subscriber_id',
        'serial_number',
        'model',
        'manufacturer',
        'software_version',
        'mac_address',
        'wan_ip',
        'lan_ip',
        'online',
        'last_contact_at',
    ];

    protected $casts = [
        'online' => 'boolean',
        'last_contact_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                }
            }
        });

        static::created(function ($device) {
            if ($device->subscriber) {
                $device->subscriber->touch(); // Trigger NetworkUser updated event
            }
        });

        static::updated(function ($device) {
            if ($device->isDirty('mac_address') && $device->subscriber) {
                $device->subscriber->touch(); // Trigger NetworkUser updated event
            }
        });

        static::deleted(function ($device) {
            if ($device->subscriber) {
                // When a device is deleted, we should ideally remove it from Radcheck
                if ($device->mac_address) {
                    \App\Models\Radius\Radcheck::where('username', $device->mac_address)
                        ->where('attribute', 'Cleartext-Password')
                        ->delete();
                }
                $device->subscriber->touch();
            }
        });
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NetworkUser::class, 'subscriber_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TenantDeviceLog::class, 'tenant_device_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(TenantDeviceAction::class, 'tenant_device_id');
    }

    public function portScans(): HasMany
    {
        return $this->hasMany(TenantDevicePortScan::class, 'tenant_device_id');
    }
}
