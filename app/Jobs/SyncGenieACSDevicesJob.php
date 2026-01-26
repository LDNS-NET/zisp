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
        // 1. Discover new devices that have joined GenieACS
        $discovered = $service->discoverNewDevices();
        if ($discovered > 0) {
            Log::info("GenieACS Sync: Discovered {$discovered} new devices.");
        }

        // 2. Sync existing devices
        $devices = TenantDevice::all();
        foreach ($devices as $device) {
            try {
                $service->syncDevice($device);
            } catch (\Exception $e) {
                Log::error("Failed to sync device {$device->serial_number}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
