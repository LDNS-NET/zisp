<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ActivateWireGuardChanges implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 60;

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return 'activate_wireguard';
    }

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('wireguard');
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::channel('wireguard')->info('ActivateWireGuardChanges job started');

        try {
            // Run the sync command which now handles:
            // 1. Batch processing of pending peers (if any)
            // 2. Single interface reload
            // 3. Status updates
            // We use --all to ensure we catch everything and verify state

            Log::channel('wireguard')->info('Running wireguard:sync-peers --all');

            $exitCode = Artisan::call('wireguard:sync-peers', [
                '--all' => true,
            ]);

            Log::channel('wireguard')->info('Sync command finished', ['exit_code' => $exitCode]);

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('ActivateWireGuardChanges failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
