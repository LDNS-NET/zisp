<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class TenantTechnician extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_technicians';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'email',
        'phone',
        'employee_id',
        'status',
        'specialization',
        'latitude',
        'longitude',
        'last_location_update',
        'skills',
        'completed_installations',
        'average_rating',
        'notes',
    ];

    protected $casts = [
        'skills' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'average_rating' => 'decimal:2',
        'last_location_update' => 'datetime',
    ];

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
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function installations()
    {
        return $this->hasMany(TenantInstallation::class, 'technician_id');
    }

    public function locations()
    {
        return $this->hasMany(TenantTechnicianLocation::class, 'technician_id');
    }

    public function currentLocation()
    {
        return $this->hasOne(TenantTechnicianLocation::class, 'technician_id')
            ->latest('recorded_at');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
            ->whereDoesntHave('installations', function ($q) {
                $q->whereIn('status', ['scheduled', 'in_progress'])
                    ->where('scheduled_date', now()->toDateString());
            });
    }

    // Helpers
    public function updateLocation($latitude, $longitude, $accuracy = null, $speed = null)
    {
        $this->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'last_location_update' => now(),
        ]);

        $this->locations()->create([
            'tenant_id' => $this->tenant_id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'speed' => $speed,
            'recorded_at' => now(),
        ]);
    }

    public function updateRating()
    {
        $avgRating = $this->installations()
            ->whereNotNull('customer_rating')
            ->avg('customer_rating');

        $this->update([
            'average_rating' => $avgRating ?? 0,
        ]);
    }
}
