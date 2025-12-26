<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantHotspot extends Model
{
    protected $table = 'tenant_hotspot_packages';
    protected $fillable = [
        'tenant_id',
        //'package_id',
        'name',
        'duration_value',
        'duration_unit',
        'price',
        'device_limit',
        'upload_speed',
        'download_speed',
        'burst_limit',
        'created_by',
        //'domain',
    ];
}
