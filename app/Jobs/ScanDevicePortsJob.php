<?php

namespace App\Jobs;

use App\Models\Tenants\TenantDevicePortScan;
use App\Services\GenieACSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScanDevicePortsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public TenantDevicePortScan $portScan
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(GenieACSService $genieACS): void
    {
        try {
            $this->portScan->markAsRunning();

            $device = $this->portScan->device;

            Log::info("Starting port scan for device", [
                'device_id' => $device->id,
                'serial' => $device->serial_number,
                'scan_id' => $this->portScan->id,
            ]);

            // Scan ports using GenieACS
            $ports = $genieACS->scanPorts($device->genieacs_id);

            if (empty($ports)) {
                Log::warning("No ports found for device", [
                    'device_id' => $device->id,
                    'serial' => $device->serial_number,
                ]);
            }

            $this->portScan->markAsCompleted($ports);

            Log::info("Port scan completed successfully", [
                'device_id' => $device->id,
                'ports_found' => count($ports),
                'scan_id' => $this->portScan->id,
            ]);

        } catch (\Exception $e) {
            Log::error("Port scan failed", [
                'device_id' => $this->portScan->tenant_device_id,
                'scan_id' => $this->portScan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->portScan->markAsFailed($e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->portScan->markAsFailed($exception->getMessage());
    }
}
