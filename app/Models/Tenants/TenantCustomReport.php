<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TenantCustomReport extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'config',
        'schedule',
        'created_by',
    ];

    protected $casts = [
        'config' => 'array',
        'schedule' => 'array',
    ];

    /**
     * Relationship to creator
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Relationship to report runs
     */
    public function runs()
    {
        return $this->hasMany(TenantReportRun::class, 'report_id');
    }

    /**
     * Get latest run
     */
    public function latestRun()
    {
        return $this->hasOne(TenantReportRun::class, 'report_id')->latest('generated_at');
    }

    /**
     * Scope for tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Check if report is scheduled
     */
    public function isScheduled()
    {
        return !empty($this->schedule);
    }
}
