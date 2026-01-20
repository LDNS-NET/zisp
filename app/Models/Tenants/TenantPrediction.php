<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantPrediction extends Model
{
    protected $fillable = [
        'tenant_id',
        'prediction_type',
        'entity_id',
        'prediction_value',
        'confidence',
        'factors',
        'predicted_at',
        'valid_until',
    ];

    protected $casts = [
        'prediction_value' => 'decimal:2',
        'confidence' => 'decimal:2',
        'factors' => 'array',
        'predicted_at' => 'datetime',
        'valid_until' => 'datetime',
    ];

    /**
     * Relationship to NetworkUser (for churn predictions)
     */
    public function user()
    {
        return $this->belongsTo(NetworkUser::class, 'entity_id');
    }

    /**
     * Scope for tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope for prediction type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('prediction_type', $type);
    }

    /**
     * Scope for valid predictions
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
              ->orWhere('valid_until', '>', now());
        });
    }
}
