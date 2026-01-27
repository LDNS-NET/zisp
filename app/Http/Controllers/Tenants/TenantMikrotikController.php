<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\{TenantMikrotik, TenantOpenVPNProfile, TenantRouterLog, TenantBandwidthUsage, TenantActiveUsers, TenantDevice};
use App\Models\Radius\Nas;
use App\Models\Tenants\TenantRouterAlert;
use App\Services\{MikrotikService, MikrotikScriptGenerator, TenantHotspotService};
use App\Models\Radius\Radacct;
use App\Services\Mikrotik\RouterApiService;
use App\Services\WinboxPortService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use Illuminate\Support\Str;
//use Illuminate\Support\Facades\ZipArchive;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Inertia\Inertia;

class TenantMikrotikController extends Controller
{
    /**
     * List all Mikrotiks for tenant.
     */
    public function index(Request $request)
    {
        $tenants = Tenant::orderBy('name')->get();

        $query = TenantMikrotik::with(['openvpnProfile', 'logs', 'bandwidthUsage', 'alerts'])
            ->orderByDesc('last_seen_at');

        if ($request->query('tab') === 'deleted') {
            $query->onlyTrashed();
        }

        $routers = $query->get();

        // Mark stale routers as offline (> 4 minutes since last_seen_at)
        foreach ($routers as $router) {
            if ($this->isRouterStale($router) && $router->status === 'online') {
                $router->status = 'offline';
                $router->save();
            }
        }

        // Refresh the collection to get updated statuses
        $routers = $routers->fresh();

        try {
            $openvpnProfiles = TenantOpenVPNProfile::where('status', 'active')
                ->orderBy('config_path')
                ->get();
        } catch (\Exception $e) {
            // If table doesn't exist or query fails, return empty collection
            $openvpnProfiles = collect([]);
        }

        return Inertia::render('Mikrotiks/Index', [
            'routers' => $routers,
            'openvpnProfiles' => $openvpnProfiles,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Show single router details.
     */
    /**
     * Show single router details.
     */
    public function show(Request $request, $id)
    {
        $router = TenantMikrotik::with(['openvpnProfile', 'logs', 'bandwidthUsage', 'activeSessions', 'alerts'])
            ->findOrFail($id);

        // Build real-time data defaults

        // Build realtime defaults from DB
        // Build realtime defaults from DB (TenantActiveUsers)
        $activeSessions = TenantActiveUsers::where('router_id', $router->id)
            ->where('status', 'active')
            ->get();
            
        $hotspotActiveDb = $activeSessions->filter(function($s) {
            return ($s->user_type ?? '') == 'hotspot' || preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $s->username);
        })->count();
        $pppoeActiveDb = $activeSessions->filter(function($s) {
            return ($s->user_type ?? '') == 'pppoe' || !preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $s->username);
        })->count();
        // If type is missing/unknown, we might want to count them too, but for now stick to known types or total
        // $totalActive = $activeSessions->count();

        $realtimeData = [
            'resources' => [
                'board-name' => $router->model,
                'architecture-name' => $router->architecture_name ?? null,
                'version' => $router->os_version,
                'build-time' => $router->build_time ?? null,
                'cpu-load' => $router->cpu_usage,
                'free-memory' => null,
                'total-memory' => null,
                'uptime' => $router->getUptimeFormatted(),
            ],
            'interfaces' => [],
            'hotspot_active' => $hotspotActiveDb,
            'pppoe_active' => $pppoeActiveDb,
            'wireguard_peers' => [],
            'router_logs' => [],
            'is_online' => $router->status === 'online',
        ];

        $force = $request->boolean('force');
        // Poll router if forced or cache is stale (>180s)
        $shouldPoll = $force || !$router->last_seen_at || now()->diffInSeconds($router->last_seen_at) > 180;

        if ($shouldPoll)
            try {
                $apiService = new RouterApiService($router);

                // Quick online check first
                if ($apiService->isOnline()) {
                    $realtimeData['is_online'] = true;

                    // Fetch data in parallel or sequence (sequence for now)
                    $realtimeData['resources'] = $apiService->getSystemResource();
                    $realtimeData['interfaces'] = $apiService->getInterfaces();
                    // $realtimeData['hotspot_active'] = $apiService->getHotspotActive(); // Removed live fetch
                    // $realtimeData['pppoe_active'] = $apiService->getPppoeActive(); // Removed live fetch
                    $realtimeData['wireguard_peers'] = $apiService->getWireGuardPeers();
                    $realtimeData['router_logs'] = $apiService->getLogs(20); // Get last 20 logs

                    // UPDATE DB STATS (for Show.vue display)
                    if (isset($realtimeData['resources']['cpu-load'])) {
                        $router->cpu_usage = $realtimeData['resources']['cpu-load'];
                    }
                    if (isset($realtimeData['resources']['free-memory']) && isset($realtimeData['resources']['total-memory'])) {
                        $router->memory_usage = round((1 - ($realtimeData['resources']['free-memory'] / $realtimeData['resources']['total-memory'])) * 100, 2);
                    }
                    if (isset($realtimeData['resources']['uptime'])) {
                        $router->uptime = (int)$realtimeData['resources']['uptime'];
                    }
                    if (isset($realtimeData['resources']['board-name'])) {
                        $router->model = $realtimeData['resources']['board-name'];
                    }
                    if (isset($realtimeData['resources']['version'])) {
                        $router->os_version = $realtimeData['resources']['version'];
                    }
                    // Update online status and last seen
                    $router->last_seen_at = now();
                    if ($router->status !== 'online') {
                        $router->status = 'online';
                        $router->online_since = now();
                    }
                    $router->save();
                }
            } catch (\Exception $e) {
                // Log error but continue to show page with cached/DB data
                Log::warning('Failed to fetch real-time data for router show page', [
                    'router_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }

        // Fetch TR-069 devices behind this Mikrotik
        $tr069Devices = TenantDevice::whereIn('wan_ip', array_filter([
            $router->detected_public_ip,
            $router->public_ip,
            $router->wireguard_address
        ]))->get();

        return Inertia::render('Mikrotiks/Show', [
            'mikrotik' => $router,
            'realtime' => $realtimeData,
            'tr069_devices' => $tr069Devices,
        ]);
    }

    /**
     * Reboot the router.
     */
    public function reboot($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        try {
            $apiService = new RouterApiService($router);
            $apiService->reboot();

            return back()->with('success', 'Router reboot command sent successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to reboot router', [
                'router_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to reboot router: ' . $e->getMessage());
        }
    }

    /**
     * Update router identity.
     */
    public function updateIdentity(Request $request, $id)
    {
        $router = TenantMikrotik::findOrFail($id);

        $request->validate([
            'identity' => 'required|string|max:255|min:1',
        ]);

        try {
            $apiService = new RouterApiService($router);
            $success = $apiService->setIdentity($request->identity);

            if ($success) {
                // Update local DB as well
                $router->name = $request->identity;
                $router->save();

                return back()->with('success', 'Router identity updated successfully.');
            }

            return back()->with('error', 'Failed to update router identity on device.');
        } catch (\Exception $e) {
            Log::error('Failed to update router identity', [
                'router_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to update identity: ' . $e->getMessage());
        }
    }

    /**
     * Get router status (for frontend status checking).
     * Returns current router status from database, and performs real-time check if data is stale.
     * All router communication uses VPN IP (wireguard_address) only.
     */
    public function getStatus($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        // Refresh router data from database
        $router->refresh();

        // Get VPN IP (standardized field)
        $vpnIp = $router->wireguard_address ?? $router->ip_address;

        // If no VPN IP, return current status
        if (!$vpnIp) {
            return response()->json([
                'success' => true,
                'status' => $router->status,
                'online' => $router->online ?? false,
                'last_seen_at' => $router->last_seen_at?->toIso8601String(),
                'vpn_ip' => null,
                'cpu' => $router->cpu ?? $router->cpu_usage ?? null,
                'memory' => $router->memory ?? $router->memory_usage ?? null,
                'uptime' => $router->uptime ?? null,
            ]);
        }

        // Check if status is stale (older than 30 seconds) - if so, do a quick real-time check
        $shouldCheck = false;
        if (!$router->last_seen_at) {
            $shouldCheck = true;
        } else {
            $secondsSinceLastSeen = now()->diffInSeconds($router->last_seen_at);
            if ($secondsSinceLastSeen > 30) {
                $shouldCheck = true;
            }
        }

        // Perform real-time check if needed
        if ($shouldCheck) {
            try {
                $apiService = new RouterApiService($router);
                $isOnline = $apiService->isOnline();

                if ($isOnline) {
                    // Get system resources
                    $resources = $apiService->getSystemResource();

                    $updateData = [
                        'status' => 'online',
                        'online' => true,
                        'last_seen_at' => now(),
                    ];

                    if ($resources !== false) {
                        // Update CPU, memory, uptime
                        if (isset($resources['cpu-load'])) {
                            $cpuValue = (float) $resources['cpu-load'];
                            $updateData['cpu_usage'] = $cpuValue;
                            $updateData['cpu'] = $cpuValue;
                        }

                        if (isset($resources['free-memory']) && isset($resources['total-memory'])) {
                            $memoryUsed = $resources['total-memory'] - $resources['free-memory'];
                            $memoryPercent = round(($memoryUsed / $resources['total-memory']) * 100, 2);
                            $updateData['memory_usage'] = $memoryPercent;
                            $updateData['memory'] = $memoryPercent;
                        }

                        if (isset($resources['uptime'])) {
                            $updateData['uptime'] = (int) $resources['uptime'];
                        }
                    }

                    $router->update($updateData);
                    $router->refresh();
                } else {
                    // Router is offline
                    $router->update([
                        'status' => 'offline',
                        'online' => false,
                    ]);
                    $router->refresh();
                }
            } catch (\Exception $e) {
                // If check fails, just return current database status
                Log::debug('Real-time status check failed, returning cached status', [
                    'router_id' => $router->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Get active sessions if router is online (from cache, don't query API every time)
        $hotspotUsers = 0;
        $pppoeUsers = 0;
        $identity = $router->name; // Default to router name

        // Only fetch from API if router is online and data is fresh (< 2 minutes)
        if ($router->status === 'online' && $router->last_seen_at) {
            $secondsSinceLastSeen = now()->diffInSeconds($router->last_seen_at);
            if ($secondsSinceLastSeen < 120) { // Less than 2 minutes
                try {
                    $apiService = new RouterApiService($router);
                    $hotspotUsers = $apiService->getHotspotActive();
                    $pppoeUsers = $apiService->getPppoeActive();
                    $identityResult = $apiService->getIdentity();
                    if ($identityResult !== false) {
                        $identity = $identityResult;
                    }
                } catch (\Exception $e) {
                    // Silently fail, use cached data
                }
            }
        }

        // Format uptime
        $uptimeFormatted = null;
        if ($router->uptime) {
            $days = floor($router->uptime / 86400);
            $hours = floor(($router->uptime % 86400) / 3600);
            $minutes = floor(($router->uptime % 3600) / 60);
            $uptimeFormatted = "{$days}d {$hours}h {$minutes}m";
        }

        return response()->json([
            'success' => true,
            'online' => $router->online ?? ($router->status === 'online'),
            'status' => $router->status,
            'last_seen_at' => $router->last_seen_at?->toIso8601String(),
            'vpn_ip' => $vpnIp,
            'wireguard_address' => $router->wireguard_address,
            'ip_address' => $router->ip_address,
            'cpu' => $router->cpu ?? $router->cpu_usage ?? null,
            'memory' => $router->memory ?? $router->memory_usage ?? null,
            'uptime' => $router->uptime ?? null,
            'uptime_formatted' => $uptimeFormatted,
            'identity' => $identity,
            'hotspot_users' => $hotspotUsers,
            'pppoe_users' => $pppoeUsers,
        ]);
    }

    /**
     * Get status for all routers (bulk endpoint for frontend polling).
     */
    public function getAllStatus()
    {
        $routers = TenantMikrotik::all();
        $statuses = [];

        foreach ($routers as $router) {
            $router->refresh();

            $vpnIp = $router->wireguard_address ?? $router->ip_address;

            // Format uptime
            $uptimeFormatted = null;
            if ($router->uptime) {
                $days = floor($router->uptime / 86400);
                $hours = floor(($router->uptime % 86400) / 3600);
                $minutes = floor(($router->uptime % 3600) / 60);
                $uptimeFormatted = "{$days}d {$hours}h {$minutes}m";
            }

            // Check if router should be marked offline (last_seen_at > 2 minutes)
            $isStale = false;
            if ($router->last_seen_at) {
                $secondsSinceLastSeen = now()->diffInSeconds($router->last_seen_at);
                if ($secondsSinceLastSeen > 120) { // 2 minutes
                    $isStale = true;
                }
            } elseif ($router->status === 'online') {
                $isStale = true;
            }

            $statuses[] = [
                'id' => $router->id,
                'online' => $isStale ? false : ($router->online ?? ($router->status === 'online')),
                'status' => $isStale ? 'offline' : $router->status,
                'last_seen_at' => $router->last_seen_at?->toIso8601String(),
                'vpn_ip' => $vpnIp,
                'cpu' => $router->cpu ?? $router->cpu_usage ?? null,
                'memory' => $router->memory ?? $router->memory_usage ?? null,
                'uptime' => $router->uptime ?? null,
                'uptime_formatted' => $uptimeFormatted,
                'identity' => $router->name,
                'identity' => $router->name,
                'hotspot_users' => TenantActiveUsers::where('router_id', $router->id)->where('status', 'active')->where(function($q) {
                    $q->where('user_type', 'hotspot')->orWhere('username', 'REGEXP', '^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$');
                })->count(),
                'pppoe_users' => TenantActiveUsers::where('router_id', $router->id)->where('status', 'active')->where(function($q) {
                    $q->where('user_type', 'pppoe')->orWhere('username', 'NOT REGEXP', '^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$');
                })->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'routers' => $statuses,
        ]);
    }

    /**
     * Get router resource information (CPU, memory, uptime).
     */
    public function getResource($id)
    {
        try {
            $router = TenantMikrotik::findOrFail($id);

            if (!$router->wireguard_address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Router VPN IP not configured',
                ], 400);
            }

            $apiService = new RouterApiService($router);
            $resources = $apiService->getSystemResource();

            if ($resources === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get router resources',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'resources' => $resources,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get router resources', [
                'router_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting router resources: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get router interfaces.
     */
    public function getInterfaces($id)
    {
        try {
            $router = TenantMikrotik::findOrFail($id);

            if (!$router->wireguard_address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Router VPN IP not configured',
                ], 400);
            }

            $apiService = new RouterApiService($router);
            $interfaces = $apiService->getInterfaces();

            return response()->json([
                'success' => true,
                'interfaces' => $interfaces,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get router interfaces', [
                'router_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting router interfaces: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active sessions (hotspot and PPPoE).
     */
    public function getActiveSessions($id)
    {
        try {
            $router = TenantMikrotik::findOrFail($id);

            if (!$router->wireguard_address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Router VPN IP not configured',
                ], 400);
            }

            $hotspotActive = TenantActiveUsers::where('router_id', $id)->where('status', 'active')->where(function($q) {
                $q->where('user_type', 'hotspot')->orWhere('username', 'REGEXP', '^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$');
            })->count();
            $pppoeActive = TenantActiveUsers::where('router_id', $id)->where('status', 'active')->where(function($q) {
                $q->where('user_type', 'pppoe')->orWhere('username', 'NOT REGEXP', '^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$');
            })->count();

            return response()->json([
                'success' => true,
                'hotspot_active' => $hotspotActive,
                'pppoe_active' => $pppoeActive,
                'total_active' => $hotspotActive + $pppoeActive,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get active sessions', [
                'router_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting active sessions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new Mikrotik and show onboarding script.
     */
    public function store(Request $request, MikrotikScriptGenerator $scriptGenerator, WinboxPortService $winboxService)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'api_port' => 'nullable|integer|min:1|max:65535',
        ]);

        // Ensure API port is set (default 8728)
        $apiPort = $data['api_port'] ?? 8728;

        // Generate API credentials immediately
        $apiUsername = 'zisp_user';
        $apiPassword = Str::random(rand(18, 24));

        // Use default router credentials (admin/blank) if not provided
        $routerUsername = 'admin';
        $routerPassword = '';

        $router = TenantMikrotik::create([
            'name' => $data['name'],
            'router_username' => $routerUsername,
            'router_password' => $routerPassword,
            'api_username' => $apiUsername,
            'api_password' => $apiPassword,
            'api_port' => $apiPort,
            'connection_type' => 'api',
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'sync_token' => Str::random(40),
        ]);

        $caUrl = optional($router->openvpnProfile)->ca_cert_path
            ? route('mikrotiks.downloadCACert', $router->id)
            : null;

        // Get server IP (trusted IP) - use config or request IP
        $trustedIp = config('app.server_ip') ?? request()->server('SERVER_ADDR') ?? '213.199.41.117';

        // Ensure API port is set (should already be set in create, but double-check)
        $apiPort = $router->api_port ?? 8728;

        // Pre-allocate WireGuard IP (first available)
        try {
            $vpnIp = MikrotikService::assignNextAvailableWireguardIp($router);
            
            // Refresh router to ensure wireguard_address is loaded
            $router->refresh();
            
            // Double-check the IP was assigned
            if (empty($vpnIp) || empty($router->wireguard_address)) {
                throw new \Exception("WireGuard IP assignment failed - IP is empty");
            }
            
            Log::info("Assigned WireGuard IP {$vpnIp} to router {$router->id}", [
                'vpn_ip' => $vpnIp,
                'router_wireguard_address' => $router->wireguard_address,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to assign WireGuard IP: " . $e->getMessage());
            // Re-throw as this is critical for router operation
            throw new \Exception("Failed to allocate WireGuard IP address. Please check available IPs in the 10.100.0.0/16 subnet.");
        }

        $script = $scriptGenerator->generate([
            'name' => $router->name,
            'username' => $router->router_username,
            'router_password' => $router->router_password,
            'api_username' => $router->api_username,
            'api_password' => $router->api_password,
            'router_id' => $router->id,
            'sync_token' => $router->sync_token,
            'ca_url' => $caUrl,
            'api_port' => $apiPort, 
            'trusted_ip' => $trustedIp,
            'radius_ip' => '10.100.0.1', 
            'radius_secret' => $router->api_password,
            'wg_client_ip' => $vpnIp, // Explicitly pass the pre-allocated IP
        ]);

        // Attempt to assign Winbox port and public IP
        // This is critical for remote access via public domain
        try {
            Log::info("Attempting Winbox port allocation for router {$router->id}", [
                'router_id' => $router->id,
                'has_vpn_ip' => !empty($vpnIp),
                'vpn_ip' => $vpnIp,
            ]);
            
            $winboxService->ensureMapping($router);
            
            // Reload router from database to get updated values
            $router->refresh();
            
            Log::info("Winbox port allocation completed for router {$router->id}", [
                'router_id' => $router->id,
                'winbox_port' => $router->winbox_port,
                'public_ip' => $router->public_ip,
                'has_vpn_ip' => !empty($router->wireguard_address),
                'vpn_ip' => $router->wireguard_address,
            ]);
        } catch (\Exception $e) {
            Log::error('Critical error in Winbox port assignment', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Re-throw to prevent silent failures
            throw $e;
        }
        
        return Inertia::render('Mikrotiks/SetupScript', [
            'router' => $router,
            'script' => $script,
        ]);
    }

    /**
     * Update router details & recheck connection.
     */
    public function update(Request $request, $id, WinboxPortService $winboxService)
    {
        $router = TenantMikrotik::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
            'ip_address' => 'required|ip',
            'api_port' => 'required|integer',
            'ssh_port' => 'required|integer',
            'openvpn_profile_id' => 'nullable|integer',
            'router_username' => 'required|string',
            'router_password' => 'nullable|string',
            'connection_type' => 'required|in:api,ssh,ovpn',
            'notes' => 'nullable|string',
        ]);

        if (empty($data['router_password']))
            unset($data['router_password']);
        $router->update($data);

        // Register/Update RADIUS NAS entry when IP address is updated
        if (isset($data['ip_address'])) {
            $this->registerRadiusNas($router);
        }

        // Ensure Winbox mapping is active if we have a VPN IP
        if ($router->wireguard_address) {
            try {
                $winboxService->ensureMapping($router);
            } catch (\Exception $e) {
                Log::error('Winbox Mapping Error', ['router' => $router->id, 'error' => $e->getMessage()]);
            }
        }
        
        $isOnline = $this->testRouterConnection($router);
        $router->status = $isOnline ? 'online' : 'offline';
        if ($isOnline)
            $router->last_seen_at = now();
        $router->save();

        $router->logs()->create([
            'action' => 'update',
            'message' => $isOnline ? 'Router is online after update.' : 'Router offline after update.',
            'status' => $isOnline ? 'success' : 'failed',
        ]);

        return redirect()->route('mikrotiks.index')->with('success', 'Router updated!');
    }

    public function destroy(Request $request, $id, WinboxPortService $winboxService)
    {
        // Only admins, tenant admins, and network engineers can delete routers
        $user = auth()->user();
        if (!$user->hasAnyRole(['admin', 'tenant_admin', 'network_engineer'])) {
            abort(403, 'You do not have permission to delete routers. Please submit a router deletion request to your administrator with documentation explaining why it should be removed.');
        }

        $request->validate([
            'password' => 'required|current_password',
        ]);
        
        $router = TenantMikrotik::findOrFail($id);

        try {
            $winboxService->removeMapping($router);
        } catch (\Exception $e) {
            Log::error("Failed to remove Winbox mapping during destroy", ['id' => $id, 'error' => $e->getMessage()]);
        }
        
        $router->delete();
        return redirect()->route('mikrotiks.index')->with('success', 'Router moved to Recycle Bin.');
    }

    public function restore(Request $request, $id)
    {
        // Only admins, tenant admins, and network engineers can restore routers
        $user = auth()->user();
        if (!$user->hasAnyRole(['admin', 'tenant_admin', 'network_engineer'])) {
            abort(403, 'You do not have permission to restore routers. Please contact your administrator.');
        }

        $request->validate([
            'password' => 'required|current_password',
        ]);
        
        $router = TenantMikrotik::withTrashed()->findOrFail($id);
        $router->restore();

        return redirect()->route('mikrotiks.index', ['tab' => 'deleted'])->with('success', 'Router restored successfully.');
    }

    public function forceDelete(Request $request, $id)
    {
        // Only admins, tenant admins, and network engineers can permanently delete routers
        $user = auth()->user();
        if (!$user->hasAnyRole(['admin', 'tenant_admin', 'network_engineer'])) {
            abort(403, 'You do not have permission to permanently delete routers. Please contact your administrator.');
        }

        $request->validate([
            'password' => 'required|current_password',
        ]);

        $router = TenantMikrotik::withTrashed()->findOrFail($id);
        $router->forceDelete();

        return redirect()->route('mikrotiks.index', ['tab' => 'deleted'])->with('success', 'Router permanently deleted.');
    }

    /*
     * Set router VPN IP address.
     * All router communication must use VPN tunnel IP (10.100.0.0/16) only.
     * This method sets the VPN IP in wireguard_address field.
     */
    public function setIp(Request $request, $id, WinboxPortService $winboxService)
    {
        $router = TenantMikrotik::findOrFail($id);

        $data = $request->validate([
            'ip_address' => 'required|string|max:255', // Accepts VPN IP
            'force' => 'nullable|boolean',
        ]);

        // Extract IP from CIDR notation if present (e.g., "10.100.0.2/16" -> "10.100.0.2")
        $ip = $data['ip_address'];
        if (strpos($ip, '/') !== false) {
            $ip = explode('/', $ip)[0];
        }

        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IP address format.',
            ], 422);
        }

        // Validate that IP is in VPN subnet (10.100.0.0/16)
        $ipLong = ip2long($ip);
        $networkLong = ip2long('10.100.0.0');
        $mask = (-1 << (32 - 16)) & 0xFFFFFFFF;
        if (($ipLong & $mask) !== ($networkLong & $mask)) {
            return response()->json([
                'success' => false,
                'message' => 'IP address must be in VPN subnet 10.100.0.0/16.',
            ], 422);
        }

        $oldVpnIp = $router->wireguard_address;
        // Store VPN IP in standardized field
        $router->wireguard_address = $ip;
        // Also update ip_address for legacy compatibility
        $router->ip_address = $ip;
        $router->save();

        $this->registerRadiusNas($router);

        Log::info('Router VPN IP address set', [
            'router_id' => $router->id,
            'old_vpn_ip' => $oldVpnIp,
            'new_vpn_ip' => $ip,
            'username' => $router->router_username,
            'api_port' => $router->api_port,
        ]);

        $router->logs()->create([
            'action' => 'set_vpn_ip',
            'message' => "VPN IP address set to: $ip (API port: {$router->api_port}, Username: {$router->router_username})",
            'status' => 'success',
        ]);


        
        // Ensure Winbox mapping is updated
        try {
            $winboxService->ensureMapping($router);
        } catch (\Exception $e) {
            Log::error("Failed to update Winbox mapping after setting IP", ['id' => $router->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'VPN IP address set successfully.',
            'vpn_ip' => $router->wireguard_address,
            'api_port' => $router->api_port,
            'username' => $router->router_username,
        ]);
    }

    /**
     * Ping the router using RouterOS API (not ICMP).
     * All router communication must go through the VPN tunnel only.
     * 
     * Uses API-based ping via RouterApiService.
     * Returns JSON with online status and latency.
     */
    public function pingRouter($id)
    {
        try {
            $router = TenantMikrotik::findOrFail($id);

            // Refresh router data from database
            $router->refresh();

            // Get VPN IP from wireguard_address field (standardized VPN IP storage)
            $vpnIp = $router->wireguard_address;

            // If wireguard_address is not set, check if ip_address is a VPN IP (10.100.0.0/16)
            if (!$vpnIp && $router->ip_address) {
                $ip = $router->ip_address;
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipLong = ip2long($ip);
                    $networkLong = ip2long('10.100.0.0');
                    $mask = (-1 << (32 - 16)) & 0xFFFFFFFF;
                    if (($ipLong & $mask) === ($networkLong & $mask)) {
                        $vpnIp = $ip;
                    }
                }
            }

            // If still no VPN IP, return error
            if (!$vpnIp) {
                return response()->json([
                    'success' => false,
                    'status' => 'pending',
                    'message' => 'Router VPN IP address not configured. Please ensure WireGuard tunnel is established.',
                    'online' => false,
                    'latency' => null,
                ], 400);
            }

            // Use API-based ping via RouterApiService
            $apiService = new RouterApiService($router);
            $pingResult = $apiService->apiPing();

            $isOnline = $pingResult['online'];
            $latency = $pingResult['latency'];

            // Update router status if online
            if ($isOnline) {
                $router->status = 'online';
                $router->online = true;
                $router->last_seen_at = now();
                $router->save();

                // If API credentials are not configured, dispatch job to set them up
                if (!$router->api_username || !$router->api_password) {
                    \App\Jobs\SetupMikrotikApiUser::dispatch($router)->onQueue('default');

                    Log::info('Dispatched SetupMikrotikApiUser job for router', [
                        'router_id' => $router->id,
                        'router_name' => $router->name,
                    ]);
                }
            } else {
                $router->status = 'offline';
                $router->online = false;
                $router->save();
            }

            return response()->json([
                'success' => true,
                'online' => $isOnline,
                'latency' => $latency,
                'status' => $isOnline ? 'online' : 'offline',
                'message' => $isOnline
                    ? 'Router is online and responding via RouterOS API (latency: ' . ($latency ? $latency . 'ms' : 'N/A') . ').'
                    : 'Router is not responding via RouterOS API. Please verify: 1) WireGuard tunnel is established, 2) Router is powered on, 3) VPN IP is correct (' . $vpnIp . ').',
                'last_seen_at' => $router->last_seen_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Ping router endpoint failed', [
                'router_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'online' => false,
                'latency' => null,
                'status' => 'error',
                'message' => 'Unable to complete ping test. Please try again shortly.',
            ], 500);
        }
    }

    /**
     * Download setup script for router.
     */
    public function downloadSetupScript($id, MikrotikScriptGenerator $scriptGenerator)
    {
        $router = TenantMikrotik::findOrFail($id);

        $caUrl = optional($router->openvpnProfile)->ca_cert_path
            ? route('mikrotiks.downloadCACert', $router->id)
            : null;

        // Get server IP (trusted IP) - use config or request IP
        $trustedIp = config('app.server_ip') ?? request()->server('SERVER_ADDR') ?? '213.199.41.117';

        $script = $scriptGenerator->generate([
            'name' => $router->name,
            'username' => $router->router_username,
            'router_password' => $router->router_password,
            'api_username' => $router->api_username,
            'api_password' => $router->api_password,
            'router_id' => $router->id,
            'sync_token' => $router->sync_token,
            'ca_url' => $caUrl,
            'api_port' => $router->api_port ?? 8728,
            'trusted_ip' => $trustedIp,
            'radius_ip' => '10.100.0.1', // RADIUS via VPN
            'radius_secret' => $router->api_password, // Use API password as RADIUS secret
        ]);

        $router->logs()->create([
            'action' => 'download_script',
            'message' => 'Setup script downloaded',
            'status' => 'success',
        ]);

        return response($script)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=setup_router_{$router->id}.rsc");
    }

    /**
     * Public download endpoint for setup script (uses token authentication)
     * This allows Mikrotik's /tool fetch to download scripts without session auth
     */
    public function downloadScriptPublic($id, Request $request, MikrotikScriptGenerator $scriptGenerator)
    {
        $router = TenantMikrotik::findOrFail($id);

        // Validate token - MUST be provided and MUST match
        $token = $request->query('token');
        if (!$token || $token !== $router->sync_token) {
            Log::warning('Unauthorized public script download attempt', [
                'router_id' => $id,
                'client_ip' => $request->ip(),
                'token_provided' => $token ? 'invalid' : 'missing'
            ]);
            abort(403, 'Invalid or missing token');
        }

        $caUrl = optional($router->openvpnProfile)->ca_cert_path
            ? route('mikrotiks.downloadCACert', $router->id)
            : null;

        $trustedIp = config('app.server_ip') ?? request()->server('SERVER_ADDR') ?? '213.199.41.117';

        $script = $scriptGenerator->generate([
            'name' => $router->name,
            'username' => $router->router_username,
            'router_password' => $router->router_password,
            'api_username' => $router->api_username,
            'api_password' => $router->api_password,
            'router_id' => $router->id,
            'sync_token' => $router->sync_token,
            'ca_url' => $caUrl,
            'api_port' => $router->api_port ?? 8728,
            'trusted_ip' => $trustedIp,
            'radius_ip' => '10.100.0.1',
            'radius_secret' => $router->api_password,
            'wg_client_ip' => $router->wireguard_address,
        ]);

        $router->logs()->create([
            'action' => 'download_script_public',
            'message' => 'Setup script downloaded via public endpoint',
            'status' => 'success',
        ]);

        return response($script)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "inline; filename=onboard_{$router->id}.rsc");
    }

    /**
     * Reprovision router - regenerate onboarding script.
     */
    public function reprovision($id, MikrotikScriptGenerator $scriptGenerator)
    {
        $router = TenantMikrotik::findOrFail($id);

        $caUrl = optional($router->openvpnProfile)->ca_cert_path
            ? route('mikrotiks.downloadCACert', $router->id)
            : null;

        // Get server IP (trusted IP) - use config or request IP
        $trustedIp = config('app.server_ip') ?? request()->server('SERVER_ADDR') ?? '213.199.41.117';

        $script = $scriptGenerator->generate([
            'name' => $router->name,
            'username' => $router->router_username,
            'router_password' => $router->router_password,
            'api_username' => $router->api_username,
            'api_password' => $router->api_password,
            'router_id' => $router->id,
            'sync_token' => $router->sync_token,
            'ca_url' => $caUrl,
            'api_port' => $router->api_port ?? 8728,
            'trusted_ip' => $trustedIp,
            'radius_ip' => '10.100.0.1', // RADIUS via VPN
            'radius_secret' => $router->api_password, // Use API password as RADIUS secret
        ]);

        return Inertia::render('Mikrotiks/SetupScript', compact('router', 'script'));
    }

    /**
     * Phone-home sync endpoint removed.
     * Router monitoring now uses RouterOS API polling via SyncRoutersCommand.
     */

    /**
     * Register WireGuard peer information from router phone-home.
     */
    public function registerWireguard($mikrotik, Request $request, WinboxPortService $winboxService)
    {
        try {
            $router = TenantMikrotik::findOrFail($mikrotik);

            // Log incoming request for debugging - sanitized
            Log::info('WireGuard registration attempt', [
                'router_id' => $mikrotik,
                'router_name' => $router->name,
                'client_ip' => $request->ip(),
                'has_token' => ($request->has('token') || $request->query('token')) ? 'yes' : 'no',
            ]);

            // Validate sync token
            $token = $request->query('token') ?? $request->input('token');
            if (!$token || $token !== $router->sync_token) {
                Log::warning('Invalid WireGuard register token attempt', [
                    'router_id' => $router->id,
                    'provided_token' => $token ? 'present' : 'missing',
                    'client_ip' => $request->ip(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid sync token.'
                ], 403);
            }

            $wgPublicKey = $request->input('wg_public_key');
            // Sanitize key: remove spaces that might be introduced by some clients
            if ($wgPublicKey) {
                $wgPublicKey = str_replace(' ', '+', $wgPublicKey); // Fix potential space-to-plus encoding issues
                $wgPublicKey = trim($wgPublicKey);
            }

            $wgAddress = $request->input('wg_address');
            $routerModel = $request->input('router_model');

            if (!$wgPublicKey) {
                Log::warning('WireGuard registration missing public key', [
                    'router_id' => $router->id,
                    'post_data' => $request->all(),
                ]);
                return response()->json(['success' => false, 'message' => 'Missing wg_public_key'], 422);
            }

            // Basic validation of public key length
            if (strlen($wgPublicKey) < 32 || strlen($wgPublicKey) > 128) {
                Log::warning('WireGuard registration invalid public key length', [
                    'router_id' => $router->id,
                    'key_length' => strlen($wgPublicKey),
                ]);
                return response()->json(['success' => false, 'message' => 'Invalid wg_public_key'], 422);
            }

            // Store router model if provided
            if ($routerModel) {
                $router->model = $routerModel;
            }

            // Store public key and address (derive if missing) and mark pending
            $router->wireguard_public_key = $wgPublicKey;
            $assignedAddress = null;
            if ($wgAddress && filter_var($wgAddress, FILTER_VALIDATE_IP)) {
                $assignedAddress = $wgAddress;
            } else {
                // Derive deterministic client IP from unified VPN subnet (10.100.0.0/16) and router id
                // Server is 10.100.0.1/16, routers get IPs starting from 10.100.0.2
                $subnet = config('wireguard.subnet') ?? env('WG_SUBNET', '10.100.0.0/16');
                if (strpos($subnet, '/') !== false) {
                    [$network, $prefix] = explode('/', $subnet, 2);
                    $prefix = (int) $prefix;
                    $netLong = ip2long($network);
                    if ($netLong !== false && $prefix >= 0 && $prefix <= 32) {
                        $hostBits = 32 - $prefix;
                        if ($hostBits > 0) {
                            $maxHosts = (1 << $hostBits) - 2;
                            if ($maxHosts > 1) {
                                $offset = 2 + ($router->id % $maxHosts); // reserve .1, start from .2
                                $candidate = $netLong + $offset;
                                $ip = long2ip($candidate);
                                if ($ip) {
                                    $assignedAddress = $ip;
                                }
                            }
                        }
                    }
                }
            }

            if ($assignedAddress) {
                $router->wireguard_address = $assignedAddress;
                // Server needs to know specifically which IP belongs to this peer
                // So we use /32 mask for the peer's AllowedIPs on the server side
                $router->wireguard_allowed_ips = $assignedAddress . '/32';
            }
            $router->wireguard_status = 'pending';
            $router->save();

            // Log successful registration
            Log::info('WireGuard registration successful', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'router_model' => $routerModel ?? 'not provided',
                'wg_public_key' => substr($wgPublicKey, 0, 16) . '...',
                'wg_address' => $assignedAddress ?? 'not assigned',
                'wireguard_status' => 'pending',
            ]);

            // Log
            $router->logs()->create([
                'action' => 'wg_register',
                'message' => 'WireGuard public key received and stored',
                'status' => 'success',
                'response_data' => [
                    'wg_public_key' => substr($wgPublicKey, 0, 16) . '...',
                    'wg_address' => $assignedAddress ?? 'not assigned',
                ],
            ]);

            // Dispatch job to apply peer on server
            \App\Jobs\ApplyWireGuardPeer::dispatch($router)->onQueue('wireguard');

            return response()->json(['success' => true, 'message' => 'WireGuard key registered, pending server application']);
        } catch (\Exception $e) {
            Log::error('WireGuard registration failed', [
                'router_id' => $mikrotik,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if router's last_seen_at is more than 4 minutes old.
     *
     * @param TenantMikrotik $router
     * @return bool
     */
    private function isRouterStale(TenantMikrotik $router): bool
    {
        if (!$router->last_seen_at) {
            // If never seen, consider it stale if status is online
            return $router->status === 'online';
        }

        // Check if last_seen_at is more than 6 minutes ago
        $sixMinutesAgo = now()->subMinutes(6);
        return $router->last_seen_at->lt($sixMinutesAgo);
    }

    /**
     * Test router connection via VPN tunnel only.
     * All router operations must use VPN IP (wireguard_address) from 10.100.0.0/16 subnet.
     */
    private function testRouterConnection(TenantMikrotik $router): bool
    {
        try {
            $apiPort = $router->api_port ?? 8728;
            $useSsl = $router->use_ssl ?? false;

            // Get VPN IP from wireguard_address (standardized VPN IP storage)
            $vpnIp = $router->wireguard_address;

            // Legacy fallback: if wireguard_address not set, check if ip_address is in VPN subnet
            if (!$vpnIp && $router->ip_address) {
                $ip = $router->ip_address;
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipLong = ip2long($ip);
                    $networkLong = ip2long('10.100.0.0');
                    $mask = (-1 << (32 - 16)) & 0xFFFFFFFF;
                    if (($ipLong & $mask) === ($networkLong & $mask)) {
                        $vpnIp = $ip;
                    }
                }
            }

            if (!$vpnIp) {
                Log::warning('Cannot test router connection: VPN IP not configured', [
                    'router_id' => $router->id,
                ]);
                return false;
            }

            // Log connection attempt details (without password)
            Log::info('Testing router connection via VPN tunnel', [
                'router_id' => $router->id,
                'vpn_ip' => $vpnIp,
                'username' => $router->router_username,
                'api_port' => $apiPort,
                'use_ssl' => $useSsl,
            ]);

            // Connect using VPN IP only - MikrotikService will use VPN IP automatically
            $service = MikrotikService::forMikrotik($router);
            $resources = $service->testConnection();
            $isOnline = $resources !== false;

            if ($isOnline) {
                // Update router status and last seen
                $router->status = 'online';
                $router->last_seen_at = now();

                // Optionally update router info from resources
                if (is_array($resources) && !empty($resources[0])) {
                    $resource = $resources[0];
                    $router->model = $resource['board-name'] ?? $router->model;
                    $router->os_version = $resource['version'] ?? $router->os_version;
                    $router->uptime = isset($resource['uptime']) ? (int) $resource['uptime'] : $router->uptime;
                    $router->cpu_usage = isset($resource['cpu-load']) ? (float) $resource['cpu-load'] : $router->cpu_usage;
                    $router->memory_usage = isset($resource['free-memory']) && isset($resource['total-memory'])
                        ? round((1 - ($resource['free-memory'] / $resource['total-memory'])) * 100, 2)
                        : $router->memory_usage;
                }

                Log::info('Router connection successful via VPN tunnel', [
                    'router_id' => $router->id,
                    'vpn_ip' => $vpnIp,
                ]);
            } else {
                // Check if router should be marked offline due to stale last_seen_at
                if ($this->isRouterStale($router)) {
                    $router->status = 'offline';
                    Log::warning('Router marked offline: Connection failed and last_seen_at > 4 minutes', [
                        'router_id' => $router->id,
                        'vpn_ip' => $vpnIp,
                        'last_seen_at' => $router->last_seen_at,
                    ]);
                } else {
                    // Connection failed but last_seen_at is recent, keep current status
                    Log::warning('Router connection failed via VPN tunnel: No response', [
                        'router_id' => $router->id,
                        'vpn_ip' => $vpnIp,
                    ]);
                }
            }

            $router->save();

            $router->logs()->create([
                'action' => 'ping',
                'message' => $isOnline
                    ? "Router responded successfully via API (port $apiPort)"
                    : "Router did not respond to API connection test (port $apiPort)",
                'status' => $isOnline ? 'success' : 'failed',
            ]);

            return $isOnline;
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            Log::error("Router connection test failed via VPN tunnel", [
                'router_id' => $router->id,
                'vpn_ip' => $vpnIp ?? 'not configured',
                'api_port' => $router->api_port ?? 8728,
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString(),
            ]);

            $router->status = 'offline';
            $router->save();

            $router->logs()->create([
                'action' => 'ping',
                'message' => 'Error during router connection test: ' . $errorMessage,
                'status' => 'failed',
                'response_data' => ['error' => $errorMessage],
            ]);

            return false;
        }
    }

    /**
     * Validate router connection before saving.
     * All router communication must use VPN tunnel IP (10.100.0.0/16) only.
     */
    public function validateRouter(Request $request)
    {
        $data = $request->validate([
            'ip_address' => 'required|ip', // Should be VPN IP in 10.100.0.0/16
            'router_username' => 'required|string',
            'router_password' => 'required|string',
            'api_port' => 'nullable|integer|min:1|max:65535',
        ]);

        // Validate that IP is in VPN subnet (10.100.0.0/16)
        $ip = $data['ip_address'];
        $ipLong = ip2long($ip);
        $networkLong = ip2long('10.100.0.0');
        $mask = (-1 << (32 - 16)) & 0xFFFFFFFF;
        if (($ipLong & $mask) !== ($networkLong & $mask)) {
            return response()->json([
                'success' => false,
                'message' => 'IP address must be in VPN subnet 10.100.0.0/16.',
            ], 422);
        }

        try {
            $service = new MikrotikService();
            $service->setConnection(
                $ip, // VPN IP
                $data['router_username'],
                $data['router_password'],
                $data['api_port'] ?? 8728,
                false
            );

            $resources = $service->testConnection();
            $isValid = $resources !== false;

            return response()->json([
                'success' => $isValid,
                'message' => $isValid ? 'Connection successful via VPN tunnel.' : 'Connection failed via VPN tunnel.',
                'resources' => $isValid ? $resources : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error via VPN tunnel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download RADIUS setup script.
     */
    public function downloadRadiusScript($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        // Load the RADIUS script template
        $templatePath = resource_path('scripts/mikrotik/setup-radius.rsc');
        $script = file_exists($templatePath) ? file_get_contents($templatePath) : '';

        if (!$script) {
            return redirect()->route('mikrotiks.index')
                ->with('error', 'RADIUS script template not found.');
        }

        $router->logs()->create([
            'action' => 'download_radius_script',
            'message' => 'RADIUS setup script downloaded',
            'status' => 'success',
        ]);

        return response($script)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=radius_setup_{$router->id}.rsc");
    }

    /**
     * Get remote management links for router.
     * All router access must use VPN tunnel IP (10.100.0.0/16) only.
     */
    public function remoteManagement($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        // Get VPN IP from wireguard_address (standardized VPN IP storage)
        $vpnIp = $router->wireguard_address;

        // Legacy fallback: if wireguard_address not set, check if ip_address is in VPN subnet
        if (!$vpnIp && $router->ip_address) {
            $ip = $router->ip_address;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipLong = ip2long($ip);
                $networkLong = ip2long('10.100.0.0');
                $mask = (-1 << (32 - 16)) & 0xFFFFFFFF;
                if (($ipLong & $mask) === ($networkLong & $mask)) {
                    $vpnIp = $ip;
                }
            }
        }

        if (!$vpnIp) {
            return response()->json([
                'success' => false,
                'message' => 'Router VPN IP address not configured. Please ensure WireGuard tunnel is established.',
            ], 400);
        }

        $sshPort = $router->ssh_port ?? 22;
        $apiPort = $router->api_port ?? 8728;

        // All management links use VPN IP only
        $links = [
            'winbox' => "winbox://{$vpnIp}",
            'ssh' => "ssh://{$router->router_username}@{$vpnIp}:{$sshPort}",
            'api' => "http://{$vpnIp}:{$apiPort}",
        ];

        return response()->json($links);
    }

    /**
     * Download OpenVPN CA certificate.
     */
    public function downloadCACert($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        if (!$router->openvpnProfile || !$router->openvpnProfile->ca_cert_path) {
            return response()->json([
                'success' => false,
                'message' => 'OpenVPN CA certificate not configured.',
            ], 404);
        }

        $certPath = storage_path('app/' . $router->openvpnProfile->ca_cert_path);

        if (!file_exists($certPath)) {
            return response()->json([
                'success' => false,
                'message' => 'CA certificate file not found.',
            ], 404);
        }

        return response()->download($certPath, 'ca.crt', [
            'Content-Type' => 'application/x-x509-ca-cert',
        ]);
    }

    /**
     * Download advanced configuration script for router.
     */
    public function downloadAdvancedConfig($id, MikrotikScriptGenerator $scriptGenerator)
    {
        $router = TenantMikrotik::findOrFail($id);

        // Get RADIUS settings (same as onboarding script)
        // Get RADIUS settings (same as onboarding script)
        $radius_ip = '10.100.0.1';
        $radius_secret = $router->api_password;

        // Get current tenant domain for hotspot URL
        $currentTenant = tenant();
        
        // If global tenant helper fails, try to derive from router owner
        if (!$currentTenant && $router->created_by) {
            $owner = \App\Models\User::find($router->created_by);
            if ($owner) {
                $currentTenant = $owner->tenant;
            }
        }

        $tenantDomain = $currentTenant ? $currentTenant->domains()->first()?->domain : null;
        $hotspotUrl = $tenantDomain ? "https://{$tenantDomain}" : url('/');

        $script = $scriptGenerator->generateAdvancedConfig([
            'name' => $router->name,
            'router_id' => $router->id,
            'radius_ip' => $radius_ip,
            'radius_secret' => $radius_secret,
            'snmp_community' => $router->name ? strtolower(str_replace(' ', '_', $router->name)) . '_snmp' : 'public',
            'snmp_location' => 'ZiSP Network',
            'api_port' => $router->api_port ?? 8728,
            'username' => $router->router_username,
            'router_password' => $router->router_password,
            'trusted_ip' => $this->getTrustedIpForScripts(),
            'hotspot_url' => $hotspotUrl,
            'tenant_domain' => $tenantDomain ?? '',
        ]);

        $router->logs()->create([
            'action' => 'download_advanced_config',
            'message' => 'Advanced configuration script downloaded',
            'status' => 'success',
        ]);

        return response($script)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=advanced_config_router_{$router->id}.rsc");
    }
    /**
     * Register or update the router in FreeRADIUS using its VPN tunnel IP.
     * All routers must use VPN IP (wireguard_address) from 10.100.0.0/16 subnet.
     * This ensures connectivity from the RADIUS server via VPN tunnel only.
     */
    private function registerRadiusNas(TenantMikrotik $router): void
    {
        try {
            // Get VPN IP from wireguard_address (standardized VPN IP storage)
            $nasIp = $router->wireguard_address;

            // Legacy fallback: if wireguard_address not set, check if ip_address is in VPN subnet
            if (!$nasIp && $router->ip_address) {
                $ip = $router->ip_address;
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipLong = ip2long($ip);
                    $networkLong = ip2long('10.100.0.0');
                    $mask = (-1 << (32 - 16)) & 0xFFFFFFFF;
                    if (($ipLong & $mask) === ($networkLong & $mask)) {
                        $nasIp = $ip;
                    }
                }
            }

            if (!$nasIp) {
                Log::warning("Skipping NAS registration: missing VPN IP", [
                    'router_id' => $router->id,
                    'router_name' => $router->name
                ]);
                return;
            }

            $shortname = "mtk-" . $router->id;

            $secret = $router->api_password;

            $radiusServer = config('radius.server', 'default');

            $existing = Nas::where('shortname', $shortname)->first();

            if ($existing) {
                $existing->update([
                    'nasname' => $nasIp,
                    'secret' => $secret,
                    'type' => 'mikrotik',
                    'server' => $radiusServer,
                    'description' => "Tenant router {$router->id} - {$router->name}",
                ]);

                Log::info("Updated NAS entry for router {$router->id}");
                return;
            }

            Nas::create([
                'nasname' => $nasIp,
                'shortname' => $shortname,
                'type' => 'mikrotik',
                'secret' => $secret,
                'server' => $radiusServer,
                'description' => "Tenant router {$router->id} - {$router->name}",
            ]);

            Log::info("Created new NAS entry for router {$router->id}");

        } catch (\Exception $e) {

            Log::error("NAS registration failed: {$e->getMessage()}", [
                'router_id' => $router->id
            ]);
        }
    }


    /**
     * Get trusted IP for scripts.
     * This method is used to ensure consistent trusted IP across different script generations.
     * It can be overridden by child classes if specific logic is needed.
     */
    protected function getTrustedIpForScripts()
    {
        $trustedIp = config('app.server_ip') ?? request()->server('SERVER_ADDR') ?? request()->ip();

        if (!$trustedIp) {
            return '0.0.0.0/0';
        }

        // Note: trusted_ip is for firewall rules (public IP), not VPN subnet
        // VPN subnet is always 10.100.0.0/16
        if (!str_contains($trustedIp, '/')) {
            $trustedIp .= '/32';
        }

        return $trustedIp;
    }

    /**
     * Download hotspot template files as ZIP archive.
     */
    public function downloadHotspotTemplates($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        // Get current tenant for proper URL replacement
        $currentTenant = tenant();
        $tenantDomain = $currentTenant ? $currentTenant->domains()->first()->domain : null;

        // Path to hotspot template files
        $templatePath = resource_path('scripts/zisp-hotspot');

        if (!is_dir($templatePath)) {
            return response()->json(['error' => 'Hotspot template directory not found'], 404);
        }

        // Create ZIP file in memory
        $zipFileName = "hotspot_templates_{$router->id}_{$router->name}.zip";
        $zipPath = storage_path("app/temp/{$zipFileName}");

        // Ensure temp directory exists
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return response()->json(['error' => 'Failed to create ZIP file'], 500);
        }

        // Template files to include
        $templateFiles = [
            'login.html',
            'alogin.html',
            'rlogin.html',
            'flogin.html',
            'logout.html',
            'redirect.html',
            'error.html'
        ];

        foreach ($templateFiles as $file) {
            $filePath = $templatePath . '/' . $file;
            if (file_exists($filePath)) {
                // Read the template content
                $content = file_get_contents($filePath);

                // Replace tenant domain placeholder if needed
                if ($tenantDomain) {
                    $content = str_replace('{{ $tenant->domain }}', $tenantDomain, $content);
                }

                // Add to ZIP
                $zip->addFromString($file, $content);
            }
        }

        $zip->close();

        // Log the download
        $router->logs()->create([
            'action' => 'download_hotspot_templates',
            'message' => 'Hotspot template files downloaded as ZIP',
            'status' => 'success',
        ]);

        // Return the ZIP file for download
        return response()->download($zipPath, $zipFileName, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$zipFileName}\""
        ])->deleteFileAfterSend(true);
    }

    /**
     * MikroTik Heartbeat for IP Discovery.
     * Hits by MikroTik scheduler to report its current public IP.
     */
    public function heartbeat(Request $request)
    {
        $token = $request->header('X-Sync-Token') ?? $request->get('token');
        
        if (!$token) {
            return response()->json(['error' => 'Missing token'], 401);
        }

        $router = TenantMikrotik::withoutGlobalScopes()
            ->where('sync_token', $token)
            ->first();

        if (!$router) {
            return response()->json(['error' => 'Invalid token'], 404);
        }

        $publicIp = $request->ip();
        
        $router->update([
            'detected_public_ip' => $publicIp,
            'last_seen_at' => now(),
            'status' => 'online'
        ]);

        return response()->json([
            'status' => 'success',
            'detected_ip' => $publicIp
        ]);
    }

    /**
     * Scan for TR-069 devices behind this router.
     */
    public function scanBehind($id)
    {
        \Illuminate\Support\Facades\Artisan::call('tr069:scan-behind', [
            'router_id' => $id
        ]);

        return back()->with('success', 'Network scan completed.');
    }

    /**
     * Generate MikroTik script to upload and configure hotspot templates.
     */
    public function getHotspotUploadScript($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        // Get current tenant domain
        $currentTenant = tenant();
        $tenantDomain = $currentTenant ? $currentTenant->domains()->first()->domain : null;

        $script = "# ZiSP Hotspot Template Upload Script
# Generated for router: {$router->name}
# Router ID: {$router->id}

:put \"==================== HOTSPOT TEMPLATE UPLOAD ====================\"

# Remove existing hotspot HTML files
:do {
    /ip hotspot html remove [find name~\"login.html|alogin.html|rlogin.html|flogin.html|logout.html|redirect.html|error.html\"]
} on-error={}

# Create custom HTML files that redirect to tenant hotspot URL
/ip hotspot html add name=login.html contents=\"<html><head><meta http-equiv=\\\"refresh\\\" content=\\\"0; URL='https://{$tenantDomain}/hotspot?src=login'\\\"></head><body style=\\\"font-family: Arial; background:#111; color:#fff; text-align:center; padding-top:50px;\\\"><h2>Redirecting...</h2><p>If you are not redirected automatically, click below:</p><a href=\\\"https://{$tenantDomain}/hotspot?src=login\\\" style=\\\"color:#4FC3F7; font-size:20px;\\\">Continue</a></body></html>\"

/ip hotspot html add name=alogin.html contents=\"<html><head><meta http-equiv=\\\"refresh\\\" content=\\\"0; URL='https://{$tenantDomain}/hotspot?src=alogin'\\\"></head><body style=\\\"font-family: Arial; background:#111; color:#fff; text-align:center; padding-top:50px;\\\"><h2>Redirecting...</h2><p>If you are not redirected automatically, click below:</p><a href=\\\"https://{$tenantDomain}/hotspot?src=alogin\\\" style=\\\"color:#4FC3F7; font-size:20px;\\\">Continue</a></body></html>\"

/ip hotspot html add name=rlogin.html contents=\"<html><head><meta http-equiv=\\\"refresh\\\" content=\\\"0; URL='https://{$tenantDomain}/hotspot?src=rlogin'\\\"></head><body style=\\\"font-family: Arial; background:#111; color:#fff; text-align:center; padding-top:50px;\\\"><h2>Redirecting...</h2><p>If you are not redirected automatically, click below:</p><a href=\\\"https://{$tenantDomain}/hotspot?src=rlogin\\\" style=\\\"color:#4FC3F7; font-size:20px;\\\">Continue</a></body></html>\"

/ip hotspot html add name=flogin.html contents=\"<html><head><meta http-equiv=\\\"refresh\\\" content=\\\"0; URL='https://{$tenantDomain}/hotspot?src=flogin'\\\"></head><body style=\\\"font-family: Arial; background:#111; color:#fff; text-align:center; padding-top:50px;\\\"><h2>Redirecting...</h2><p>If you are not redirected automatically, click below:</p><a href=\\\"https://{$tenantDomain}/hotspot?src=flogin\\\" style=\\\"color:#4FC3F7; font-size:20px;\\\">Continue</a></body></html>\"

/ip hotspot html add name=logout.html contents=\"<html><head><meta http-equiv=\\\"refresh\\\" content=\\\"0; URL='https://{$tenantDomain}/hotspot?src=logout'\\\"></head><body style=\\\"font-family: Arial; background:#111; color:#fff; text-align:center; padding-top:50px;\\\"><h2>Redirecting...</h2><p>If you are not redirected automatically, click below:</p><a href=\\\"https://{$tenantDomain}/hotspot?src=logout\\\" style=\\\"color:#4FC3F7; font-size:20px;\\\">Continue</a></body></html>\"

/ip hotspot html add name=redirect.html contents=\"<html><head><meta http-equiv=\\\"refresh\\\" content=\\\"0; URL='https://{$tenantDomain}/hotspot?src=redirect'\\\"></head><body style=\\\"font-family: Arial; background:#111; color:#fff; text-align:center; padding-top:50px;\\\"><h2>Redirecting...</h2><p>If you are not redirected automatically, click below:</p><a href=\\\"https://{$tenantDomain}/hotspot?src=redirect\\\" style=\\\"color:#4FC3F7; font-size:20px;\\\">Continue</a></body></html>\"

/ip hotspot html add name=error.html contents=\"<html><head><meta http-equiv=\\\"refresh\\\" content=\\\"0; URL='https://{$tenantDomain}/hotspot?src=error'\\\"></head><body style=\\\"font-family: Arial; background:#111; color:#fff; text-align:center; padding-top:50px;\\\"><h2>Redirecting...</h2><p>If you are not redirected automatically, click below:</p><a href=\\\"https://{$tenantDomain}/hotspot?src=error\\\" style=\\\"color:#4FC3F7; font-size:20px;\\\">Continue</a></body></html>\"

:put \" =================== Hotspot HTML templates configured =================== \"

:put \"==================== HOTSPOT TEMPLATE UPLOAD COMPLETE ====================\"";

        // Log the script generation
        $router->logs()->create([
            'action' => 'generate_hotspot_upload_script',
            'message' => 'Hotspot template upload script generated',
            'status' => 'success',
        ]);

        return response($script)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename=hotspot_upload_{$router->id}.rsc");
    }
}
