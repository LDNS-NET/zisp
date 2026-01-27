<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantExpenses extends Model
{

    protected $fillable = [
        'description',
        'amount',
        'incurred_on',
        'category',
        'created_by',
        'qbo_id',
        'tenant_id'
    ];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            }
        });

        static::addGlobalScope('created_by', function ($query) {
            if (auth()->check()) {
                $query->where('created_by', auth()->id());
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id) && tenant()) {
                $model->tenant_id = tenant()->id;
            }
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}
