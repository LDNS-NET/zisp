<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantMikrotik extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'hostname',
        'ip_address',
        'api_port',
        'api_username',
        'api_password',
        'sync_token',
        'onboarding_token',
        'status',
        'onboarding_status',
        'onboarding_completed_at',
        'last_connected_at',
        'last_seen_at',
        'onboarding_script_url',
        'onboarding_script_content',
        'device_id',
        'board_name',
        'system_version',
        'interface_count',
        'last_error',
        'sync_attempts',
        'connection_failures',
        'created_by',
    ];

    protected $casts = [
        'onboarding_completed_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'api_password' => 'encrypted',
    ];

    /**
     * Boot the model
     */
    protected static function booted()
    {
        static::addGlobalScope('created_by', function ($query) {
            if (auth()->check()) {
                $query->where('created_by', auth()->id());
            }
        });

        static::creating(function ($model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
            // Set tenant_id if tenancy context is available
            if (function_exists('tenant') && empty($model->tenant_id)) {
                $tenantId = tenant('id');
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
            
            // Generate unique tokens
            if (empty($model->sync_token)) {
                $model->sync_token = Str::random(64);
            }
            if (empty($model->onboarding_token)) {
                $model->onboarding_token = Str::random(64);
            }
        });

        static::updating(function ($model) {
            // Reset sync attempt counter after 4 minutes of no activity
            if ($model->last_seen_at && $model->last_seen_at->diffInMinutes(now()) > 4) {
                $model->sync_attempts = 0;
            }
        });
    }

    /**
     * Get the user who created this device
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get onboarding script content
     */
    public function getOnboardingScript(): string
    {
        return $this->onboarding_script_content ?? '';
    }

    /**
     * Check if device is online
     */
    public function isOnline(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->diffInMinutes(now()) <= 4;
    }

    /**
     * Mark as connected
     */
    public function markConnected(): void
    {
        $this->update([
            'status' => 'connected',
            'last_seen_at' => now(),
            'last_connected_at' => now(),
            'connection_failures' => 0,
            'last_error' => null,
        ]);
    }

    /**
     * Mark as disconnected
     */
    public function markDisconnected(): void
    {
        $this->update([
            'status' => 'disconnected',
            'connection_failures' => $this->connection_failures + 1,
        ]);
    }

    /**
     * Mark onboarding as completed
     */
    public function completeOnboarding(): void
    {
        $this->update([
            'onboarding_status' => 'completed',
            'onboarding_completed_at' => now(),
            'status' => 'connected',
            'last_seen_at' => now(),
            'sync_attempts' => 0,
        ]);
    }

    /**
     * Mark onboarding as failed
     */
    public function failOnboarding(string $error): void
    {
        $this->update([
            'onboarding_status' => 'failed',
            'last_error' => $error,
            'sync_attempts' => $this->sync_attempts + 1,
        ]);
    }

    /**
     * Reset onboarding tokens for re-onboarding
     */
    public function regenerateTokens(): void
    {
        $this->update([
            'sync_token' => Str::random(64),
            'onboarding_token' => Str::random(64),
            'onboarding_status' => 'not_started',
            'sync_attempts' => 0,
            'connection_failures' => 0,
        ]);
    }
}
