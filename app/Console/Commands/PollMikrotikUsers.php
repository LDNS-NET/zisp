<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikUserSyncService;

class PollMikrotikUsers extends Command
{
    protected $signature = 'app:poll-mikrotik-users';
    protected $description = 'Poll all Mikrotik routers for active users (optimized: only syncs changes)';

    public function handle(MikrotikUserSyncService $syncService)
    {
        $routers = TenantMikrotik::all();
        $totalChanges = 0;

        foreach ($routers as $router) {
            $result = $syncService->syncActiveUsers($router);
            $totalChanges += $result['synced'];

            if ($result['synced'] > 0) {
                $this->info("Router {$router->id}: {$result['synced']} changes (online: " . count($result['online']) . ", offline: " . count($result['offline']) . ")");
            }
        }

        $this->info("Polling complete. Total changes: {$totalChanges}");
        return 0;
    }
}
