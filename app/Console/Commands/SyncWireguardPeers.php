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

        // Use the optimized batch sync method for all operations
        // This handles both "sync all" and "sync pending" efficiently
        // and includes idempotency checks to prevent unnecessary reloads.

        $this->info('Running optimized batch sync...');

        $results = $this->wgService->syncAllPeers();

        $success = $results['added'] + $results['updated'];
        $failed = $results['failed'];
        $removed = $results['removed'] ?? 0;

        $this->newLine();
        $this->info("Sync complete:");
        $this->line("  - Added: {$results['added']}");
        $this->line("  - Updated: {$results['updated']}");
        $this->line("  - Removed: {$removed}");
        $this->line("  - Failed: {$failed}");

        if ($results['changes_detected'] ?? false) {
            $this->info("✅ Configuration updated and interface reloaded.");
        } else {
            $this->info("ℹ️ No changes detected. Interface reload skipped.");
        }

        if (!empty($results['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line('  - ' . $error);
            }
        }

        $bar->finish();
        $this->newLine();

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
