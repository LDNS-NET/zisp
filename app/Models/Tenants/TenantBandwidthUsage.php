<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBandwidthUsage extends Model
{
    protected $fillable = [
        'router_id',
        'interface_name',
        'bytes_in',
        'bytes_out',
        'packets_in',
        'packets_out',
        'timestamp',
        // 'tenant_id', // Column does not exist in migration
    ];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            $tenantId = null;
            
            if (tenant()) {
                $tenantId = tenant()->id;
            } elseif (auth()->check()) {
                $user = auth()->user();
                // Check if user is NOT super admin (super admins might want to see all, or specific tenant context)
                // If is_super_admin is true, usually we might not scope, but if they are simulating a tenant...
                // existing logic logic had: if (!$user->is_super_admin && $user->tenant_id)
                if (!$user->is_super_admin && $user->tenant_id) {
                    $tenantId = $user->tenant_id;
                }
            } else {
                // Fallback for public routes
                $host = request()->getHost();
                $subdomain = explode('.', $host)[0];
                $centralDomains = config('tenancy.central_domains', []);
                
                if (!in_array($host, $centralDomains)) {
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $tenantId = $tenant->id;
                    }
                }
            }

            if ($tenantId) {
                // Determine tenant via the related router
                $query->whereHas('router', function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                });
            }
        });

        // 'creating' event removed as tenant_id column does not exist
    }

    protected $casts = [
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'packets_in' => 'integer',
        'packets_out' => 'integer',
        'timestamp' => 'datetime',
    ];

    // Relationships
    public function router(): BelongsTo
    {
        return $this->belongsTo(TenantMikrotik::class, 'router_id');
    }

    // Helper methods
    public function getBytesInFormatted(): string
    {
        return $this->formatBytes($this->bytes_in);
    }

    public function getBytesOutFormatted(): string
    {
        return $this->formatBytes($this->bytes_out);
    }

    private function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
