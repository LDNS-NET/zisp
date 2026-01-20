<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantTicketReply extends Model
{
    protected $fillable = [
        'ticket_id',
        'repliable_type',
        'repliable_id',
        'message',
        'tenant_id',
    ];

    public function ticket()
    {
        return $this->belongsTo(TenantTickets::class, 'ticket_id');
    }

    public function repliable()
    {
        return $this->morphTo();
    }

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            }
        });

        static::creating(function ($model) {
            if (tenant()) {
                $model->tenant_id = tenant()->id;
            }
        });
    }
}
