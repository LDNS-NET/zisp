<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantEquipmentSerial extends Model
{
    use HasFactory;

    protected $table = 'tenant_equipment_serials';

    protected $fillable = [
        'equipment_id',
        'serial',
        'mac',
        'status',
        'assigned_to_user_id',
        'tenant_id',
        'created_by'
    ];

    protected $casts = [
        'status' => 'string'
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(TenantEquipment::class, 'equipment_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(NetworkUser::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isInUse(): bool
    {
        return $this->status === 'in_use';
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

            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}