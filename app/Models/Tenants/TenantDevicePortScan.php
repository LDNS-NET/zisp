<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDevicePortScan extends Model
{
    protected $fillable = [
        'tenant_device_id',
        'scan_type',
        'ports_found',
        'scan_status',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'ports_found' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the device that owns this port scan.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(TenantDevice::class, 'tenant_device_id');
    }

    /**
     * Mark scan as running.
     */
    public function markAsRunning(): void
    {
        $this->update(['scan_status' => 'running']);
    }

    /**
     * Mark scan as completed.
     */
    public function markAsCompleted(array $ports): void
    {
        $this->update([
            'scan_status' => 'completed',
            'ports_found' => $ports,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark scan as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'scan_status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}
