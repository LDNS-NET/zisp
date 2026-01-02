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
        $this->info('Checking for pending package upgrades...');

        $users = NetworkUser::withoutGlobalScopes()
            ->where(function($q) {
                $q->whereNotNull('pending_package_id')
                  ->orWhereNotNull('pending_hotspot_package_id');
            })
            ->where('pending_package_activation_at', '<=', now())
            ->get();

        if ($users->isEmpty()) {
            $this->info('No pending upgrades to process.');
            return;
        }

        foreach ($users as $user) {
            try {
                Log::info('Processing pending upgrade for user', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'pending_package_id' => $user->pending_package_id,
                    'pending_hotspot_package_id' => $user->pending_hotspot_package_id
                ]);

                if ($user->pending_package_id) {
                    $user->package_id = $user->pending_package_id;
                }
                
                if ($user->pending_hotspot_package_id) {
                    $user->hotspot_package_id = $user->pending_hotspot_package_id;
                }

                // Clear pending fields
                $user->pending_package_id = null;
                $user->pending_hotspot_package_id = null;
                $user->pending_package_activation_at = null;
                
                $user->save();

                $this->info("Successfully upgraded user: {$user->username}");
            } catch (\Exception $e) {
                Log::error('Failed to process pending upgrade', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                $this->error("Failed to upgrade user: {$user->username}");
            }
        }

        $this->info('Finished processing pending upgrades.');
    }
}
