<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantEquipment extends Model
{
    protected $table = "tenant_equipments";

    protected $fillable = [
        "name",
        "brand",
        "type",
        "serial_number",
        "mac_address",
        "status",
        "condition",
        "location",
        "model",
        "price",
        "total_price",
        "purchase_date",
        "warranty_expiry",
        "notes",
        "assigned_to",
        "assigned_user_id",
        "created_by",
        "tenant_id",
        "qbo_id",
        "quantity",
    ];

    public function assignedUser()
    {
        return $this->belongsTo(NetworkUser::class, 'assigned_user_id');
    }

    public function logs()
    {
        return $this->hasMany(TenantEquipmentLog::class, 'equipment_id')->latest();
    }

    public function usages()
    {
        return $this->hasMany(TenantEquipmentUsage::class, 'equipment_id')->latest();
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

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
