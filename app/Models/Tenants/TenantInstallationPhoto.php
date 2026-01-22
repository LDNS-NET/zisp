<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class TenantInstallationPhoto extends Model
{
    protected $table = 'tenant_installation_photos';

    protected $fillable = [
        'tenant_id',
        'installation_id',
        'photo_path',
        'photo_type',
        'caption',
        'latitude',
        'longitude',
        'taken_at',
        'uploaded_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'taken_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

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

            if (auth()->check() && empty($model->uploaded_by)) {
                $model->uploaded_by = auth()->id();
            }

            if (empty($model->taken_at)) {
                $model->taken_at = now();
            }
        });

        static::deleting(function ($model) {
            // Delete the physical file when the record is deleted
            if (Storage::disk('public')->exists($model->photo_path)) {
                Storage::disk('public')->delete($model->photo_path);
            }
        });
    }

    // Relationships
    public function installation()
    {
        return $this->belongsTo(TenantInstallation::class, 'installation_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Accessors
    public function getPhotoUrlAttribute()
    {
        if (str_starts_with($this->photo_path, 'http')) {
            return $this->photo_path;
        }
        return Storage::disk('public')->url($this->photo_path);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('photo_type', $type);
    }

    public function scopeForInstallation($query, $installationId)
    {
        return $query->where('installation_id', $installationId);
    }
}
