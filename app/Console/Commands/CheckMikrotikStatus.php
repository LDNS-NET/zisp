<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikConnectionService;

class CheckMikrotikStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mikrotik:check-status {--force : Force check all devices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check status of all Mikrotik devices and update their connectivity state';

    /**
     * Execute the console command.
     */
    public function handle(MikrotikConnectionService $connectionService)
    {
        $this->info('🔍 Checking Mikrotik device statuses...');

        $force = $this->option('force');
        
        // Get all devices with API credentials set
        $devices = TenantMikrotik::where('status', '!=', 'error')
            ->get();

        $total = $devices->count();
        $connected = 0;
        $disconnected = 0;
        $marked_stale = 0;

        if ($total === 0) {
            $this->info('No Mikrotik devices found to check.');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($devices as $device) {
            // Mark devices as stale if no activity for 4 minutes
            if ($device->last_seen_at && $device->last_seen_at->diffInMinutes(now()) > 4) {
                if ($device->status === 'connected') {
                    $device->markDisconnected();
                    $marked_stale++;
                    $disconnected++;
                }
            } else if ($device->last_seen_at && $device->last_seen_at->diffInMinutes(now()) <= 4) {
                if ($device->status !== 'connected') {
                    $device->markConnected();
                    $connected++;
                }
            }

            // If device has API credentials, try to connect
            if ($device->ip_address && $device->api_username && !$force) {
                if ($connectionService->testConnection($device)) {
                    $info = $connectionService->getDeviceInfo($device);
                    if ($info) {
                        $device->update([
                            'board_name' => $info['board_name'] ?? $device->board_name,
                            'system_version' => $info['system_version'] ?? $device->system_version,
                            'interface_count' => $info['interface_count'] ?? $device->interface_count,
                        ]);
                        $connected++;
                    }
                } else {
                    $disconnected++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('✅ Status check complete!');
        $this->info("📊 Summary:");
        $this->info("   Total devices: {$total}");
        $this->info("   Connected: {$connected}");
        $this->info("   Disconnected: {$disconnected}");
        if ($marked_stale > 0) {
            $this->info("   Marked stale (no activity): {$marked_stale}");
        }

        return 0;
    }
}
