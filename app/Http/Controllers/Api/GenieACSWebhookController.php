<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantDevice;
use App\Models\Tenants\TenantDeviceLog;
use App\Models\Tenants\TenantDeviceAction;
use App\Services\GenieACSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GenieACSWebhookController extends Controller
{
    protected GenieACSService $genieACS;

    public function __construct(GenieACSService $genieACS)
    {
        $this->genieACS = $genieACS;
    }

    /**
     * Handle GenieACS webhook events.
     */
    public function handle(Request $request)
    {
        $event = $request->input('event');
        $deviceId = $request->input('deviceId');
        
        Log::info('GenieACS Webhook received', [
            'event' => $event,
            'deviceId' => $deviceId,
            'payload' => $request->all()
        ]);
        
        switch ($event) {
            case 'inform':
                $this->handleInform($deviceId, $request->all());
                break;
            case 'offline':
                $this->handleOffline($deviceId);
                break;
            case 'task_completed':
                $this->handleTaskCompleted($deviceId, $request->input('taskId'));
                break;
            case 'task_failed':
                $this->handleTaskFailed($deviceId, $request->input('taskId'), $request->input('error'));
                break;
            default:
                Log::warning('Unknown GenieACS webhook event', ['event' => $event]);
        }
        
        return response()->json(['status' => 'ok']);
    }

    /**
     * Get device configuration for provisioning.
     */
    public function getDeviceConfig(Request $request)
    {
        $serial = $request->get('serial');
        
        // Search across all tenants for this device
        $device = TenantDevice::withoutGlobalScopes()
            ->where('serial_number', $serial)
            ->with('subscriber')
            ->first();
        
        if (!$device || !$device->subscriber) {
            return response()->json(['error' => 'Device not provisioned yet'], 404);
        }
        
        // Return configuration for this device
        return response()->json([
            'subscriber_id' => $device->subscriber_id,
            'tenant_id' => $device->tenant_id,
            'wifi_ssid' => $device->subscriber->wifi_ssid ?? 'ZISP-' . $device->subscriber->id,
            'wifi_password' => $device->subscriber->wifi_password ?? 'changeme',
            'pppoe_username' => $device->subscriber->username ?? '',
            'pppoe_password' => $device->subscriber->password ? decrypt($device->subscriber->password) : '',
            'bandwidth_download' => $device->subscriber->package->download_speed ?? null,
            'bandwidth_upload' => $device->subscriber->package->upload_speed ?? null,
        ]);
    }

    private function handleInform(string $deviceId, array $data)
    {
        $device = TenantDevice::withoutGlobalScopes()
            ->where('serial_number', $deviceId)
            ->first();
            
        if ($device) {
            $device->update([
                'online' => true,
                'last_contact_at' => now()
            ]);
            
            TenantDeviceLog::create([
                'tenant_device_id' => $device->id,
                'log_type' => 'info',
                'message' => 'Device connected (Inform received)',
                'raw_payload' => $data
            ]);
        }
    }

    private function handleOffline(string $deviceId)
    {
        $device = TenantDevice::withoutGlobalScopes()
            ->where('serial_number', $deviceId)
            ->first();
            
        if ($device) {
            $device->update(['online' => false]);
            
            TenantDeviceLog::create([
                'tenant_device_id' => $device->id,
                'log_type' => 'warning',
                'message' => 'Device went offline',
                'raw_payload' => ['timestamp' => now()]
            ]);
        }
    }

    private function handleTaskCompleted(string $deviceId, ?string $taskId)
    {
        $device = TenantDevice::withoutGlobalScopes()
            ->where('serial_number', $deviceId)
            ->first();
            
        if ($device && $taskId) {
            $action = TenantDeviceAction::where('genieacs_task_id', $taskId)->first();
            
            if ($action) {
                $action->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
                
                TenantDeviceLog::create([
                    'tenant_device_id' => $device->id,
                    'log_type' => 'success',
                    'message' => "Action '{$action->action}' completed successfully",
                    'raw_payload' => ['task_id' => $taskId]
                ]);
            }
        }
    }

    private function handleTaskFailed(string $deviceId, ?string $taskId, ?string $error)
    {
        $device = TenantDevice::withoutGlobalScopes()
            ->where('serial_number', $deviceId)
            ->first();
            
        if ($device && $taskId) {
            $action = TenantDeviceAction::where('genieacs_task_id', $taskId)->first();
            
            if ($action) {
                $action->update([
                    'status' => 'failed',
                    'error_message' => $error,
                    'completed_at' => now()
                ]);
                
                TenantDeviceLog::create([
                    'tenant_device_id' => $device->id,
                    'log_type' => 'error',
                    'message' => "Action '{$action->action}' failed: {$error}",
                    'raw_payload' => ['task_id' => $taskId, 'error' => $error]
                ]);
            }
        }
    }
}
