<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class TenantInstallationChecklist extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_installation_checklists';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'installation_type',
        'service_type',
        'checklist_items',
        'is_default',
        'is_active',
        'order',
        'created_by',
    ];

    protected $casts = [
        'checklist_items' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

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
            }
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (tenant()) {
                    $model->tenant_id = tenant()->id;
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                }
            }

            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForInstallationType($query, $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->where('installation_type', $type)
              ->orWhere('installation_type', 'all');
        });
    }

    public function scopeForServiceType($query, $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->where('service_type', $type)
              ->orWhere('service_type', 'all');
        });
    }

    // Helpers
    public function getChecklistForInstallation($installationType, $serviceType)
    {
        return $this->where('is_active', true)
            ->where(function ($q) use ($installationType) {
                $q->where('installation_type', $installationType)
                  ->orWhere('installation_type', 'all');
            })
            ->where(function ($q) use ($serviceType) {
                $q->where('service_type', $serviceType)
                  ->orWhere('service_type', 'all');
            })
            ->orderBy('order')
            ->first();
    }
}
