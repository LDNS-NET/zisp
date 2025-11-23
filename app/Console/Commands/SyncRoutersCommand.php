<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\Mikrotik\RouterApiService;
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
                            // Also update wireguard_address for consistency if column exists
                            if (Schema::hasColumn('tenant_mikrotiks', 'wireguard_address') && !$router->wireguard_address) {
                                $router->wireguard_address = $ip;
                                $router->save();
                            }
                        }
                    }
                }
                
                // Skip if no VPN IP configured
                if (!$vpnIp) {
                    $this->warn("Router {$router->id} ({$router->name}) has no VPN IP configured (neither wireguard_address nor ip_address in 10.100.0.0/16), skipping...");
                    continue;
                }
                
                $apiService = new RouterApiService($router);
                
                // Check online status
                $isOnline = $apiService->isOnline();
                
                if ($isOnline) {
                    // Get system resource information
                    $resources = $apiService->getSystemResource();
                    
                    if ($resources !== false) {
                        // Update router status
                        $updateData = [
                            'status' => 'online',
                            'last_seen_at' => now(),
                        ];
                        
                        // Update online field if column exists
                        if (Schema::hasColumn('tenant_mikrotiks', 'online')) {
                            $updateData['online'] = true;
                        }
                        
                        // Update CPU, memory, uptime
                        if (isset($resources['cpu-load'])) {
                            $cpuValue = (float)$resources['cpu-load'];
                            $updateData['cpu_usage'] = $cpuValue;
                            if (Schema::hasColumn('tenant_mikrotiks', 'cpu')) {
                                $updateData['cpu'] = $cpuValue;
                            }
                        }
                        
                        if (isset($resources['free-memory']) && isset($resources['total-memory'])) {
                            $memoryUsed = $resources['total-memory'] - $resources['free-memory'];
                            $memoryPercent = round(($memoryUsed / $resources['total-memory']) * 100, 2);
                            $updateData['memory_usage'] = $memoryPercent;
                            if (Schema::hasColumn('tenant_mikrotiks', 'memory')) {
                                $updateData['memory'] = $memoryPercent;
                            }
                        }
                        
                        if (isset($resources['uptime'])) {
                            $updateData['uptime'] = (int)$resources['uptime'];
                        }
                        
                        $router->update($updateData);
                        
                        // Get identity
                        $identity = $apiService->getIdentity();
                        if ($identity !== false) {
                            // Identity is already stored in name field, but we can update if needed
                        }
                        
                        // Get active sessions
                        $hotspotActive = $apiService->getHotspotActive();
                        $pppoeActive = $apiService->getPppoeActive();
                        
                        // Store active sessions count (if you have a field for this)
                        // For now, we'll just log it
                        
                        $router->save();
                        
                        $synced++;
                        $this->info("Router {$router->id} ({$router->name}) synced successfully");
                    } else {
                        // Online but couldn't get resources
                        $updateData = ['status' => 'online'];
                        if (Schema::hasColumn('tenant_mikrotiks', 'online')) {
                            $updateData['online'] = true;
                        }
                        $router->update($updateData);
                        $synced++;
                    }
                } else {
                    // Router is offline
                    $updateData = ['status' => 'offline'];
                    if (Schema::hasColumn('tenant_mikrotiks', 'online')) {
                        $updateData['online'] = false;
                    }
                    $router->update($updateData);
                    $synced++;
                    $this->warn("Router {$router->id} ({$router->name}) is offline");
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('Router sync failed', [
                    'router_id' => $router->id,
                    'router_name' => $router->name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Mark as offline on error
                $updateData = ['status' => 'offline'];
                if (Schema::hasColumn('tenant_mikrotiks', 'online')) {
                    $updateData['online'] = false;
                }
                $router->update($updateData);
                
                $this->error("Router {$router->id} ({$router->name}) sync failed: " . $e->getMessage());
            }
        }
        
        $this->info("Sync complete: {$synced} routers synced, {$failed} failed");
        
        return Command::SUCCESS;
    }
}
