<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDeviceLog extends Model
{
    protected $table = 'tenant_device_logs';

    protected $fillable = [
        'tenant_device_id',
        'log_type',
        'message',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(TenantDevice::class, 'tenant_device_id');
    }
}
