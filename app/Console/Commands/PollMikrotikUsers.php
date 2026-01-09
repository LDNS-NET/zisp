<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikUserSyncService;

class PollMikrotikUsers extends Command
{
    protected $signature = 'app:poll-mikrotik-users';
    protected $description = 'Poll all Mikrotik routers for active users and sync to DB';

    public function handle(MikrotikUserSyncService $syncService)
    {
        // Only poll routers that are currently online
        $routers = TenantMikrotik::where('status', 'online')->get();
        
        if ($routers->isEmpty()) {
            $this->info('No online routers to poll.');
            return 0;
        }

        $totalOnline = 0;
        $totalOffline = 0;

        foreach ($routers as $router) {
            $this->info("Polling Mikrotik router: {$router->id} ({$router->name})");
            
            try {
                $result = $syncService->syncActiveUsers($router);
                
                $onlineCount = count($result['online'] ?? []);
                $offlineCount = count($result['offline'] ?? []);
                $totalOnline += $onlineCount;
                $totalOffline += $offlineCount;
                
                if ($onlineCount > 0) {
                    $this->info("  ✓ Online: " . implode(', ', $result['online']));
                }
                if ($offlineCount > 0) {
                    $this->warn("  ✗ Offline: " . implode(', ', $result['offline']));
                }
                if ($onlineCount === 0 && $offlineCount === 0) {
                    $this->line("  - No status changes detected");
                }
            } catch (\Exception $e) {
                $this->error("  Error polling router: " . $e->getMessage());
            }
        }
        
        $this->newLine();
        $this->info("Polling complete. Total changes: $totalOnline online, $totalOffline offline");
        return 0;
    }
}
