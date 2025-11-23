<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;

class CheckMikrotikStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of all MikroTik routers and update the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting MikroTik status check...');

        // Get all routers that have a VPN IP address configured
        // All router communication must use VPN tunnel IP (10.100.0.0/16) only
        $routers = TenantMikrotik::where(function($query) {
            $query->whereNotNull('wireguard_address')
                  ->where('wireguard_address', '!=', '')
                  ->orWhere(function($q) {
                      // Legacy: also check ip_address if it's in VPN subnet
                      $q->whereNotNull('ip_address')
                        ->where('ip_address', '!=', '');
                  });
        })->get();

        if ($routers->isEmpty()) {
            $this->info('No routers with VPN IP addresses found.');
            return 0;
        }

        $this->info("Checking {$routers->count()} router(s) via VPN tunnel...");

        $onlineCount = 0;
        $offlineCount = 0;
        $errorCount = 0;
        $staleCount = 0;

        foreach ($routers as $router) {
            try {
                // Get VPN IP for display
                $vpnIp = $router->wireguard_address ?? $router->ip_address ?? 'Not configured';
                $this->line("Checking router: {$router->name} (VPN IP: {$vpnIp})...");

                // First, check if router should be marked offline based on last_seen_at
                if ($this->isRouterStale($router)) {
                    $router->status = 'offline';
                    $router->save();
                    $staleCount++;
                    $offlineCount++;
                    $this->warn("  ⏱ Router '{$router->name}' marked offline (last seen > 4 minutes ago)");
                    continue;
                }

                // Use the existing testRouterConnection logic from the controller
                $isOnline = $this->testRouterConnection($router);

                if ($isOnline) {
                    $onlineCount++;
                    $this->info("  ✓ Router '{$router->name}' is online");
                } else {
                    $offlineCount++;
                    $this->warn("  ✗ Router '{$router->name}' is offline");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("  ✗ Error checking router '{$router->name}': " . $e->getMessage());
                Log::error('MikroTik status check error', [
                    'router_id' => $router->id,
                    'router_name' => $router->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("\nStatus check complete:");
        $this->info("  Online: {$onlineCount}");
        $this->info("  Offline: {$offlineCount}");
        if ($staleCount > 0) {
            $this->warn("  Marked stale (>4min): {$staleCount}");
        }
        if ($errorCount > 0) {
            $this->warn("  Errors: {$errorCount}");
        }

        return 0;
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
     * Test router connection via VPN tunnel only.
     * All router communication must use VPN IP (wireguard_address) from 10.100.0.0/16 subnet.
     *
     * @param TenantMikrotik $router
     * @return bool
     */
    private function testRouterConnection(TenantMikrotik $router): bool
    {
        try {
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
            
            // Ensure router has a VPN tunnel IP before attempting connection
            if (!$vpnIp) {
                Log::warning('Router test skipped: No VPN IP address configured', ['router_id' => $router->id]);
                return false;
            }

            $apiPort = $router->api_port ?? 8728;
            $useSsl = $router->use_ssl ?? false;

            $service = MikrotikService::forMikrotik($router);

            // The service will automatically use the router's VPN IP.
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
                
                Log::debug('Router connection successful', [
                    'router_id' => $router->id,
                    'ip_address' => $router->ip_address,
                ]);
            } else {
                // Check if router should be marked offline due to stale last_seen_at
                if ($this->isRouterStale($router)) {
                    $router->status = 'offline';
                    Log::debug('Router marked offline: Connection failed via VPN tunnel and last_seen_at > 4 minutes', [
                        'router_id' => $router->id,
                        'vpn_ip' => $vpnIp,
                        'last_seen_at' => $router->last_seen_at,
                    ]);
                } else {
                    // Connection failed but last_seen_at is recent, keep current status
                    Log::debug('Router connection failed via VPN tunnel: No response', [
                        'router_id' => $router->id,
                        'vpn_ip' => $vpnIp,
                    ]);
                }
            }
            
            $router->save();

            return $isOnline;
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            Log::error("Router connection test failed via VPN tunnel", [
                'router_id' => $router->id,
                'vpn_ip' => $vpnIp ?? 'not configured',
                'api_port' => $router->api_port ?? 8728,
                'error' => $errorMessage,
            ]);
            
            $router->status = 'offline';
            $router->save();
            
            return false;
        }
    }
}

