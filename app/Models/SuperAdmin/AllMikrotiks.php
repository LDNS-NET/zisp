<?php

namespace App\Models\Superadmin;

use Illuminate\Database\Eloquent\Model;

class AllMikrotiks extends Model
{
    protected $table = 'all_mikrotiks';

    protected $fillable = [
        'ip_address',
        'name',
        'active_users',
        'model',
        'status',
        'location',
        'owner',
        'api_username',
        'api_password',
        'status',
        'wireguard_status',
        'winbox',
        'created_at',
        'updated_at',
        'uptime',
        'version',
        'last_seen',
    ];
}
