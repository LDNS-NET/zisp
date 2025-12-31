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

    protected static function booted()
    {
        /** Apply created_by scope */
        static::addGlobalScope('created_by', function ($query) {
            if (Auth::check()) {
                $query->where('created_by', Auth::id());
            }
        });

        /** Fill created_by + generate account number */
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }

            if (empty($model->account_number)) {
                $tenant = app(Tenant::class);
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
    public static function generateUsername(): string
    {
        $tenant = app(Tenant::class);
        $prefix = $tenant && !empty($tenant->business_name)
            ? strtoupper(substr(preg_replace('/\s+/', '', $tenant->business_name), 0, 1))
            : 'U';

        // Avoid confusing letters for prefix if possible, but strict requirement is first letter.
        // If we strictly follow "first letter", we use it.

        // Find last username with this prefix and 3+ digits
        $lastUser = self::where('username', 'REGEXP', "^{$prefix}[0-9]+$")
            ->orderByRaw('LENGTH(username) DESC')
            ->orderBy('username', 'desc')
            ->first();

        if ($lastUser) {
            $number = intval(substr($lastUser->username, 1));
            $nextNumber = $number + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public static function generatePassword(): string
    {
        return (string) mt_rand(100000, 999999);
    }
}
