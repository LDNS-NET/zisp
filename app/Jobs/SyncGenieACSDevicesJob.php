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

        // 2. Fetch all known MikroTik IPs across all potential contexts
        $allMikrotikIps = \App\Models\Tenants\TenantMikrotik::query()
            ->select('public_ip', 'wireguard_address', 'tenant_id')
            ->get();

        Log::info("TR-069 Sync: Processing " . count($remoteDevices) . " remote devices.");

        foreach ($remoteDevices as $remote) {
            $serial = $remote['_id'] ?? null;
            if (!$serial) continue;

            $deviceSourceIp = $remote['_ip'] ?? null;
            $targetTenantId = null;

            // Find which tenant this device belongs to (Match by IP)
            if ($deviceSourceIp) {
                $match = $allMikrotikIps->where('public_ip', $deviceSourceIp)
                    ->orWhere('wireguard_address', $deviceSourceIp)
                    ->first();
                
                if ($match) {
                    $targetTenantId = $match->tenant_id;
                }
            }

            // CHECK: Does this device already exist?
            $device = TenantDevice::where('serial_number', $serial)->first();

            if ($device) {
                $service->syncDevice($device, $remote);
                Log::debug("TR-069 Sync: Updated existing device {$serial}");
            } else {
                // DISCOVER NEW DEVICE
                // Even if we don't know the tenant yet, we create it so the user can see it
                $newDevice = TenantDevice::create([
                    'serial_number' => $serial,
                    'tenant_id' => $targetTenantId, // Might be null if behind unknown NAT
                    'online' => true,
                    'last_contact_at' => now(),
                ]);
                
                $service->syncDevice($newDevice, $remote);
                Log::info("TR-069 Discovery: Found new device {$serial} (Assigned to Tenant: " . ($targetTenantId ?? 'Global/Pending') . ")");
            }
        }
    }
}
