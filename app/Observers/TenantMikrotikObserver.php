<?php

namespace App\Observers;

use App\Models\Tenants\TenantMikrotik;
use App\Jobs\ApplyWireGuardPeer;
use App\Jobs\RemoveWireGuardPeer;
use Illuminate\Support\Facades\Log;

class TenantMikrotikObserver
{
    /**
     * Handle the TenantMikrotik "updated" event.
     * Triggered when a router's WireGuard configuration changes
     */
    public function updated(TenantMikrotik $router): void
    {
        // Check if automatic sync is enabled
        if (!config('wireguard.auto_sync_enabled', true)) {
            return;
        }

        // Check if WireGuard-related fields changed
        $wireguardFieldsChanged = $router->wasChanged([
            'wireguard_public_key',
            'wireguard_address',
            'wireguard_allowed_ips',
        ]);

        if ($wireguardFieldsChanged && !empty($router->wireguard_public_key)) {
            Log::channel('wireguard')->info('WireGuard fields changed, dispatching sync job', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'changes' => $router->getChanges(),
            ]);

            // Dispatch job to apply peer
            ApplyWireGuardPeer::dispatch($router);
        }
    }

    /**
     * Handle the TenantMikrotik "deleted" event.
     * Remove peer from WireGuard when router is deleted
     */
    public function deleted(TenantMikrotik $router): void
    {
        // Check if automatic sync is enabled
        if (!config('wireguard.auto_sync_enabled', true)) {
            return;
        }

        if (!empty($router->wireguard_public_key)) {
            Log::channel('wireguard')->info('Router deleted, dispatching removal job', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'public_key' => substr($router->wireguard_public_key, 0, 16) . '...',
            ]);

            // Dispatch job to remove peer
            RemoveWireGuardPeer::dispatch($router);
        }
    }

    /**
     * Handle the TenantMikrotik "created" event.
     * If a router is created with WireGuard keys already set, sync immediately
     */
    public function created(TenantMikrotik $router): void
    {
        // Check if automatic sync is enabled
        if (!config('wireguard.auto_sync_enabled', true)) {
            return;
        }

        if (!empty($router->wireguard_public_key)) {
            Log::channel('wireguard')->info('New router created with WireGuard key, dispatching sync job', [
                'router_id' => $router->id,
                'router_name' => $router->name,
            ]);

            // Dispatch job to apply peer
            ApplyWireGuardPeer::dispatch($router);
        }
    }
}
