<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantMikrotik extends Model
{
    protected $fillable = [
        //
    ];

    protected $casts = [
       // 
    ];

    protected static function booted()
    {
        static::addGlobalScope('created_by', function ($query) {
            if (auth()->check()) {
                $query->where('created_by', auth()->id());
            }
        });
        static::creating(function ($model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}
