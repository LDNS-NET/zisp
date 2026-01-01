<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class TenantTickets extends Model
{
    protected $fillable = [
        'ticket_number',
        'client_type',
        'client_id',
        'status',
        'priority',
        'description',
        'created_by',
        'tenant_id',
    ];

    public function client()
    {
        return $this->morphTo(null, 'client_type', 'client_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            $ticket->ticket_number = 'TCK-' . strtoupper(Str::random(8));
        });
    }


    //tenant Specific
    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            } elseif (auth()->check()) {
                $user = auth()->user();
                if (!$user->is_super_admin && $user->tenant_id) {
                    $query->where('tenant_id', $user->tenant_id);
                }
            } else {
                // Fallback for public routes
                $host = request()->getHost();
                $subdomain = explode('.', $host)[0];
                $centralDomains = config('tenancy.central_domains', []);
                
                if (!in_array($host, $centralDomains)) {
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $query->where('tenant_id', $tenant->id);
                    }
                }
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                } else {
                    $host = request()->getHost();
                    $subdomain = explode('.', $host)[0];
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $model->tenant_id = $tenant->id;
                    }
                }
            }

            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

}


