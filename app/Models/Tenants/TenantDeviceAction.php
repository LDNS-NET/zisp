<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDeviceAction extends Model
{
    protected $table = 'tenant_device_actions';

    protected $fillable = [
        'tenant_device_id',
        'action',
        'payload',
        'status',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(TenantDevice::class, 'tenant_device_id');
    }
}
