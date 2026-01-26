<?php

namespace App\Jobs;

use App\Models\Tenants\TenantDeviceAction;
use App\Services\GenieACSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushDeviceActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected TenantDeviceAction $action;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantDeviceAction $action)
    {
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(GenieACSService $service): void
    {
        $device = $this->action->device;
        
        if (!$device) {
            $this->action->update(['status' => 'failed', 'error_message' => 'Device not found']);
            return;
        }

        $this->action->update(['status' => 'sent']);

        $success = false;
        $deviceId = $device->serial_number;

        switch ($this->action->action) {
            case 'reboot':
                $success = $service->reboot($deviceId);
                break;
            case 'reset':
                $success = $service->factoryReset($deviceId);
                break;
            case 'update_wifi':
                $success = $service->setParameterValues($deviceId, [
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID' => $this->action->payload['ssid'] ?? '',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey' => $this->action->payload['password'] ?? '',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BeaconType' => '11i',
                ]);
                break;
            case 'update_pppoe':
                $success = $service->setParameterValues($deviceId, [
                    'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Username' => $this->action->payload['username'] ?? '',
                    'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Password' => $this->action->payload['password'] ?? '',
                ]);
                break;
            case 'sync_params':
                $success = $service->syncDevice($device);
                break;
        }

        if ($success) {
            $this->action->update(['status' => 'completed']);
        } else {
            $this->action->update([
                'status' => 'failed', 
                'error_message' => 'GenieACS NBI rejected the task or was unreachable.'
            ]);
        }
    }
}
