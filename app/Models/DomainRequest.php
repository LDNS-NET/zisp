<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'type',
        'requested_domain',
        'status',
        'rejection_reason',
        'admin_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
