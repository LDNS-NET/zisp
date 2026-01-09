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
        $routers = TenantMikrotik::all();
        foreach ($routers as $router) {
            $this->info("Polling Mikrotik router: {$router->id}");
            $updated = $syncService->syncActiveUsers($router);
            $this->info("Online users: " . implode(', ', $updated));
        }
        $this->info('Polling complete.');
        return 0;
    }
}
