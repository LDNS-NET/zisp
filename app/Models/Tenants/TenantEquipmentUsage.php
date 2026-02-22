<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TenantEquipmentUsage extends Model
{
    protected $table = 'tenant_equipment_usages';

    protected $fillable = [
        'equipment_id',
        'user_id',
        'quantity',
        'details',
        'used_at',
        'tenant_id',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function equipment()
    {
        return $this->belongsTo(TenantEquipment::class, 'equipment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                }
            }
        });
    }
}
