<?php

namespace App\Console\Commands;

use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncMikrotikApiUser extends Command
{
    protected $signature = 'mikrotik:sync-api-user {router_id}';
    protected $description = 'Sync API user credentials for a MikroTik router';

    public function handle()
    {
        $routerId = $this->argument('router_id');
        $router = TenantMikrotik::find($routerId);

        if (!$router) {
            $this->error("Router with ID {$routerId} not found");
            return 1;
        }

        $this->info("Router: {$router->name}");
        $this->info("VPN IP: {$router->wireguard_address}");
        $this->info("Current API Username: {$router->api_username}");

        // Generate new password
        $newPassword = Str::random(rand(18, 24));

        $this->info("\nConnecting to router using admin credentials...");

        try {
            // Connect using admin credentials
            $service = new MikrotikService();
            $service->setConnection(
                $router->wireguard_address,
                $router->router_username,
                $router->router_password,
                $router->api_port ?? 8728
            );

            // Update zisp_user on the router
            $this->info("Updating zisp_user on router...");
            $success = $service->createSystemUser('zisp_user', $newPassword, 'full');

            if (!$success) {
                $this->error("Failed to update user on router");
                return 1;
            }

            // Update database
            $router->api_username = 'zisp_user';
            $router->api_password = $newPassword;
            $router->save();

            $this->info("\n✓ Successfully synced API user credentials!");
            $this->info("API Username: zisp_user");
            $this->info("API Password: {$newPassword}");

            // Test the new credentials
            $this->info("\nTesting new credentials...");
            $testService = MikrotikService::forMikrotik($router->fresh());
            $result = $testService->testConnection();

            if ($result !== false) {
                $this->info("✓ Connection test successful!");
            } else {
                $this->warn("⚠ Connection test failed - please verify manually");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
