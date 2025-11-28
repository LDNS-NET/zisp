<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\UsesTenantConnection;

class TenantHotspotPackage extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'name',
        'price',
        'download_speed',
        'upload_speed',
        'duration_value',
        'duration_unit',
        'created_by',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (is_null($model->tenant_id)) {
                $model->tenant_id = tenant('id');
            }
        });
    }
}
