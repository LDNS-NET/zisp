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

        // 2. Collect ALL Mikrotik IPs from the system (for fast lookup)
        $allMikrotiks = \App\Models\Tenants\TenantMikrotik::withoutGlobalScopes()
            ->select('id', 'tenant_id', 'public_ip', 'detected_public_ip', 'wireguard_address')
            ->get();

        Log::info("TR-069 Sync: Processing " . count($remoteDevices) . " remote devices against " . count($allMikrotiks) . " known MikroTiks.");

        foreach ($remoteDevices as $remote) {
            $serial = $remote['_id'] ?? null;
            if (!$serial) continue;

            $deviceSourceIp = $remote['_ip'] ?? null;
            $serial = $remote['_id'] ?? null;
            $matchedMikrotik = null;

            // 1. Try to match by Serial Number (if the TR-069 device is the router itself)
            if ($serial) {
                // GenieACS IDs often have URL encoding like %2D for -
                $cleanSerial = urldecode($serial);
                $matchedMikrotik = $allMikrotiks->filter(function($m) use ($cleanSerial) {
                    return $m->serial_number && (
                        $m->serial_number === $cleanSerial || 
                        str_contains($cleanSerial, $m->serial_number)
                    );
                })->first();
            }

            // 2. Try to match by Public IP
            if (!$matchedMikrotik && $deviceSourceIp) {
                $matchedMikrotik = $allMikrotiks->filter(function($m) use ($deviceSourceIp) {
                    return $m->detected_public_ip === $deviceSourceIp || 
                           $m->public_ip === $deviceSourceIp || 
                           $m->wireguard_address === $deviceSourceIp;
                })->first();
            }

            // 3. Try Deep Discovery (if we still have no match, check subnets - optional/slow but good for local ACS)
            // For now we keep it to Public IP and Serial matching for the global job.
            // Site-specific deep scan is handled by the manual button or FetchDevicesBehindRouter command.

            // Determine context (Tenant or Central)
            $tenantId = $matchedMikrotik?->tenant_id;
            
            if ($tenantId) {
                $tenant = \App\Models\Tenant::find($tenantId);
                if ($tenant) {
                    $tenant->run(function() use ($service, $remote, $serial) {
                        $this->syncToContext($service, $remote, $serial, tenant('id'));
                    });
                    continue;
                }
            }

            // Fallback: Sync to Central context (Discovery Mode)
            $this->syncToContext($service, $remote, $serial, null);
        }
    }

    private function syncToContext(GenieACSService $service, array $remote, string $serial, ?string $tenantId): void
    {
        $device = TenantDevice::where('serial_number', $serial)->first();

        if (!$device) {
            $device = TenantDevice::create([
                'serial_number' => $serial,
                'tenant_id' => $tenantId,
                'online' => true,
                'last_contact_at' => now(),
            ]);
        }

        $service->syncDevice($device, $remote);
        Log::info("TR-069: Synced device {$serial} to " . ($tenantId ? "Tenant {$tenantId}" : "Central Discovery"));
    }
}
