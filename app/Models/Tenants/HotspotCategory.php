<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use App\Models\Package;

class HotspotCategory extends Model
{
    protected $table = 'hotspot_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'display_order',
    ];

    public function packages()
    {
        return $this->hasMany(Package::class, 'hotspot_category_id');
    }

    public function tenantHotspotPackages()
    {
        return $this->hasMany(TenantHotspot::class, 'hotspot_category_id');
    }

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            }
        });
    }
}
