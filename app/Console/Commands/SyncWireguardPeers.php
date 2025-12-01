<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\WireGuardService;

class SyncWireguardPeers extends Command
{
    protected $signature = 'wireguard:sync-peers 
                            {--all : Sync all peers regardless of status}
                            {--reconcile : Reconcile database with configuration file}';

    protected $description = 'Sync WireGuard peers from database to server (apply new/updated peers)';

    protected WireGuardService $wgService;

    public function __construct(WireGuardService $wgService)
    {
        parent::__construct();
        $this->wgService = $wgService;
    }

    public function handle()
    {
        if ($this->option('reconcile')) {
            return $this->reconcile();
        }

        $this->info('Starting WireGuard peers sync...');

        $query = TenantMikrotik::query();
        if (!$this->option('all')) {
            $query->whereNotNull('wireguard_public_key')->where('wireguard_status', '!=', 'active');
        } else {
            $query->whereNotNull('wireguard_public_key');
        }

        $routers = $query->get();

        if ($routers->isEmpty()) {
            $this->info('No WireGuard peers to sync');
            return 0;
        }

        $this->info("Found {$routers->count()} peer(s) to sync");

        $bar = $this->output->createProgressBar($routers->count());
        $bar->start();

        $success = 0;
        $failed = 0;
        $processedRouters = [];

        // 1. Update config file for all peers (without reloading interface)
        foreach ($routers as $router) {
            // Pass false to skip reload
            if ($this->wgService->applyPeer($router, false)) {
                $success++;
                $processedRouters[] = $router;
            } else {
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // 2. Reload interface once if there were successes
        if ($success > 0) {
            $this->info('Reloading WireGuard interface...');

            if ($this->wgService->applyConfigSafely()) {
                $this->info('Interface reloaded successfully.');

                // 3. Update status to active for all processed routers
                foreach ($processedRouters as $router) {
                    $router->wireguard_status = 'active';
                    $router->save();
                }
                $this->info("Updated status for {$success} peers.");
            } else {
                $this->error('Failed to reload interface. Peers added to config but not active.');
                return 1;
            }
        }

        $this->info("Sync complete: {$success} succeeded, {$failed} failed");

        return 0;
    }

    protected function reconcile()
    {
        $this->info('Starting WireGuard configuration reconciliation...');
        $this->info('This will sync all database peers to the configuration file');

        $results = $this->wgService->syncAllPeers();

        $this->newLine();
        $this->info('Reconciliation complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Added', $results['added']],
                ['Updated', $results['updated']],
                ['Failed', $results['failed']],
            ]
        );

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line('  - ' . $error);
            }
        }

        return $results['failed'] > 0 ? 1 : 0;
    }
}
