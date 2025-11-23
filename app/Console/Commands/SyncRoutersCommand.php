<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncRoutersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'routers:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync router status via RouterOS API polling';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting router sync...');
        
        $routers = TenantMikrotik::all();
        $synced = 0;
        $failed = 0;
        
        foreach ($routers as $router) {
            try {
                // Skip routers without VPN IP (MikrotikService will throw exception, catch it below)
                $vpnIp = $router->wireguard_address ?? $router->ip_address;
                if (!$vpnIp) {
                    $this->warn("Router {$router->id} ({$router->name}) has no VPN IP configured, skipping...");
                    continue;
                }
                
                // Use the same approach as pingRouter - MikrotikService handles VPN IP detection
                // Set timeout to 3 seconds max to avoid blocking
                $service = MikrotikService::forMikrotik($router);
                
                // Wrap in try-catch with timeout handling
                $startTime = microtime(true);
                $resources = $service->testConnection();
                $elapsed = microtime(true) - $startTime;
                
                $isOnline = $resources !== false;
                
                if ($isOnline) {
                    // Update router status
                    $updateData = [
                        'status' => 'online',
                        'last_seen_at' => now(),
                    ];
                    
                    // Update online field if column exists
                    if (Schema::hasColumn('tenant_mikrotiks', 'online')) {
                        $updateData['online'] = true;
                    }
                    
                    // Update CPU, memory, uptime from resources
                    if (is_array($resources) && !empty($resources[0])) {
                        $resource = $resources[0];
                        
                        if (isset($resource['cpu-load'])) {
                            $cpuValue = (float)$resource['cpu-load'];
                            $updateData['cpu_usage'] = $cpuValue;
                            if (Schema::hasColumn('tenant_mikrotiks', 'cpu')) {
                                $updateData['cpu'] = $cpuValue;
                            }
                        }
                        
                        if (isset($resource['free-memory']) && isset($resource['total-memory'])) {
                            $memoryUsed = $resource['total-memory'] - $resource['free-memory'];
                            $memoryPercent = round(($memoryUsed / $resource['total-memory']) * 100, 2);
                            $updateData['memory_usage'] = $memoryPercent;
                            if (Schema::hasColumn('tenant_mikrotiks', 'memory')) {
                                $updateData['memory'] = $memoryPercent;
                            }
                        }
                        
                        if (isset($resource['uptime'])) {
                            $updateData['uptime'] = (int)$resource['uptime'];
                        }
                    }
                    
                    $router->update($updateData);
                    $synced++;
                    $this->info("Router {$router->id} ({$router->name}) synced successfully ({$elapsed}s)");
                } else {
                    // Router is offline
                    $updateData = ['status' => 'offline'];
                    if (Schema::hasColumn('tenant_mikrotiks', 'online')) {
                        $updateData['online'] = false;
                    }
                    $router->update($updateData);
                    $synced++;
                    
                    Log::warning('Router sync: Router is offline', [
                        'router_id' => $router->id,
                        'router_name' => $router->name,
                        'vpn_ip' => $vpnIp,
                    ]);
                    
                    $this->warn("Router {$router->id} ({$router->name}) is offline");
                }
            } catch (\Exception $e) {
                $failed++;
                $errorMessage = $e->getMessage();
                
                // Determine error type for better logging
                $errorType = 'unknown';
                if (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'Connection timed out')) {
                    $errorType = 'timeout';
                } elseif (str_contains($errorMessage, 'authentication') || str_contains($errorMessage, 'password') || str_contains($errorMessage, 'login')) {
                    $errorType = 'authentication_failed';
                } elseif (str_contains($errorMessage, 'VPN IP') || str_contains($errorMessage, 'not set')) {
                    $errorType = 'no_vpn_ip';
                    // Skip logging for missing VPN IP (already warned above)
                    continue;
                }
                
                Log::error('Router sync failed', [
                    'router_id' => $router->id,
                    'router_name' => $router->name,
                    'error_type' => $errorType,
                    'error' => $errorMessage,
                    'vpn_ip' => $router->wireguard_address ?? $router->ip_address ?? 'not configured',
                ]);
                
                // Mark as offline on error
                $updateData = ['status' => 'offline'];
                if (Schema::hasColumn('tenant_mikrotiks', 'online')) {
                    $updateData['online'] = false;
                }
                $router->update($updateData);
                
                $this->error("Router {$router->id} ({$router->name}) sync failed: {$errorType} - " . substr($errorMessage, 0, 100));
            }
        }
        
        $this->info("Sync complete: {$synced} routers synced, {$failed} failed");
        
        return Command::SUCCESS;
    }
}
