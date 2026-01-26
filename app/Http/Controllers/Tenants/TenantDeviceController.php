<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantDevice;
use App\Models\Tenants\TenantDeviceAction;
use App\Models\Tenants\TenantDeviceLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantDeviceController extends Controller
{
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

        return Inertia::render('Devices/View', [
            'device' => $device,
        ]);
    }

    public function sync()
    {
        // TODO: Implement GenieACS API sync logic
        // This would involve fetching devices from GenieACS NBI
        // and updating/creating local TenantDevice records.
        
        return back()->with('success', 'Device synchronization started.');
    }

    public function action(Request $request, $id)
    {
        $device = TenantDevice::findOrFail($id);
        
        $validated = $request->validate([
            'action' => 'required|string|in:reboot,reset,update_wifi,update_pppoe',
            'payload' => 'nullable|array',
        ]);

        $action = TenantDeviceAction::create([
            'tenant_device_id' => $device->id,
            'action' => $validated['action'],
            'payload' => $validated['payload'] ?? [],
            'status' => 'pending',
        ]);

        // TODO: Dispatch Job to push action to GenieACS
        
        return back()->with('success', "Action '{$validated['action']}' queued successfully.");
    }

    public function destroy($id)
    {
        $device = TenantDevice::findOrFail($id);
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Device removed successfully.');
    }
}
