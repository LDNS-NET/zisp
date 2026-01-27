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
                $params = [];
                $ssid = $this->action->payload['ssid'] ?? '';
                $pass = $this->action->payload['password'] ?? '';
                
                // Try both TR-098 and TR-181 paths (GenieACS will ignore invalid ones for the device)
                $params['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID'] = $ssid;
                $params['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase'] = $pass;
                $params['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey'] = $pass;
                $params['Device.WiFi.SSID.1.SSID'] = $ssid;
                $params['Device.WiFi.AccessPoint.1.Security.KeyPassphrase'] = $pass;
                
                $success = $service->setParameterValues($deviceId, $params);
                break;
            case 'update_pppoe':
                $params = [];
                $user = $this->action->payload['username'] ?? '';
                $pass = $this->action->payload['password'] ?? '';
                
                $params['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Username'] = $user;
                $params['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Password'] = $pass;
                $params['Device.PPP.Interface.1.Username'] = $user;
                $params['Device.PPP.Interface.1.Password'] = $pass;
                
                $success = $service->setParameterValues($deviceId, $params);
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
