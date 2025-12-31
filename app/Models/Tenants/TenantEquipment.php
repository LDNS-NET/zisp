<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantEquipment extends Model
{
    protected $table = "tenant_equipments";

    protected $fillable = [
        "name",
        "type",
        "serial_number",
        "location",
        "model",
        "price",
        "total_price",
        "created_by",
        "assigned_to",
    ];


    protected static function booted()
    {
        static::creating(function ($model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}
