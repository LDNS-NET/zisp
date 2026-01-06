<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantMikrotik;

class TenantActiveSession extends Model
{
    protected $table = 'tenant_active_sessions';

    protected $fillable = [
        'router_id',
        'user_id',
        'session_id',
        'ip_address',
        'mac_address',
        'bytes_in',
        'bytes_out',
        'connected_at',
        'last_seen_at',
        'status',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(TenantMikrotik::class, 'router_id');
    }

    public function user()
    {
        return $this->belongsTo(NetworkUser::class, 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
