<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Jobs\PushDeviceActionJob;
use App\Jobs\SyncGenieACSDevicesJob;
use App\Models\Tenants\TenantDevice;
use App\Models\Tenants\TenantDeviceAction;
use App\Services\GenieACSService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantDeviceController extends Controller
{
    protected GenieACSService $genieACS;

    public function __construct(GenieACSService $genieACS)
    {
        $this->genieACS = $genieACS;
    }

    public function index(Request $request)
    {
        $devices = TenantDevice::with('subscriber')
            ->when($request->search, function ($q) use ($request) {
                $q->where('serial_number', 'like', "%{$request->search}%")
                  ->orWhere('model', 'like', "%{$request->search}%")
                  ->orWhere('manufacturer', 'like', "%{$request->search}%");
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        return Inertia::render('Devices/Index', [
            'devices' => $devices,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show($id)
    {
        $device = TenantDevice::with(['subscriber', 'logs' => function($q) {
            $q->latest()->limit(50);
        }, 'actions' => function($q) {
            $q->latest()->limit(50);
        }])->findOrFail($id);

        $subscribers = \App\Models\Tenants\NetworkUser::select('id', 'full_name', 'account_number', 'username')
            ->orderBy('full_name')
            ->get();

        return Inertia::render('Devices/View', [
            'device' => $device,
            'subscribers' => $subscribers,
        ]);
    }

    /**
     * Link a device to a specific subscriber.
     */
    public function linkSubscriber(Request $request, $id)
    {
        $device = TenantDevice::findOrFail($id);
        
        $validated = $request->validate([
            'subscriber_id' => 'nullable|exists:network_users,id',
        ]);

        $device->update([
            'subscriber_id' => $validated['subscriber_id']
        ]);

        return back()->with('success', 'Device successfully linked to subscriber.');
    }

    /**
     * Manually trigger a full sync across all devices.
     */
    public function sync()
    {
        SyncGenieACSDevicesJob::dispatch();
        
        return back()->with('success', 'Device synchronization started in background.');
    }

    /**
     * Queue a remote action/task for a device.
     */
    public function action(Request $request, $id)
    {
        $device = TenantDevice::findOrFail($id);
        
        $validated = $request->validate([
            'action' => 'required|string|in:reboot,reset,update_wifi,update_pppoe,sync_params',
            'payload' => 'nullable|array',
        ]);

        $action = TenantDeviceAction::create([
            'tenant_device_id' => $device->id,
            'action' => $validated['action'],
            'payload' => $validated['payload'] ?? [],
            'status' => 'pending',
        ]);

        // Dispatch the job to push this to GenieACS
        PushDeviceActionJob::dispatch($action);
        
        return back()->with('success', "Action '{$validated['action']}' queued and processing.");
    }

    public function destroy($id)
    {
        $device = TenantDevice::findOrFail($id);
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Device removed from local management.');
    }
}
