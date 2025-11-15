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

        // Get all routers that have an IP address configured
        $routers = TenantMikrotik::whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->get();

        if ($routers->isEmpty()) {
            $this->info('No routers with IP addresses found.');
            return 0;
        }

        $this->info("Checking {$routers->count()} router(s)...");

        $onlineCount = 0;
        $offlineCount = 0;
        $errorCount = 0;
        $staleCount = 0;

        foreach ($routers as $router) {
            try {
                $this->line("Checking router: {$router->name} ({$router->ip_address})...");

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
     * Test router connection using the same logic as the controller.
     *
     * @param TenantMikrotik $router
     * @return bool
     */
    private function testRouterConnection(TenantMikrotik $router): bool
    {
        try {
            if (!$router->ip_address) {
                Log::warning('Router test skipped: No IP address', ['router_id' => $router->id]);
                return false;
            }

            $apiPort = $router->api_port ?? 8728;
            $useSsl = $router->use_ssl ?? false;

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
                
                Log::debug('Router connection successful', [
                    'router_id' => $router->id,
                    'ip_address' => $router->ip_address,
                ]);
            } else {
                // Check if router should be marked offline due to stale last_seen_at
                if ($this->isRouterStale($router)) {
                    $router->status = 'offline';
                    Log::debug('Router marked offline: Connection failed and last_seen_at > 4 minutes', [
                        'router_id' => $router->id,
                        'ip_address' => $router->ip_address,
                        'last_seen_at' => $router->last_seen_at,
                    ]);
                } else {
                    // Connection failed but last_seen_at is recent, keep current status
                    Log::debug('Router connection failed: No response', [
                        'router_id' => $router->id,
                        'ip_address' => $router->ip_address,
                    ]);
                }
            }
            
            $router->save();

            return $isOnline;
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            Log::error("Router connection test failed", [
                'router_id' => $router->id,
                'ip_address' => $router->ip_address,
                'api_port' => $router->api_port ?? 8728,
                'error' => $errorMessage,
            ]);
            
            $router->status = 'offline';
            $router->save();
            
            return false;
        }
    }
}

