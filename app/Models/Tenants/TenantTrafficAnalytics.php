<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantTrafficAnalytics extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'date',
        'hour',
        'bytes_in',
        'bytes_out',
        'total_bytes',
        'protocol',
    ];

    protected $casts = [
        'date' => 'date',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'total_bytes' => 'integer',
    ];

    /**
     * Relationship to NetworkUser
     */
    public function user()
    {
        return $this->belongsTo(NetworkUser::class, 'user_id');
    }

    /**
     * Scope for tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
