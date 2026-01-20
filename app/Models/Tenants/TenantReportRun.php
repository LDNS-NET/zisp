<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantReportRun extends Model
{
    protected $fillable = [
        'report_id',
        'generated_at',
        'file_path',
        'status',
        'error_message',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    /**
     * Relationship to report
     */
    public function report()
    {
        return $this->belongsTo(TenantCustomReport::class, 'report_id');
    }

    /**
     * Check if run was successful
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if run failed
     */
    public function isFailed()
    {
        return $this->status === 'failed';
    }

    /**
     * Scope for completed runs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed runs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
