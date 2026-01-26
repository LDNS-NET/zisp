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
        
        // 2. Iterate through all Tenants in the system
        \App\Models\Tenant::all()->each(function ($tenant) use ($remoteDevices, $service) {
            $tenant->run(function () use ($remoteDevices, $service) {
                
                // Get all MikroTik IPs for this specific tenant
                $tenantMikrotikIps = \App\Models\Tenants\TenantMikrotik::pluck('ip_address', 'public_ip')
                    ->flatten()
                    ->filter()
                    ->toArray();

                foreach ($remoteDevices as $remote) {
                    $serial = $remote['_id'] ?? null;
                    if (!$serial) continue;

                    // Get the IP the device is connecting FROM (GenieACS metadata)
                    $deviceSourceIp = $remote['_ip'] ?? null;

                    // CHECK 1: Does this device already exist in this tenant?
                    $existingDevice = TenantDevice::where('serial_number', $serial)->first();

                    if ($existingDevice) {
                        // Just update its health/stats
                        $service->syncDevice($existingDevice, $remote);
                    } 
                    // CHECK 2: If new, does its connection IP match this tenant's MikroTiks?
                    elseif ($deviceSourceIp && in_array($deviceSourceIp, $tenantMikrotikIps)) {
                        $newDevice = TenantDevice::create([
                            'serial_number' => $serial,
                            'tenant_id' => tenant('id'),
                            'online' => true,
                            'last_contact_at' => now(),
                        ]);
                        $service->syncDevice($newDevice, $remote);
                        
                        Log::info("TR-069: Automatically discovered and assigned device {$serial} to tenant " . tenant('id'));
                    }
                }
            });
        });
    }
}
