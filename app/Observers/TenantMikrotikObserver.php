<?php

namespace App\Observers;

use App\Models\Tenants\TenantMikrotik;
use App\Jobs\ApplyWireGuardPeer;
use App\Jobs\RemoveWireGuardPeer;
use Illuminate\Support\Facades\Log;
use App\Models\Radius\Nas;

class TenantMikrotikObserver
{
    /**
     * Handle the TenantMikrotik "updated" event.
     * Sync WireGuard peer changes and ensure RADIUS NAS entry stays updated.
     */
    public function updated(TenantMikrotik $router): void
    {
        // ---------------- WireGuard peer sync ----------------
        if (config('wireguard.auto_sync_enabled', true)) {
            $wireguardFieldsChanged = $router->wasChanged([
                'wireguard_public_key',
                'wireguard_address',
                'wireguard_allowed_ips',
            ]);

            if ($wireguardFieldsChanged && !empty($router->wireguard_public_key)) {
                Log::channel('wireguard')->info('WireGuard fields changed, dispatching sync job', [
                    'router_id'   => $router->id,
                    'router_name' => $router->name,
                    'changes'     => $router->getChanges(),
                ]);

                // Dispatch job to apply peer
                ApplyWireGuardPeer::dispatch($router);
            }
        }

        // ---------------- RADIUS NAS sync ----------------
        if ($router->wasChanged(['wireguard_address', 'api_password'])) {
            $this->syncRadiusNas($router);
        }
    }

    /**
     * Handle the TenantMikrotik "deleted" event.
     * Remove peer from WireGuard when router is deleted
     */
    public function deleted(TenantMikrotik $router): void
    {
        // Remove associated RADIUS NAS entry
        Nas::where('shortname', 'mtk-' . $router->id)->delete();

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
        // Sync RADIUS NAS entry on creation (if VPN IP already set)
        $this->syncRadiusNas($router);

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

    /**
     * Create or update the router entry in the RADIUS `nas` table.
     */
    private function syncRadiusNas(TenantMikrotik $router): void
    {
        try {
            $nasIp = $router->wireguard_address;

            // No VPN IP yet – nothing to sync.
            if (empty($nasIp)) {
                return;
            }

            $shortname    = 'mtk-' . $router->id;
            $secret       = $router->api_password ?: env('RADIUS_SECRET', 'testing123');
            $radiusServer = config('radius.server', 'default');

            $nas = Nas::where('shortname', $shortname)->first();

            if ($nas) {
                $nas->update([
                    'nasname'     => $nasIp,
                    'secret'      => $secret,
                    'type'        => 'mikrotik',
                    'server'      => $radiusServer,
                    'description' => "Tenant router {$router->id} - {$router->name}",
                ]);
                Log::info("Updated NAS entry for router {$router->id}");
            } else {
                Nas::create([
                    'nasname'     => $nasIp,
                    'shortname'   => $shortname,
                    'type'        => 'mikrotik',
                    'secret'      => $secret,
                    'server'      => $radiusServer,
                    'description' => "Tenant router {$router->id} - {$router->name}",
                ]);
                Log::info("Created NAS entry for router {$router->id}");
            }
        } catch (\Exception $e) {
            Log::error('NAS sync failed', [
                'router_id' => $router->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
