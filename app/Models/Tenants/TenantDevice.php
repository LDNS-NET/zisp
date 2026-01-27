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
                $query->where(function ($q) {
                    $q->where('tenant_id', tenant()->id)
                      ->orWhereNull('tenant_id');
                });
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                }
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
}
