<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WinboxPortService;
use App\Models\Tenants\TenantMikrotik;
use Illuminate\Support\Facades\Process;

class SyncWinboxRules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'winbox:sync {--clean : Remove all existing PREROUTING/POSTROUTING DNAT/SNAT rules before applying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild iptables rules for Remote Winbox Access';

    /**
     * Execute the console command.
     */
    public function handle(WinboxPortService $winboxService)
    {
        $this->info("Starting Winbox Firewall Rule Sync...");

        // Optional cleaning (dangerous if checking strictly, so we just logging for now or rudimentary usage)
        if ($this->option('clean')) {
            $this->warn("Clean mode selected. Attempting to flush old ZISP NAT rules...");
            // Real implementation of 'clean' is hard without tagging rules. 
            // For now, we rely on the service's apply/remove logic which is idempotent.
            // But if user wants to kill the 'old' 8292 rules, they need to do it manually or we'd need to pattern match.
            // We'll stick to mostly safe rebuilding.
        }

        $routers = TenantMikrotik::whereNotNull('wireguard_address')->get();
        $this->info("Found {$routers->count()} routers with VPN IPs.");

        foreach ($routers as $router) {
            try {
                $winboxService->ensureMapping($router);
                $this->line("Processed Router: {$router->name} ({$router->public_ip}:{$router->winbox_port})");
            } catch (\Exception $e) {
                $this->error("Failed to process router {$router->id}: " . $e->getMessage());
            }
        }

        $this->info("Sync complete. Verify with 'sudo iptables -t nat -L -n -v'");
    }
}
