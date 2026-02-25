<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TenantEquipmentLog extends Model
{
    protected $table = 'tenant_equipment_logs';

    protected $fillable = [
        'equipment_id',
        'action',
        'old_status',
        'new_status',
        'performed_by',
        'description',
    ];

    public function equipment()
    {
        return $this->belongsTo(TenantEquipment::class, 'equipment_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
