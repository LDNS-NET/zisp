<?php

namespace App\Jobs;

use App\Models\Tenants\TenantDevice;
use App\Services\GenieACSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGenieACSDevicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(GenieACSService $service): void
    {
        // 1. Fetch ALL known devices from GenieACS NBI
        $remoteDevices = $service->getDevices();
        
        if (empty($remoteDevices)) {
            Log::info("TR-069 Sync: No devices found in GenieACS.");
            return;
        }

        // 2. Iterate through all Tenants
        \App\Models\Tenant::all()->each(function ($tenant) use ($remoteDevices, $service) {
            $tenant->run(function () use ($remoteDevices, $service) {
                
                // Get this tenant's MikroTiks IPs
                $tenantMikrotikIps = \App\Models\Tenants\TenantMikrotik::query()
                    ->select('public_ip', 'wireguard_address')
                    ->get()
                    ->flatMap(fn($m) => [$m->public_ip, $m->wireguard_address])
                    ->filter()
                    ->toArray();

                foreach ($remoteDevices as $remote) {
                    $serial = $remote['_id'] ?? null;
                    if (!$serial) continue;

                    $deviceSourceIp = $remote['_ip'] ?? null;
                    
                    // Match by IP or check if already in this tenant
                    $existing = TenantDevice::where('serial_number', $serial)->first();
                    
                    if ($existing || ($deviceSourceIp && in_array($deviceSourceIp, $tenantMikrotikIps))) {
                        if (!$existing) {
                            $existing = TenantDevice::create([
                                'serial_number' => $serial,
                                'tenant_id' => tenant('id'),
                                'online' => true,
                                'last_contact_at' => now(),
                            ]);
                        }
                        
                        $service->syncDevice($existing, $remote);
                        Log::info("TR-069: Synced device {$serial} for tenant " . tenant('id'));
                    }
                }
            });
        });

        // 3. Handle 'Global' (unassigned) devices in the Central Context
        foreach ($remoteDevices as $remote) {
            $serial = $remote['_id'] ?? null;
            if (!$serial) continue;

            // Check if it was picked up by any tenant
            $isAssigned = false;
            // Simplified check: since we're in central, we check if ANY tenant_devices row exists
            // This assumes the table exists in central too (for the global view)
            try {
                if (TenantDevice::where('serial_number', $serial)->exists()) {
                    continue;
                }

                TenantDevice::create([
                    'serial_number' => $serial,
                    'tenant_id' => null,
                    'online' => true,
                    'last_contact_at' => now(),
                ]);
                Log::info("TR-069: Discovered unassigned device {$serial}");
            } catch (\Exception $e) {
                // Table might not exist in central, ignore
            }
        }
    }
}
