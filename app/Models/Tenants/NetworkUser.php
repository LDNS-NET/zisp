<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Models\Radius\Radcheck;
use App\Models\Radius\Radreply;
use App\Models\Radius\Radusergroup;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Tenants\TenantHotspot;

class NetworkUser extends Model
{
    use HasFactory;

    protected $table = 'network_users';

    protected $fillable = [
        'account_number',
        'full_name',
        'username',
        'password',
        'phone',
        //'email',
        'location',
        'type',
        'package_id',
        'hotspot_package_id',
        'status',
        'registered_at',
        'expires_at',
        'online',
        'created_by',
        'tenant_id',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'expires_at' => 'datetime',
        'online' => 'boolean',
    ];

    public function package(): BelongsTo
    {
        if ($this->type === 'hotspot') {
            return $this->belongsTo(TenantHotspot::class, 'hotspot_package_id');
        }
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function hotspotPackage(): BelongsTo
    {
        return $this->belongsTo(TenantHotspot::class, 'hotspot_package_id');
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

    protected static function booted()
    {
        /** Apply tenant scope */
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            } elseif (auth()->check()) {
                $user = auth()->user();
                if (!$user->is_super_admin && $user->tenant_id) {
                    $query->where('tenant_id', $user->tenant_id);
                }
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
                $tenant = \App\Models\Tenant::find($model->tenant_id) ?: app(Tenant::class);
                $prefix = $tenant && !empty($tenant->business_name)
                    ? strtoupper(substr(preg_replace('/\s+/', '', $tenant->business_name), 0, 2))
                    : 'NU';

                do {
                    $accountNumber = $prefix . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                } while (self::where('account_number', $accountNumber)->exists());

                $model->account_number = $accountNumber;
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

            // Package handling
            $package = $user->package;
            if ($package) {
                // Rate limit
                $rateValue = "{$package->upload_speed}M/{$package->download_speed}M";
                Radreply::create([
                    'username' => $user->username,
                    'attribute' => 'Mikrotik-Rate-Limit',
                    'op' => ':=',
                    'value' => $rateValue,
                ]);

                // Group
                Radusergroup::create([
                    'username' => $user->username,
                    'groupname' => $package->name ?? 'default',
                    'priority' => 1,
                ]);

                // Expiry / Duration handling
                if ($user->type === 'hotspot') {
                    // Use Access-Period for relative expiry (start on first use)
                    $seconds = 0;
                    $val = $package->duration_value;
                    switch ($package->duration_unit) {
                        case 'minutes': $seconds = $val * 60; break;
                        case 'hours':   $seconds = $val * 3600; break;
                        case 'days':    $seconds = $val * 86400; break;
                        case 'weeks':   $seconds = $val * 604800; break;
                        case 'months':  $seconds = $val * 2592000; break;
                    }

                    if ($seconds > 0) {
                        Radcheck::create([
                            'username' => $user->username,
                            'attribute' => 'Access-Period',
                            'op' => ':=',
                            'value' => (string)$seconds,
                        ]);
                    }
                } elseif ($user->expires_at) {
                    // Standard absolute expiration for other types
                    Radcheck::create([
                        'username' => $user->username,
                        'attribute' => 'Expiration',
                        'op' => ':=',
                        'value' => $user->expires_at->format('d M Y H:i:s'),
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

            // Update package-related entries
            $package = $user->package;
            if ($package) {
                $rateValue = "{$package->upload_speed}M/{$package->download_speed}M";
                Radreply::updateOrCreate(
                    ['username' => $user->username, 'attribute' => 'Mikrotik-Rate-Limit'],
                    ['op' => ':=', 'value' => $rateValue]
                );

                Radusergroup::updateOrCreate(
                    ['username' => $user->username],
                    ['groupname' => $package->name ?? 'default', 'priority' => 1]
                );

                // Update Expiry / Duration
                if ($user->type === 'hotspot') {
                    $seconds = 0;
                    $val = $package->duration_value;
                    switch ($package->duration_unit) {
                        case 'minutes': $seconds = $val * 60; break;
                        case 'hours':   $seconds = $val * 3600; break;
                        case 'days':    $seconds = $val * 86400; break;
                        case 'weeks':   $seconds = $val * 604800; break;
                        case 'months':  $seconds = $val * 2592000; break;
                    }

                    if ($seconds > 0) {
                        Radcheck::updateOrCreate(
                            ['username' => $user->username, 'attribute' => 'Access-Period'],
                            ['op' => ':=', 'value' => (string)$seconds]
                        );
                        // Ensure Expiration is removed if switched to hotspot
                        Radcheck::where('username', $user->username)->where('attribute', 'Expiration')->delete();
                    }
                } elseif ($user->expires_at) {
                    Radcheck::updateOrCreate(
                        ['username' => $user->username, 'attribute' => 'Expiration'],
                        ['op' => ':=', 'value' => $user->expires_at->format('d M Y H:i:s')]
                    );
                    // Ensure Access-Period is removed
                    Radcheck::where('username', $user->username)->where('attribute', 'Access-Period')->delete();
                } else {
                    Radcheck::where('username', $user->username)
                        ->whereIn('attribute', ['Expiration', 'Access-Period'])
                        ->delete();
                }
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
