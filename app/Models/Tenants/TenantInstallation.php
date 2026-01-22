<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class TenantInstallation extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_installations';

    protected $fillable = [
        'tenant_id',
        'network_user_id',
        'technician_id',
        'equipment_id',
        'installation_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'installation_address',
        'latitude',
        'longitude',
        'installation_type',
        'service_type',
        'status',
        'priority',
        'scheduled_date',
        'scheduled_time',
        'started_at',
        'completed_at',
        'estimated_duration',
        'actual_duration',
        'installation_notes',
        'technician_notes',
        'customer_feedback',
        'customer_rating',
        'checklist_data',
        'equipment_installed',
        'installation_cost',
        'payment_collected',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'checklist_data' => 'array',
        'equipment_installed' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'installation_cost' => 'decimal:2',
        'payment_collected' => 'boolean',
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

            if (empty($model->installation_number)) {
                $model->installation_number = 'INST-' . strtoupper(uniqid());
            }

            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }

            // Auto-calculate duration when completed
            if ($model->isDirty('status') && $model->status === 'completed' && $model->started_at) {
                $model->completed_at = now();
                $model->actual_duration = $model->started_at->diffInMinutes($model->completed_at);
            }
        });
    }

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function networkUser()
    {
        return $this->belongsTo(NetworkUser::class, 'network_user_id');
    }

    public function technician()
    {
        return $this->belongsTo(TenantTechnician::class, 'technician_id');
    }

    public function equipment()
    {
        return $this->belongsTo(TenantEquipment::class, 'equipment_id');
    }

    public function photos()
    {
        return $this->hasMany(TenantInstallationPhoto::class, 'installation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', now()->toDateString());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_date', '>=', now()->toDateString())
            ->whereIn('status', ['scheduled', 'in_progress']);
    }

    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Helpers
    public function start()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function complete($technicianNotes = null, $equipmentInstalled = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'technician_notes' => $technicianNotes ?? $this->technician_notes,
            'equipment_installed' => $equipmentInstalled ?? $this->equipment_installed,
        ]);

        // Update technician stats
        if ($this->technician) {
            $this->technician->increment('completed_installations');
            $this->technician->updateRating();
        }
    }

    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'technician_notes' => $reason ?? $this->technician_notes,
        ]);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'scheduled' => 'blue',
            'in_progress' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            'on_hold' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'urgent' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }
}
