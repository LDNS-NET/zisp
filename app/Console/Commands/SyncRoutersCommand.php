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
                // Use the same approach as pingRouter - MikrotikService handles VPN IP detection
                $service = MikrotikService::forMikrotik($router);
                $resources = $service->testConnection();
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
                    $this->info("Router {$router->id} ({$router->name}) synced successfully");
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
