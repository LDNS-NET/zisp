<?php

namespace App\Console\Commands;

use App\Jobs\SyncGenieACSDevicesJob;
use Illuminate\Console\Command;

class SyncTR069Devices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tr069:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize TR-069 devices from GenieACS for all tenants and global discovery';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting TR-069 Synchronization...');
        
        SyncGenieACSDevicesJob::dispatchSync();
        
        $this->info('Synchronization complete.');
        return Command::SUCCESS;
    }
}
