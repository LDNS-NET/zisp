<?php

namespace App\Models\Tenants;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\Notifiable;
use App\Models\Radius\Radcheck;
use App\Models\Radius\Radreply;
use App\Models\Radius\Radusergroup;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Tenants\TenantHotspot;

class NetworkUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'network_users';

    protected $fillable = [
        'account_number',
        'full_name',
        'username',
        'password',
        'web_password',
        'phone',
        //'email',
        'location',
        'type',
        'package_id',
        'hotspot_package_id',
        'pending_package_id',
        'pending_hotspot_package_id',
        'pending_package_activation_at',
        'status',
        'registered_at',
        'expiry_notified_at',
        'expiry_warning_sent_at',
        'online',
        'expires_at',
        'mac_address',
        'created_by',
        'tenant_id',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'expires_at' => 'datetime',
        'expiry_notified_at' => 'datetime',
        'expiry_warning_sent_at' => 'datetime',
        'pending_package_activation_at' => 'datetime',
        'online' => 'boolean',
    ];

    public function getAuthPassword()
    {
        return $this->web_password;
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function hotspotPackage(): BelongsTo
    {
        return $this->belongsTo(TenantHotspot::class, 'hotspot_package_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }

    public static function generateHotspotUsername($tenantId)
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        $prefix = $tenant ? strtoupper(substr($tenant->name, 0, 1)) : 'H';
        
        // Find the last user for this tenant with a username starting with the prefix
        $lastUser = self::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'hotspot')
            ->where('username', 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastUser) {
            // Extract the number from the last username (e.g., L001 -> 1)
            $lastNumber = (int) substr($lastUser->username, 1);
            $nextNumber = $lastNumber + 1;
        }

        // Format with leading zeros (e.g., L001)
        return $prefix . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public static function generateAccountNumber($tenantId)
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        $name = $tenant ? ($tenant->business_name ?: $tenant->name) : 'System';
        
        // Remove spaces and characters O, I (case-insensitive)
        $cleanName = preg_replace('/[\sOIoi]/', '', $name);
        
        // Take first 2 letters
        $prefix = strtoupper(substr($cleanName, 0, 2));
        
        // Fallbacks if prefix is too short or empty
        if (strlen($prefix) < 2) {
            // Try to get more letters from the original name if possible, but still avoid O/I
            $prefix = str_pad($prefix, 2, 'X'); 
        }
        
        if (empty($prefix) || $prefix === 'XX') {
            $prefix = 'NU';
        }

        // Find the last account number for this prefix across ALL tenants (universally unique)
        // We order by length first to handle transitions from e.g. LD999 to LD1000
        $lastUser = self::withoutGlobalScopes()
            ->where('account_number', 'LIKE', $prefix . '%')
            ->orderByRaw('LENGTH(account_number) DESC')
            ->orderBy('account_number', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastUser) {
            $lastAccountNumber = $lastUser->account_number;
            $numberPart = substr($lastAccountNumber, strlen($prefix));
            if (is_numeric($numberPart)) {
                $nextNumber = (int)$numberPart + 1;
            }
        }

        // Format with at least 3 digits (e.g. LD001)
        return $prefix . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);
    }

    protected static function booted()
    {
        /** Apply tenant scope */
        static::addGlobalScope('tenant', function ($query) {
            $tenantId = null;
            if (tenant()) {
                $tenantId = tenant()->id;
            } else {
                foreach (['customer', 'web'] as $guard) {
                    if (auth()->guard($guard)->hasUser()) {
                        $user = auth()->guard($guard)->user();
                        if ($guard === 'web' && ($user->is_super_admin ?? false)) {
                            return;
                        }
                        $tenantId = $user->tenant_id;
                        break;
                    }

                    // Only call check() for 'web' guard to avoid recursion on NetworkUser
                    if ($guard === 'web' && auth()->guard('web')->check()) {
                        $user = auth()->guard('web')->user();
                        if ($user->is_super_admin ?? false) {
                            return;
                        }
                        $tenantId = $user->tenant_id;
                        break;
                    }
                }
            }

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            } else {
                // Fallback for public routes (hotspot page)
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

        /** Sync web_password when password changes and reset notifications if expiry extended */
        static::saving(function ($user) {
            if ($user->isDirty('password')) {
                $user->web_password = \Illuminate\Support\Facades\Hash::make($user->password);
            }

            // Auto-fill full_name with username if empty (Fail-safe for notifications)
            if (empty($user->full_name) && !empty($user->username)) {
                $user->full_name = $user->username;
            }

            if ($user->isDirty('expires_at')) {
                $newExpiry = $user->expires_at;
                $oldExpiry = $user->getOriginal('expires_at');

                // If expiry is extended or set for the first time to a future date, reset notification flags
                if ($newExpiry && (!$oldExpiry || $newExpiry->gt($oldExpiry))) {
                    if ($newExpiry->isFuture()) {
                        $user->expiry_notified_at = null;
                        $user->expiry_warning_sent_at = null;
                    }
                }
            }
        });

        /** Fill tenant_id, created_by + generate account number */
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                } else {
                    // Fallback for public routes
                    $host = request()->getHost();
                    $subdomain = explode('.', $host)[0];
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $model->tenant_id = $tenant->id;
                    }
                }
            }

            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }

            if (empty($model->account_number)) {
                $model->account_number = self::generateAccountNumber($model->tenant_id);
            }
        });

        /**
         *  Sync with RADIUS after creation
         */
        static::created(function ($user) {
            // Create radcheck entry (password)
            Radcheck::create([
                'username' => $user->username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $user->password,
            ]);

            // Package handling (Standard or Hotspot)
            $package = $user->package ?: $user->hotspotPackage;
            
            if ($package) {
                // Rate limit
                $rateValue = "{$package->upload_speed}M/{$package->download_speed}M";
                Radreply::create([
                    'username' => $user->username,
                    'attribute' => 'Mikrotik-Rate-Limit',
                    'op' => ':=',
                    'value' => $rateValue,
                ]);

                // Simultaneous Use (Devices)
                $deviceLimit = $package->device_limit ?? 1;
                Radreply::create([
                    'username' => $user->username,
                    'attribute' => 'Simultaneous-Use',
                    'op' => ':=',
                    'value' => (string)$deviceLimit,
                ]);

                // Expiry / Duration handling
                if ($user->type === 'hotspot') {
                    // Session-Timeout (Duration in seconds)
                    $seconds = 0;
                    $val = $package->duration_value ?? $package->duration ?? 1;
                    $unit = $package->duration_unit ?? 'days';
                    
                    switch ($unit) {
                        case 'minutes': $seconds = $val * 60; break;
                        case 'hours':   $seconds = $val * 3600; break;
                        case 'days':    $seconds = $val * 86400; break;
                        case 'weeks':   $seconds = $val * 604800; break;
                        case 'months':  $seconds = $val * 2592000; break;
                    }

                    if ($seconds > 0) {
                        Radreply::create([
                            'username' => $user->username,
                            'attribute' => 'Session-Timeout',
                            'op' => ':=',
                            'value' => (string)$seconds,
                        ]);
                    }
                }

                // Absolute Expiration (Works for all types if expires_at is set)
                if ($user->expires_at) {
                    Radcheck::create([
                        'username' => $user->username,
                        'attribute' => 'Expiration',
                        'op' => ':=',
                        'value' => $user->expires_at->format('d M Y H:i:s'),
                    ]);
                }

                // MAC-Auth synchronization
                if ($user->type === 'hotspot' && !empty($user->mac_address)) {
                    Radcheck::updateOrCreate(
                        ['username' => $user->mac_address, 'attribute' => 'Cleartext-Password'],
                        ['op' => ':=', 'value' => $user->mac_address]
                    );
                }

                // Group (Only for non-hotspot or if specifically needed)
                if ($user->type !== 'hotspot') {
                    Radusergroup::create([
                        'username' => $user->username,
                        'groupname' => $package->name ?? 'default',
                        'priority' => 1,
                    ]);
                }
            }
        });

        /**
         *  Update RADIUS entries when user is updated
         */
        static::updated(function ($user) {
            // Update password if changed
            Radcheck::updateOrCreate(
                ['username' => $user->username, 'attribute' => 'Cleartext-Password'],
                ['op' => ':=', 'value' => $user->password]
            );

            // Update package-related entries (Standard or Hotspot)
            $package = $user->package ?: $user->hotspotPackage;
            
            if ($package) {
                $rateValue = "{$package->upload_speed}M/{$package->download_speed}M";
                Radreply::updateOrCreate(
                    ['username' => $user->username, 'attribute' => 'Mikrotik-Rate-Limit'],
                    ['op' => ':=', 'value' => $rateValue]
                );

                // Simultaneous Use
                $deviceLimit = $package->device_limit ?? 1;
                Radreply::updateOrCreate(
                    ['username' => $user->username, 'attribute' => 'Simultaneous-Use'],
                    ['op' => ':=', 'value' => (string)$deviceLimit]
                );

                if ($user->type === 'hotspot') {
                    $seconds = 0;
                    $val = $package->duration_value ?? $package->duration ?? 1;
                    $unit = $package->duration_unit ?? 'days';
                    
                    switch ($unit) {
                        case 'minutes': $seconds = $val * 60; break;
                        case 'hours':   $seconds = $val * 3600; break;
                        case 'days':    $seconds = $val * 86400; break;
                        case 'weeks':   $seconds = $val * 604800; break;
                        case 'months':  $seconds = $val * 2592000; break;
                    }

                    if ($seconds > 0) {
                        Radreply::updateOrCreate(
                            ['username' => $user->username, 'attribute' => 'Session-Timeout'],
                            ['op' => ':=', 'value' => (string)$seconds]
                        );
                    }
                    
                    // Remove group for hotspot
                    Radusergroup::where('username', $user->username)->delete();
                } else {
                    Radusergroup::updateOrCreate(
                        ['username' => $user->username],
                        ['groupname' => $package->name ?? 'default', 'priority' => 1]
                    );
                    // Remove Session-Timeout
                    Radreply::where('username', $user->username)->where('attribute', 'Session-Timeout')->delete();
                }

                // Update Expiration
                if ($user->expires_at) {
                    Radcheck::updateOrCreate(
                        ['username' => $user->username, 'attribute' => 'Expiration'],
                        ['op' => ':=', 'value' => $user->expires_at->format('d M Y H:i:s')]
                    );
                } else {
                    Radcheck::where('username', $user->username)->where('attribute', 'Expiration')->delete();
                }

                // MAC-Auth synchronization
                if ($user->type === 'hotspot' && !empty($user->mac_address)) {
                    Radcheck::updateOrCreate(
                        ['username' => $user->mac_address, 'attribute' => 'Cleartext-Password'],
                        ['op' => ':=', 'value' => $user->mac_address]
                    );
                }

                // Cleanup old Access-Period if it exists
                Radcheck::where('username', $user->username)->where('attribute', 'Access-Period')->delete();
            }
        });

        /**
         *  Cleanup RADIUS entries when user is deleted
         */
        static::deleted(function ($user) {
            // Cleanup all RADIUS entries for this user
            Radcheck::where('username', $user->username)->delete();
            Radreply::where('username', $user->username)->delete();
            Radusergroup::where('username', $user->username)->delete();
        });
    }
}
