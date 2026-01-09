<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\NetworkUser;
use Illuminate\Support\Facades\Log;

class ProcessPendingUpgrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'network:process-upgrades';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending package upgrades for network users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Only process upgrades activated in the last 10 minutes (skip old pending)
        $recentActivation = now()->subMinutes(10);

        $users = NetworkUser::withoutGlobalScopes()
            ->where(function($q) {
                $q->whereNotNull('pending_package_id')
                  ->orWhereNotNull('pending_hotspot_package_id');
            })
            ->where('pending_package_activation_at', '<=', now())
            ->where('pending_package_activation_at', '>=', $recentActivation)
            ->get();

        if ($users->isEmpty()) {
            $this->info('No pending upgrades to process.');
            return 0;
        }

        $processed = 0;
        foreach ($users as $user) {
            try {
                if ($user->pending_package_id) {
                    $user->package_id = $user->pending_package_id;
                }
                if ($user->pending_hotspot_package_id) {
                    $user->hotspot_package_id = $user->pending_hotspot_package_id;
                }

                $user->update([
                    'pending_package_id' => null,
                    'pending_hotspot_package_id' => null,
                    'pending_package_activation_at' => null,
                ]);

                $processed++;
                Log::info("Upgrade processed: {$user->username}");
            } catch (\Exception $e) {
                Log::error("Upgrade failed: {$user->username}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Processed $processed pending upgrades.");
        return 0;
    }
}
