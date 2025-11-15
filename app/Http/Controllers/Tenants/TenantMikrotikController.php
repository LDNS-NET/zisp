<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikScriptGenerator;
use App\Services\MikrotikConnectionService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TenantMikrotikController extends Controller
{
    public function __construct(
        protected MikrotikScriptGenerator $scriptGenerator,
        protected MikrotikConnectionService $connectionService
    ) {}

    /**
     * Display all Mikrotik devices
     */
    public function index()
    {
        $tenantMikrotiks = TenantMikrotik::with('creator')
            ->get()
            ->map(function ($mikrotik) {
                return [
                    'id' => $mikrotik->id,
                    'name' => $mikrotik->name,
                    'hostname' => $mikrotik->hostname,
                    'ip_address' => $mikrotik->ip_address,
                    'status' => $mikrotik->status,
                    'onboarding_status' => $mikrotik->onboarding_status,
                    'last_seen_at' => $mikrotik->last_seen_at,
                    'is_online' => $mikrotik->isOnline(),
                    'created_at' => $mikrotik->created_at,
                    'onboarding_script_url' => route('mikrotiks.download-script', $mikrotik->id),
                ];
            });

        return Inertia::render('Mikrotiks/Index', [
            'mikrotiks' => $tenantMikrotiks,
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Mikrotiks/Create');
    }

    /**
     * Store a new Mikrotik device
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hostname' => 'nullable|string|max:255',
        ]);

        $mikrotik = TenantMikrotik::create($validated);

        // Generate and store the onboarding script
        $systemUrl = config('app.url');
        $this->scriptGenerator->storeScript($mikrotik, $systemUrl);

        return redirect()->route('mikrotiks.show', $mikrotik->id)
            ->with('success', 'Device created. Download the onboarding script to begin setup.');
    }

    /**
     * Show device details
     */
    public function show(TenantMikrotik $mikrotik)
    {
        return Inertia::render('Mikrotiks/Show', [
            'mikrotik' => [
                'id' => $mikrotik->id,
                'name' => $mikrotik->name,
                'hostname' => $mikrotik->hostname,
                'ip_address' => $mikrotik->ip_address,
                'api_port' => $mikrotik->api_port,
                'status' => $mikrotik->status,
                'onboarding_status' => $mikrotik->onboarding_status,
                'last_seen_at' => $mikrotik->last_seen_at,
                'last_connected_at' => $mikrotik->last_connected_at,
                'device_id' => $mikrotik->device_id,
                'board_name' => $mikrotik->board_name,
                'system_version' => $mikrotik->system_version,
                'interface_count' => $mikrotik->interface_count,
                'sync_token' => $mikrotik->sync_token,
                'onboarding_token' => $mikrotik->onboarding_token,
                'sync_attempts' => $mikrotik->sync_attempts,
                'connection_failures' => $mikrotik->connection_failures,
                'last_error' => $mikrotik->last_error,
                'is_online' => $mikrotik->isOnline(),
                'download_script_url' => route('mikrotiks.download-script', $mikrotik->id),
                'created_at' => $mikrotik->created_at,
                'updated_at' => $mikrotik->updated_at,
            ],
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(TenantMikrotik $mikrotik)
    {
        return Inertia::render('Mikrotiks/Edit', [
            'mikrotik' => [
                'id' => $mikrotik->id,
                'name' => $mikrotik->name,
                'hostname' => $mikrotik->hostname,
                'ip_address' => $mikrotik->ip_address,
                'api_port' => $mikrotik->api_port,
                'api_username' => $mikrotik->api_username,
            ],
        ]);
    }

    /**
     * Update device details
     */
    public function update(Request $request, TenantMikrotik $mikrotik)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hostname' => 'nullable|string|max:255',
            'ip_address' => 'nullable|ip',
            'api_port' => 'nullable|integer|between:1,65535',
            'api_username' => 'nullable|string|max:255',
            'api_password' => 'nullable|string',
        ]);

        $mikrotik->update($validated);

        return redirect()->route('mikrotiks.show', $mikrotik->id)
            ->with('success', 'Device updated successfully.');
    }

    /**
     * Delete a device
     */
    public function destroy(TenantMikrotik $mikrotik)
    {
        $mikrotik->delete();

        return redirect()->route('mikrotiks.index')
            ->with('success', 'Device deleted successfully.');
    }

    /**
     * Download the onboarding script
     */
    public function downloadScript(TenantMikrotik $mikrotik)
    {
        if (!$mikrotik->onboarding_script_content) {
            $systemUrl = config('app.url');
            $this->scriptGenerator->storeScript($mikrotik, $systemUrl);
        }

        return response($mikrotik->onboarding_script_content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="' . $this->scriptGenerator->getScriptFilename($mikrotik) . '"');
    }

    /**
     * Public sync endpoint - called by the onboarding script and phone-home scheduler
     * Token-based authentication (no session required)
     */
    public function sync(Request $request, TenantMikrotik $mikrotik)
    {
        $token = $request->query('token');

        // Verify sync token
        if (!$token || $token !== $mikrotik->sync_token) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        try {
            $data = $request->all();
            
            // Check if this is a phone-home ping (minimal data) or full sync
            $isPhoneHome = empty($data['device_id']) && empty($data['board_name']) && !isset($data['system_version']);

            if ($isPhoneHome) {
                // Phone-home: Just update status to keep device online
                $mikrotik->update([
                    'last_seen_at' => now(),
                    'status' => 'connected',
                    'last_error' => null,
                ]);

                \Log::debug("Mikrotik phone-home ping for device {$mikrotik->id}");

                return response()->json([
                    'success' => true,
                    'message' => 'Status updated',
                ]);
            }

            // Full sync: Update device information from sync payload
            $updates = [
                'device_id' => $data['device_id'] ?? null,
                'board_name' => $data['board_name'] ?? null,
                'interface_count' => $data['interface_count'] ?? null,
                'sync_attempts' => ($mikrotik->sync_attempts ?? 0) + 1,
                'last_seen_at' => now(),
                'status' => 'connected',
                'last_error' => null,
            ];

            if (isset($data['system_version'])) {
                $updates['system_version'] = $data['system_version'];
            }

            if (isset($data['ip_address'])) {
                $updates['ip_address'] = $data['ip_address'];
            }

            $mikrotik->update($updates);

            // If first successful sync, mark onboarding as in progress
            if ($mikrotik->onboarding_status === 'not_started') {
                $mikrotik->update(['onboarding_status' => 'in_progress']);
            }

            // Check if all required data is present for completion
            if ($data['device_id'] && $data['board_name']) {
                $mikrotik->completeOnboarding();
            }

            \Log::info("Mikrotik sync successful for device {$mikrotik->id}", $updates);

            return response()->json([
                'success' => true,
                'message' => 'Device synced successfully',
                'device_id' => $mikrotik->id,
            ]);
        } catch (\Exception $e) {
            $mikrotik->failOnboarding($e->getMessage());
            \Log::error("Mikrotik sync failed for device {$mikrotik->id}: {$e->getMessage()}");

            return response()->json([
                'error' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate onboarding tokens and script
     */
    public function regenerateScript(TenantMikrotik $mikrotik)
    {
        $mikrotik->regenerateTokens();
        $systemUrl = config('app.url');
        $this->scriptGenerator->storeScript($mikrotik, $systemUrl);

        return back()->with('success', 'Onboarding script regenerated. Device must run the new script to reconnect.');
    }

    /**
     * Test API connection to the device
     */
    public function testConnection(TenantMikrotik $mikrotik)
    {
        if ($this->connectionService->testConnection($mikrotik)) {
            $info = $this->connectionService->getDeviceInfo($mikrotik);
            
            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'device_info' => $info,
            ]);
        }

        return response()->json([
            'error' => 'Connection failed: ' . ($mikrotik->last_error ?? 'Unknown error'),
        ], 500);
    }

    /**
     * Get device status including interface and IP information
     */
    public function status(TenantMikrotik $mikrotik)
    {
        return response()->json([
            'status' => $mikrotik->status,
            'is_online' => $mikrotik->isOnline(),
            'last_seen_at' => $mikrotik->last_seen_at,
            'interfaces' => $this->connectionService->getInterfaceStatus($mikrotik),
            'ip_addresses' => $this->connectionService->getIPAddresses($mikrotik),
            'has_wireless' => $this->connectionService->hasWireless($mikrotik),
        ]);
    }

    /**
     * Mark device as manually offline (for testing/admin purposes)
     */
    public function markOffline(TenantMikrotik $mikrotik)
    {
        $mikrotik->markDisconnected();

        return back()->with('success', 'Device marked as offline.');
    }

    /**
     * Mark device as manually online (for testing/admin purposes)
     */
    public function markOnline(TenantMikrotik $mikrotik)
    {
        $mikrotik->markConnected();

        return back()->with('success', 'Device marked as online.');
    }
}