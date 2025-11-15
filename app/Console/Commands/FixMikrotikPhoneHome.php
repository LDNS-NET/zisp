<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikConnectionService;
use App\Services\MikrotikScriptGenerator;
use RouterOS\Query;

class FixMikrotikPhoneHome extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:fix-phone-home {--device-id= : Fix specific device} {--all : Fix all devices} {--check-only : Only check, do not fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and fix phone-home scheduler on Mikrotik devices';

    /**
     * Execute the console command.
     */
    public function handle(
        MikrotikConnectionService $connectionService,
        MikrotikScriptGenerator $scriptGenerator
    ) {
        $deviceId = $this->option('device-id');
        $all = $this->option('all');
        $checkOnly = $this->option('check-only');

        if (!$deviceId && !$all) {
            $this->error('Please specify --device-id or --all');
            return 1;
        }

        $devices = $all 
            ? TenantMikrotik::whereNotNull('ip_address')
                ->whereNotNull('api_username')
                ->get()
            : [TenantMikrotik::findOrFail($deviceId)];

        $this->info("Checking phone-home scheduler on " . count($devices) . " device(s)...\n");

        $bar = $this->output->createProgressBar(count($devices));
        $bar->start();

        $fixed = 0;
        $failed = 0;
        $notConnected = 0;

        foreach ($devices as $device) {
            try {
                $client = $connectionService->connect($device);
                
                if (!$client) {
                    $notConnected++;
                    $bar->advance();
                    continue;
                }

                // Check if scheduler exists
                $query = new Query('/system/scheduler/print');
                $query->where('name', 'zisp-phone-home');
                $schedulers = $client->query($query)->read();

                // Check if script exists
                $query = new Query('/system/script/print');
                $query->where('name', 'zisp-phone-home');
                $scripts = $client->query($query)->read();

                $hasScheduler = !empty($schedulers);
                $hasScript = !empty($scripts);
                $schedulerEnabled = $hasScheduler && ($schedulers[0]['disabled'] ?? 'true') === 'false';

                if ($hasScheduler && $hasScript && $schedulerEnabled) {
                    $this->newLine();
                    $this->info("✓ Device {$device->name} (ID: {$device->id}): Phone-home is working");
                } else {
                    $this->newLine();
                    $this->warn("⚠ Device {$device->name} (ID: {$device->id}): Phone-home needs fixing");
                    $this->line("  - Script exists: " . ($hasScript ? 'Yes' : 'No'));
                    $this->line("  - Scheduler exists: " . ($hasScheduler ? 'Yes' : 'No'));
                    $this->line("  - Scheduler enabled: " . ($schedulerEnabled ? 'Yes' : 'No'));

                    if (!$checkOnly) {
                        // Regenerate script and update scheduler
                        $systemUrl = config('app.url');
                        $scriptGenerator->storeScript($device, $systemUrl);
                        
                        // Re-run the phone-home section via API
                        $this->fixPhoneHomeViaAPI($client, $device);
                        $fixed++;
                        $this->info("  ✓ Fixed phone-home for device {$device->name}");
                    }
                }

            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("✗ Device {$device->name} (ID: {$device->id}): " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Summary:");
        $this->line("  Total devices: " . count($devices));
        $this->line("  Fixed: {$fixed}");
        $this->line("  Failed: {$failed}");
        $this->line("  Not connected: {$notConnected}");

        if ($checkOnly && ($fixed > 0 || $failed > 0)) {
            $this->newLine();
            $this->info("Run without --check-only to fix issues");
        }

        return 0;
    }

    /**
     * Fix phone-home via API by executing the script section
     */
    protected function fixPhoneHomeViaAPI($client, TenantMikrotik $device)
    {
        try {
            $systemUrl = rtrim(config('app.url'), '/');
            $syncUrl = $systemUrl . '/mikrotiks/' . $device->id . '/sync?token=' . $device->sync_token;

            // Remove existing scheduler
            try {
                $query = new Query('/system/scheduler/print');
                $query->where('name', 'zisp-phone-home');
                $existing = $client->query($query)->read();
                if (!empty($existing)) {
                    $query = new Query('/system/scheduler/remove');
                    $query->equal('.id', $existing[0]['.id']);
                    $client->query($query)->read();
                }
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }

            // Remove existing script
            try {
                $query = new Query('/system/script/print');
                $query->where('name', 'zisp-phone-home');
                $existing = $client->query($query)->read();
                if (!empty($existing)) {
                    $query = new Query('/system/script/remove');
                    $query->equal('.id', $existing[0]['.id']);
                    $client->query($query)->read();
                }
            } catch (\Exception $e) {
                // Ignore if doesn't exist
            }

            // Create script
            $scriptSource = ':local syncUrl "' . $syncUrl . '"; :local routerIp ""; :do { :foreach id in=[/ip address find] do={ :local ipAddr [/ip address get $id address]; :local slash [:find $ipAddr "/"]; :local ipOnly $ipAddr; :if ($slash != -1) do={ :set ipOnly [:pick $ipAddr 0 $slash] }; :if ([:pick $ipOnly 0 3] != "127" && [:len $routerIp] = 0) do={ :set routerIp $ipOnly }; }; } on-error={ :put "Error getting IP addresses" }; :if ([:len $routerIp] > 0) do={ :do { :local postData ("ip_address=" . $routerIp); /tool fetch url=$syncUrl http-method=post http-data=$postData keep-result=no; :put ("Phone-home successful: " . $routerIp); } on-error={ :put "Phone-home failed: could not reach server" }; } else={ :put "Phone-home skipped: no router IP found" };';

            $query = new Query('/system/script/add');
            $query->equal('name', 'zisp-phone-home');
            $query->equal('policy', 'ftp,reboot,read,write,policy,test,password,sniff,sensitive,api');
            $query->equal('source', $scriptSource);
            $client->query($query)->read();

            // Create scheduler
            $query = new Query('/system/scheduler/add');
            $query->equal('name', 'zisp-phone-home');
            $query->equal('start-time', 'start');
            $query->equal('interval', '3m');
            $query->equal('on-event', '/system script run zisp-phone-home');
            $query->equal('comment', 'ZiSP periodic phone-home');
            $client->query($query)->read();

            // Test immediately
            $query = new Query('/system/script/run');
            $query->equal('script', 'zisp-phone-home');
            $client->query($query)->read();

        } catch (\Exception $e) {
            \Log::error("Failed to fix phone-home via API for device {$device->id}: " . $e->getMessage());
            throw $e;
        }
    }
}

