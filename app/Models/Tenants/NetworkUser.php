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
        'email',
        'location',
        'type',
        'package_id',
        'status',
        'registered_at',
        'expires_at',
        'online',
        'created_by',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'expires_at'    => 'datetime',
        'online'        => 'boolean',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
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
                'username'  => $user->username,
                'attribute' => 'Cleartext-Password',
                'op'        => ':=',
                'value'     => $user->password,
            ]);

            // Package speed handling
            $package = $user->package;
            if ($package) {
                $rateValue = "{$package->upload_speed}M/{$package->download_speed}M";
                Radreply::create([
                    'username'  => $user->username,
                    'attribute' => 'Mikrotik-Rate-Limit',
                    'op'        => ':=',
                    'value'     => $rateValue,
                ]);

                // Group name can be package name or type
                Radusergroup::create([
                    'username'  => $user->username,
                    'groupname' => $package->name ?? 'default',
                    'priority'  => 1,
                ]);
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
            }
        });

        /**
         *  Cleanup RADIUS entries when user is deleted
         */
        static::deleted(function ($user) {
            Radcheck::where('username', $user->username)->delete();
            Radreply::where('username', $user->username)->delete();
            Radusergroup::where('username', $user->username)->delete();
        });
    }
}
