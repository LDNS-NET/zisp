<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantTechnicianLocation extends Model
{
    protected $table = 'tenant_technician_locations';

    protected $fillable = [
        'tenant_id',
        'technician_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'activity_type',
        'installation_id',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'speed' => 'decimal:2',
        'recorded_at' => 'datetime',
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

            if (empty($model->recorded_at)) {
                $model->recorded_at = now();
            }
        });
    }

    // Relationships
    public function technician()
    {
        return $this->belongsTo(TenantTechnician::class, 'technician_id');
    }

    public function installation()
    {
        return $this->belongsTo(TenantInstallation::class, 'installation_id');
    }

    // Scopes
    public function scopeRecent($query, $minutes = 30)
    {
        return $query->where('recorded_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('recorded_at', now()->toDateString());
    }

    public function scopeForTechnician($query, $technicianId)
    {
        return $query->where('technician_id', $technicianId);
    }

    // Helpers
    public static function getDistanceBetween($lat1, $lon1, $lat2, $lon2)
    {
        // Haversine formula to calculate distance in kilometers
        $earthRadius = 6371;

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}
