<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;


class TenantActiveUsers extends Model
{
    protected $table = "tenant_active_users";

    protected $fillable = [
        "username",
        'user_type',
        'ip/mac_address',
        "session_start",
        "session_end",
        "created_by",
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(NetworkUser::class, 'username');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });
    }
}
