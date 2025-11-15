<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Jobs\ApplyWireGuardPeer;

class SyncWireguardPeers extends Command
{
    protected $signature = 'wireguard:sync-peers {--all : Sync all peers regardless of status}';
    protected $description = 'Sync WireGuard peers from database to server (apply new/updated peers)';

    public function handle()
    {
        $this->info('Starting WireGuard peers sync...');

        $query = TenantMikrotik::query();
        if (! $this->option('all')) {
            $query->whereNotNull('wireguard_public_key')->where('wireguard_status', '!=', 'active');
        } else {
            $query->whereNotNull('wireguard_public_key');
        }

        $routers = $query->get();

        if ($routers->isEmpty()) {
            $this->info('No WireGuard peers to sync');
            return 0;
        }

        foreach ($routers as $router) {
            ApplyWireGuardPeer::dispatch($router);
            $this->info('Dispatched job for router: ' . $router->id);
        }

        $this->info('Dispatched all jobs');

        return 0;
    }
}
