<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDepreciationLog extends Model
{
    use HasFactory;

    protected $table = 'tenant_depreciation_logs';

    protected $fillable = [
        'equipment_id',
        'original_value',
        'current_value',
        'depreciation_amount',
        'calculated_date',
        'schedule',
        'tenant_id',
        'created_by'
    ];

    protected $casts = [
        'original_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'depreciation_amount' => 'decimal:2',
        'calculated_date' => 'date',
        'schedule' => 'array'
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(TenantEquipment::class, 'equipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function getDepreciationPercentage(): float
    {
        if ($this->original_value == 0) {
            return 0;
        }
        return ($this->depreciation_amount / $this->original_value) * 100;
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