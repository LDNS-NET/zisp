<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;


class TenantActiveUsers extends Model
{
    protected $table = "tenant_active_users";

    protected $fillable = [
        'router_id',
        'user_id',
        'username',
        'session_id',
        'ip_address',
        'mac_address',
        'bytes_in',
        'bytes_out',
        'status',
        'last_seen_at',
        'connected_at',
        'disconnected_at',
        'created_by',
        'tenant_id',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function router()
    {
        return $this->belongsTo(TenantMikrotik::class, 'router_id');
    }

    public function user()
    {
        return $this->belongsTo(NetworkUser::class, 'user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            } elseif (auth()->check()) {
                $user = auth()->user();
                if (!$user->is_super_admin && $user->tenant_id) {
                    $query->where('tenant_id', $user->tenant_id);
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                }
            }

            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });
    }
}
