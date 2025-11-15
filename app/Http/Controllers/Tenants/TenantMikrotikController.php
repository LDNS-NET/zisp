<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\{
    TenantMikrotik,
    TenantOpenVPNProfile,
    TenantRouterLog,
    TenantBandwidthUsage,
    TenantActiveSession,
    TenantRouterAlert
};
use App\Services\{MikrotikService, MikrotikScriptGenerator};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenantMikrotikController extends Controller
{
    /**
     * List all Mikrotiks for tenant.
     */
    public function index()
    {
        $routers = TenantMikrotik::with(['openvpnProfile', 'logs', 'bandwidthUsage', 'alerts'])
            ->orderByDesc('last_seen_at')
            ->get();

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
        ]);
    }

    /**
     * Show single router details.
     */
    public function show($id)
    {
        $router = TenantMikrotik::with(['openvpnProfile', 'logs', 'bandwidthUsage', 'activeSessions', 'alerts'])
            ->findOrFail($id);

        return Inertia::render('Mikrotiks/Show', compact('router'));
    }

    /**
     * Get router status (for frontend status checking).
     * Returns current router status from database without testing connection.
     */
    public function getStatus($id)
    {
        $router = TenantMikrotik::findOrFail($id);
        
        // Refresh router data from database
        $router->refresh();
        
        // Check if router should be marked offline based on last_seen_at (> 4 minutes)
        if ($this->isRouterStale($router) && $router->status === 'online') {
            $router->status = 'offline';
            $router->save();
        }

        return response()->json([
            'success' => true,
            'status' => $router->status,
            'last_seen_at' => $router->last_seen_at?->toIso8601String(),
            'ip_address' => $router->ip_address,
        ]);
    }

    /**
     * Store a new Mikrotik and show onboarding script.
     */
    public function store(Request $request, MikrotikScriptGenerator $scriptGenerator)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'router_username' => 'required|string|max:255',
            'router_password' => 'required|string|min:1',
            'notes' => 'nullable|string',
            'api_port' => 'nullable|integer|min:1|max:65535',
        ]);

        // Ensure API port is set (default 8728)
        $apiPort = $data['api_port'] ?? 8728;

        $router = TenantMikrotik::create([
            'name' => $data['name'],
            'router_username' => $data['router_username'],
            'router_password' => $data['router_password'],
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
        $trustedIp = config('app.server_ip') ?? request()->server('SERVER_ADDR') ?? '207.154.204.144';
        
        // Ensure API port is set (should already be set in create, but double-check)
        $apiPort = $router->api_port ?? 8728;
        
        $script = $scriptGenerator->generate([
            'name' => $router->name,
            'username' => $router->router_username,
            'router_password' => $router->router_password,
            'router_id' => $router->id,
            'sync_token' => $router->sync_token,
            'ca_url' => $caUrl,
            'api_port' => $apiPort, // Use the stored API port
            'trusted_ip' => $trustedIp,
            'radius_ip' => '207.154.204.144', // TODO: Get from tenant settings
            'radius_secret' => 'ZyraafSecret123', // TODO: Get from tenant settings
        ]);
        
        // Log the created router details for debugging
        Log::info('MikroTik router created', [
            'router_id' => $router->id,
            'name' => $router->name,
            'username' => $router->router_username,
            'api_port' => $apiPort,
            'trusted_ip' => $trustedIp,
        ]);

        return Inertia::render('Mikrotiks/SetupScript', [
            'router' => $router,
            'script' => $script,
        ]);
    }

    /**
     * Update router details & recheck connection.
     */
    public function update(Request $request, $id)
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

        if (empty($data['router_password'])) unset($data['router_password']);
        $router->update($data);

        $isOnline = $this->testRouterConnection($router);
        $router->status = $isOnline ? 'online' : 'offline';
        if ($isOnline) $router->last_seen_at = now();
        $router->save();

        $router->logs()->create([
            'action' => 'update',
            'message' => $isOnline ? 'Router is online after update.' : 'Router offline after update.',
            'status' => $isOnline ? 'success' : 'failed',
        ]);

        return redirect()->route('mikrotiks.index')->with('success', 'Router updated!');
    }

    public function destroy($id)
    {
        TenantMikrotik::findOrFail($id)->delete();
        return redirect()->route('mikrotiks.index')->with('success', 'Router deleted!');
    }

    /**
     * Check router connectivity.
     */
    public function testConnection($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        $isOnline = $this->testRouterConnection($router);

        return response()->json([
            'success' => $isOnline,
            'message' => $isOnline ? 'Router is online.' : 'Router is offline.',
            'status' => $router->status,
            'last_seen_at' => $router->last_seen_at,
        ]);
    }

    /**
     * Set router IP address.
     */
    public function setIp(Request $request, $id)
    {
        $router = TenantMikrotik::findOrFail($id);

        $data = $request->validate([
            'ip_address' => 'required|string|max:255',
        ]);

        // Extract IP from CIDR notation if present (e.g., "192.168.1.1/24" -> "192.168.1.1")
        $ip = $data['ip_address'];
        if (strpos($ip, '/') !== false) {
            $ip = explode('/', $ip)[0];
        }

        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IP address format.',
            ], 422);
        }

        $oldIp = $router->ip_address;
        $router->ip_address = $ip;
        $router->save();

        Log::info('Router IP address set', [
            'router_id' => $router->id,
            'old_ip' => $oldIp,
            'new_ip' => $ip,
            'username' => $router->router_username,
            'api_port' => $router->api_port,
        ]);

        $router->logs()->create([
            'action' => 'set_ip',
            'message' => "IP address set to: $ip (API port: {$router->api_port}, Username: {$router->router_username})",
            'status' => 'success',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'IP address set successfully.',
            'ip_address' => $router->ip_address,
            'api_port' => $router->api_port,
            'username' => $router->router_username,
        ]);
    }

    /**
     * Used by the Vue SetupScript.vue component.
     * Pings the router's IP address via API connection test.
     */
    public function pingRouter($id)
    {
        $router = TenantMikrotik::findOrFail($id);
        
        // Refresh router data from database
        $router->refresh();
        
        // Check if router has an IP address
        if (!$router->ip_address) {
            return response()->json([
                'success' => false,
                'status' => 'pending',
                'message' => 'Please set the router IP address first.',
                'ip_address' => null,
                'last_seen_at' => $router->last_seen_at,
            ], 400);
        }

        // Check if router should be marked offline based on last_seen_at (> 4 minutes)
        if ($this->isRouterStale($router)) {
            $router->status = 'offline';
            $router->save();
        }

        // Test API connection to the router's IP address
        $isOnline = $this->testRouterConnection($router);

        return response()->json([
            'success' => $isOnline,
            'status' => $isOnline ? 'online' : 'offline',
            'message' => $isOnline 
                ? 'Router is online and responding via API!' 
                : 'Router is not responding. Please verify: 1) Router is powered on and online, 2) IP address is correct (' . $router->ip_address . '), 3) API service is enabled on port ' . ($router->api_port ?? 8728) . ', 4) Username and password are correct, 5) Firewall allows API connections from this server.',
            'last_seen_at' => $router->last_seen_at,
            'ip_address' => $router->ip_address,
            'api_port' => $router->api_port ?? 8728,
            'username' => $router->router_username,
        ]);
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
        $trustedIp = config('app.server_ip') ?? request()->server('SERVER_ADDR') ?? '207.154.204.144';
        
        $script = $scriptGenerator->generate([
            'name' => $router->name,
            'username' => $router->router_username,
            'router_password' => $router->router_password,
            'router_id' => $router->id,
            'sync_token' => $router->sync_token,
            'ca_url' => $caUrl,
            'api_port' => $router->api_port ?? 8728,
            'trusted_ip' => $trustedIp,
            'radius_ip' => '207.154.204.144', // TODO: Get from tenant settings
            'radius_secret' => 'ZyraafSecret123', // TODO: Get from tenant settings
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
     * Reprovision router - regenerate onboarding script.
     */
    public function reprovision($id, MikrotikScriptGenerator $scriptGenerator)
    {
        $router = TenantMikrotik::findOrFail($id);

        $caUrl = optional($router->openvpnProfile)->ca_cert_path
            ? route('mikrotiks.downloadCACert', $router->id)
            : null;

        // Get server IP (trusted IP) - use config or request IP
        $trustedIp = config('app.server_ip') ?? request()->server('SERVER_ADDR') ?? '207.154.204.144';
        
        $script = $scriptGenerator->generate([
            'name' => $router->name,
            'username' => $router->router_username,
            'router_password' => $router->router_password,
            'router_id' => $router->id,
            'sync_token' => $router->sync_token,
            'ca_url' => $caUrl,
            'api_port' => $router->api_port ?? 8728,
            'trusted_ip' => $trustedIp,
            'radius_ip' => '207.154.204.144', // TODO: Get from tenant settings
            'radius_secret' => 'ZyraafSecret123', // TODO: Get from tenant settings
        ]);

        return Inertia::render('Mikrotiks/SetupScript', compact('router', 'script'));
    }

    /**
     * Handle router phone-home sync endpoint.
     * This endpoint is called by the MikroTik router after running the onboarding script.
     */
    public function sync($mikrotik, Request $request)
    {
        try {
            $router = TenantMikrotik::findOrFail($mikrotik);

            // Validate sync token
            $token = $request->query('token') ?? $request->input('token');
            if (!$token || $token !== $router->sync_token) {
                Log::warning('Invalid sync token attempt', [
                    'router_id' => $router->id,
                    'provided_token' => $token ? 'present' : 'missing',
                    'client_ip' => $request->ip(),
                ]);
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid sync token.'
                ], 403);
            }

            // Get router IP from request (if provided) or use client IP
            $routerIp = $request->input('ip_address') ?? $request->ip();
            
            // Validate IP address
            if ($routerIp && !filter_var($routerIp, FILTER_VALIDATE_IP)) {
                Log::warning('Invalid IP address in sync request', [
                    'router_id' => $router->id,
                    'ip' => $routerIp,
                ]);
                $routerIp = $request->ip(); // Fallback to client IP
            }
            
            $updateData = [
                'status' => 'online',
                'last_seen_at' => now(),
            ];
            
            // Update IP address if provided and different (or if not set)
            if ($routerIp && ($routerIp !== $router->ip_address || !$router->ip_address)) {
                $updateData['ip_address'] = $routerIp;
            }

            $router->update($updateData);

            // Log the sync
            $router->logs()->create([
                'action' => 'sync',
                'message' => 'Router phone-home sync received. IP: ' . ($routerIp ?? 'not provided'),
                'status' => 'success',
                'response_data' => [
                    'ip_address' => $routerIp,
                    'client_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);

            Log::info('Router sync successful', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'ip_address' => $router->ip_address,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sync received. Router marked online.',
                'status' => 'online',
                'ip_address' => $router->ip_address,
                'last_seen_at' => $router->last_seen_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Router sync failed', [
                'router_id' => $mikrotik,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register WireGuard peer information from router phone-home.
     */
    public function registerWireguard($mikrotik, Request $request)
    {
        try {
            $router = TenantMikrotik::findOrFail($mikrotik);

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
            $wgAddress = $request->input('wg_address');

            if (!$wgPublicKey) {
                return response()->json(['success' => false, 'message' => 'Missing wg_public_key'], 422);
            }

            // Basic validation of public key length
            if (strlen($wgPublicKey) < 32 || strlen($wgPublicKey) > 128) {
                return response()->json(['success' => false, 'message' => 'Invalid wg_public_key'], 422);
            }

            // Store public key and address (derive if missing) and mark pending
            $router->wireguard_public_key = $wgPublicKey;
            $assignedAddress = null;
            if ($wgAddress && filter_var($wgAddress, FILTER_VALIDATE_IP)) {
                $assignedAddress = $wgAddress;
            } else {
                // Derive deterministic client IP from configured WG_SUBNET and router id
                $subnet = config('wireguard.subnet') ?? env('WG_SUBNET', '10.254.0.0/16');
                if (strpos($subnet, '/') !== false) {
                    [$network, $prefix] = explode('/', $subnet, 2);
                    $prefix = (int)$prefix;
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
                $router->wireguard_allowed_ips = $assignedAddress . '/32';
            }
            $router->wireguard_status = 'pending';
            $router->save();

            // Log
            $router->logs()->create([
                'action' => 'wg_register',
                'message' => 'WireGuard public key received and stored',
                'status' => 'success',
                'response_data' => [
                    'wg_public_key' => substr($wgPublicKey, 0, 16) . '...'
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

        // Check if last_seen_at is more than 4 minutes ago
        $fourMinutesAgo = now()->subMinutes(4);
        return $router->last_seen_at->lt($fourMinutesAgo);
    }

    /**
     * Shared logic to test router connectivity.
     */
    private function testRouterConnection(TenantMikrotik $router): bool
    {
        try {
            if (!$router->ip_address) {
                Log::warning('Router test skipped: No IP address', ['router_id' => $router->id]);
                return false;
            }

            // Use the exact credentials and port from database
            $apiPort = $router->api_port ?? 8728;
            $useSsl = $router->use_ssl ?? false;

            // Log connection attempt details (without password)
            Log::info('Testing router connection', [
                'router_id' => $router->id,
                'ip_address' => $router->ip_address,
                'username' => $router->router_username,
                'api_port' => $apiPort,
                'use_ssl' => $useSsl,
            ]);

            $service = MikrotikService::forMikrotik($router)
                ->setConnection(
                    $router->ip_address,
                    $router->router_username,
                    $router->router_password,
                    $apiPort,
                    $useSsl
                );

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
                    $router->uptime = isset($resource['uptime']) ? (int)$resource['uptime'] : $router->uptime;
                    $router->cpu_usage = isset($resource['cpu-load']) ? (float)$resource['cpu-load'] : $router->cpu_usage;
                    $router->memory_usage = isset($resource['free-memory']) && isset($resource['total-memory']) 
                        ? round((1 - ($resource['free-memory'] / $resource['total-memory'])) * 100, 2)
                        : $router->memory_usage;
                }
                
                Log::info('Router connection successful', [
                    'router_id' => $router->id,
                    'ip_address' => $router->ip_address,
                ]);
            } else {
                // Check if router should be marked offline due to stale last_seen_at
                if ($this->isRouterStale($router)) {
                    $router->status = 'offline';
                    Log::warning('Router marked offline: Connection failed and last_seen_at > 4 minutes', [
                        'router_id' => $router->id,
                        'ip_address' => $router->ip_address,
                        'last_seen_at' => $router->last_seen_at,
                    ]);
                } else {
                    // Connection failed but last_seen_at is recent, keep current status
                    Log::warning('Router connection failed: No response', [
                        'router_id' => $router->id,
                        'ip_address' => $router->ip_address,
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
            Log::error("Router connection test failed", [
                'router_id' => $router->id,
                'ip_address' => $router->ip_address,
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
     */
    public function validateRouter(Request $request)
    {
        $data = $request->validate([
            'ip_address' => 'required|ip',
            'router_username' => 'required|string',
            'router_password' => 'required|string',
            'api_port' => 'nullable|integer|min:1|max:65535',
        ]);

        try {
            $service = new MikrotikService();
            $service->setConnection(
                $data['ip_address'],
                $data['router_username'],
                $data['router_password'],
                $data['api_port'] ?? 8728,
                false
            );

            $resources = $service->testConnection();
            $isValid = $resources !== false;

            return response()->json([
                'success' => $isValid,
                'message' => $isValid ? 'Connection successful.' : 'Connection failed.',
                'resources' => $isValid ? $resources : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
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
     */
    public function remoteManagement($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        if (!$router->ip_address) {
            return response()->json([
                'success' => false,
                'message' => 'Router IP address not configured.',
            ], 400);
        }

        $sshPort = $router->ssh_port ?? 22;
        $apiPort = $router->api_port ?? 8728;
        
        $links = [
            'winbox' => "winbox://{$router->ip_address}",
            'ssh' => "ssh://{$router->router_username}@{$router->ip_address}:{$sshPort}",
            'api' => "http://{$router->ip_address}:{$apiPort}",
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
        $radius_ip = '207.154.204.144'; // TODO: Get from tenant settings
        $radius_secret = 'ZyraafSecret123'; // TODO: Get from tenant settings

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

        if (!str_contains($trustedIp, '/')) {
            $trustedIp .= '/32';
        }

        return $trustedIp;
    }
}
