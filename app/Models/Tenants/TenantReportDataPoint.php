<?php

namespace App\Models\Tenants;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantReportDataPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'category',
        'value',
        'description',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
