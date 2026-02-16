<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikUserSyncService;

class PollMikrotikUsers extends Command
{
    protected $signature = 'app:poll-mikrotik-users';
    protected $description = 'Poll all Mikrotik routers for active users (optimized: only syncs changes)';

    public function handle()
    {
        // Only poll routers marked as online
        $routers = TenantMikrotik::where('online', true)->get();
        
        if ($routers->isEmpty()) {
            $this->info('No online routers to poll.');
            return 0;
        }

        $this->info("Dispatching sync jobs for " . $routers->count() . " routers...");

        foreach ($routers as $router) {
            \App\Jobs\SyncMikrotikActiveUsersJob::dispatch($router);
        }

        $this->info("Polling jobs dispatched.");
        return 0;
    }
}
