<?php

namespace App\Console\Commands;

use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestPublicIpConnectivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:test-public-ip {--router-id= : Test specific router ID} {--all : Test all routers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test router connectivity using public IP addresses';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing MikroTik public IP connectivity...');
        
        if ($this->option('router-id')) {
            $routers = TenantMikrotik::where('id', $this->option('router-id'))->get();
        } elseif ($this->option('all')) {
            $routers = TenantMikrotik::all();
        } else {
            $this->error('Please specify either --router-id=X or --all');
            return 1;
        }

        foreach ($routers as $router) {
            $this->testRouter($router);
        }

        $this->info('Public IP connectivity testing completed!');
        return 0;
    }

    private function testRouter(TenantMikrotik $router)
    {
        $this->line("\n--- Testing Router: {$router->name} (ID: {$router->id}) ---");
        
        // Show IP configuration
        $this->line("Private IP: " . ($router->ip_address ?: 'Not set'));
        $this->line("Public IP: " . ($router->public_ip_address ?: 'Not set'));
        $this->line("Preferred IP: " . $router->getPreferredIpAddress());
        $this->line("Has Public IP: " . ($router->hasPublicIp() ? 'Yes' : 'No'));

        try {
            $service = MikrotikService::forMikrotik($router);
            $resources = $service->testConnection();
            
            if ($resources !== false) {
                $this->info("✅ Connection successful using: " . $router->getPreferredIpAddress());
                $this->line("Router resources: " . count($resources) . " items retrieved");
                
                // Show some router info if available
                if (isset($resources[0])) {
                    $resource = $resources[0];
                    $this->line("Board name: " . ($resource['board-name'] ?? 'Unknown'));
                    $this->line("Version: " . ($resource['version'] ?? 'Unknown'));
                }
            } else {
                $this->error("❌ Connection failed using: " . $router->getPreferredIpAddress());
            }
        } catch (\Exception $e) {
            $this->error("❌ Exception occurred: " . $e->getMessage());
            Log::error('Public IP connectivity test failed', [
                'router_id' => $router->id,
                'preferred_ip' => $router->getPreferredIpAddress(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
