<?php

namespace App\Console\Commands;

use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikScriptGenerator;
use Illuminate\Console\Command;

class RegenerateMikrotikScripts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:regenerate-scripts {--device-id= : Regenerate script for specific device} {--all : Regenerate scripts for all devices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate Mikrotik onboarding scripts with updated format';

    /**
     * Execute the console command.
     */
    public function handle(MikrotikScriptGenerator $scriptGenerator)
    {
        $all = $this->option('all');
        $deviceId = $this->option('device-id');
        $systemUrl = config('app.url');

        if ($all) {
            $devices = TenantMikrotik::all();
            $this->info("Regenerating scripts for " . count($devices) . " devices...\n");

            $bar = $this->output->createProgressBar(count($devices));
            $bar->start();

            foreach ($devices as $device) {
                $scriptGenerator->storeScript($device, $systemUrl);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✓ All scripts regenerated successfully!");
            return Command::SUCCESS;
        }

        if ($deviceId) {
            $device = TenantMikrotik::find($deviceId);

            if (!$device) {
                $this->error("Device with ID {$deviceId} not found.");
                return Command::FAILURE;
            }

            $scriptGenerator->storeScript($device, $systemUrl);
            $this->info("✓ Script regenerated for device: {$device->name} (ID: {$device->id})");
            $this->line("Sync Token: " . $device->sync_token);
            $this->line("Download URL: " . route('mikrotiks.download-script', $device->id));
            return Command::SUCCESS;
        }

        $this->error('Please specify either --device-id or --all');
        return Command::FAILURE;
    }
}
