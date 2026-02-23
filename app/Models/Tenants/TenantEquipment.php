<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantEquipment extends Model
{
    use HasFactory;

    protected $table = "tenant_equipments";

    protected $fillable = [
        'name',
        'brand',
        'type',
        'serial_number',
        'mac_address',
        'status',
        'condition',
        'location',
        'model',
        'price',
        'total_price',
        'purchase_date',
        'warranty_expiry',
        'notes',
        'assigned_to',
        'assigned_user_id',
        'created_by',
        'tenant_id',
        'qbo_id',
        'quantity',
        'unit',
        'equipment_type',
        'min_stock',
        'purchase_price',
        'depreciation_rate',
        'maintenance_interval',
        'last_maintenance_date',
        'next_maintenance_date',
        'cable_type',
        'cable_length',
        'track_serials',
        'track_length',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'last_maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
        'track_serials' => 'boolean',
        'track_length' => 'boolean',
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'cable_length' => 'decimal:2'
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(NetworkUser::class, 'assigned_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TenantEquipmentLog::class, 'equipment_id')->latest();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(TenantEquipmentUsage::class, 'equipment_id')->latest();
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(TenantEquipmentUsage::class, 'equipment_id')->latest();
    }

    public function serials(): HasMany
    {
        return $this->hasMany(TenantEquipmentSerial::class, 'equipment_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(TenantStockMovement::class, 'equipment_id');
    }

    public function depreciationLogs(): HasMany
    {
        return $this->hasMany(TenantDepreciationLog::class, 'equipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    public function needsMaintenance(): bool
    {
        if (!$this->next_maintenance_date) {
            return false;
        }
        return now()->greaterThanOrEqualTo($this->next_maintenance_date);
    }

    public function getStatusAttribute($value)
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        }
        if ($this->isLowStock()) {
            return 'low_stock';
        }
        return $value;
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