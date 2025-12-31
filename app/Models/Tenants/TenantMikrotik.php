<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TenantMikrotik extends Model
{
    protected $fillable = [
        'name',
        'ip_address', // Legacy field - prefer wireguard_address for VPN IP
        'api_port',
        'ssh_port',
        'openvpn_profile_id',
        'router_username',
        'router_password',
        'api_username',
        'api_password',
        'connection_type',
        'last_seen_at',
        'status',
        'model',
        'os_version',
        'uptime',
        'cpu_usage',
        'memory_usage',
        'temperature',
        'notes',
        'sync_token',
        'created_by',
        // WireGuard fields
        'wireguard_public_key',
        'wireguard_allowed_ips',
        'wireguard_address',
        'wireguard_port',
        'wireguard_status',
        'wireguard_last_handshake',
        // API polling fields
        'online',
        'cpu',
            'memory',
            'public_ip',
            'winbox_port',
            'online_since',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'uptime' => 'integer',
        'cpu_usage' => 'decimal:2',
        'memory_usage' => 'decimal:2',
        'temperature' => 'decimal:2',
        // WireGuard handshake timestamp
        'wireguard_last_handshake' => 'datetime',
        // API polling fields
        'online' => 'boolean',
        'cpu' => 'decimal:2',
        'memory' => 'decimal:2',
        'online_since' => 'datetime',
    ];

    protected $hidden = [
        'router_password',
        'api_password',
    ];

    /* Encrypt password when setting
    public function setRouterPasswordAttribute($value)
    {
        $this->attributes['router_password'] = Crypt::encryptString($value);
    }*/

    /* Decrypt password when getting
    public function getRouterPasswordAttribute($value)
    {
        return Crypt::decryptString($value);
    }*/

    // Relationships
    public function logs(): HasMany
    {
        return $this->hasMany(TenantRouterLog::class, 'router_id');
    }

    public function bandwidthUsage(): HasMany
    {
        return $this->hasMany(TenantBandwidthUsage::class, 'router_id');
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(TenantActiveSession::class, 'router_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(TenantRouterAlert::class, 'router_id');
    }

    public function openvpnProfile(): BelongsTo
    {
        return $this->belongsTo(TenantOpenVPNProfile::class, 'openvpn_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // Scopes
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    // Helper methods
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function getUptimeFormatted(): string
    {
        // If router is officially "offline", show Offline
        if ($this->status === 'offline') {
            return 'Offline';
        }

        // Use stored uptime from DB (updated via API/Polling)
        if (!$this->uptime) {
            return 'N/A';
        }

        $seconds = $this->uptime;
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$days}d {$hours}h {$minutes}m";
    }

    public function getConnectionUrl(): string
    {
        switch ($this->connection_type) {
            case 'api':
                return "http://{$this->ip_address}:{$this->api_port}";
            case 'ssh':
                return "ssh://{$this->ip_address}:{$this->ssh_port}";
            case 'ovpn':
                return "ovpn://{$this->ip_address}";
            default:
                return $this->ip_address;
        }
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
            } else {
                // Fallback for public routes
                $host = request()->getHost();
                $subdomain = explode('.', $host)[0];
                $centralDomains = config('tenancy.central_domains', []);
                
                if (!in_array($host, $centralDomains)) {
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $query->where('tenant_id', $tenant->id);
                    }
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                } else {
                    $host = request()->getHost();
                    $subdomain = explode('.', $host)[0];
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $model->tenant_id = $tenant->id;
                    }
                }
            }

            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
    /**
    * Get remote Winbox address (public_ip:winbox_port).
    */
    public function getRemoteWinboxAddressAttribute(): string
    {
        return $this->public_ip && $this->winbox_port ? "{$this->public_ip}:{$this->winbox_port}" : '';
    }

    }
