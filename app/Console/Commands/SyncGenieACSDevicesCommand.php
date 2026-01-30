<?php

namespace App\Console\Commands;

use App\Jobs\SyncGenieACSDevicesJob;
use Illuminate\Console\Command;

class SyncGenieACSDevicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'genieacs:sync-devices {--force : Force sync even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all TR-069 devices from GenieACS to local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching GenieACS device sync job...');
        
        SyncGenieACSDevicesJob::dispatch();
        
        $this->info('✓ Sync job dispatched successfully!');
        $this->info('Devices will be synced in the background.');
        
        return Command::SUCCESS;
    }
}
