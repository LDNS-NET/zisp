<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\UsesTenantConnection;

class TenantHotspot extends Model
{
    /** @use HasFactory<\Database\Factories\TenantHotspotFactory> */
    use HasFactory, UsesTenantConnection;

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
