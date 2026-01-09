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

        foreach ($routers as $router) {
            $this->info("Polling Mikrotik router: {$router->id}");
            $result = $syncService->syncActiveUsers($router);
            $onlineUsers = implode(', ', $result['online'] ?? []);
            $offlineUsers = implode(', ', $result['offline'] ?? []);
            if (!empty($result['online'])) {
                $this->info("Online: " . $onlineUsers);
            }
            if (!empty($result['offline'])) {
                $this->warn("Offline: " . $offlineUsers);
            }
        }
        $this->info('Polling complete.');
        return 0;
    }
}
