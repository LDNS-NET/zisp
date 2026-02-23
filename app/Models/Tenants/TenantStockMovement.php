<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStockMovement extends Model
{
    use HasFactory;

    protected $table = 'tenant_stock_movements';

    protected $fillable = [
        'equipment_id',
        'location',
        'quantity',
        'direction',
        'reference',
        'notes',
        'created_by',
        'tenant_id'
    ];

    protected $casts = [
        'quantity' => 'decimal:2'
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(TenantEquipment::class, 'equipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'IN';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'OUT';
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