<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SuspendNoPackageUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:suspend-no-package';

    protected $description = 'Suspend users who were created more than 10 minutes ago and have no package assigned';

    public function handle()
    {
        $cutoffTime = now()->subMinutes(10);

        $usersToSuspend = \App\Models\Tenants\NetworkUser::withoutGlobalScopes()
            ->whereNull('package_id')
            ->whereNull('hotspot_package_id')
            ->where('created_at', '<=', $cutoffTime)
            ->where('status', '!=', 'suspended')
            ->get();

        if ($usersToSuspend->isEmpty()) {
            $this->info('No users found to suspend.');
            return 0;
        }

        $this->info("Found {$usersToSuspend->count()} users to suspend.");

        foreach ($usersToSuspend as $user) {
            try {
                $user->update(['status' => 'suspended']);
                
                // Disconnect if online
                if ($user->online) {
                    $activeSessions = \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->with('router')
                        ->get();

                    foreach ($activeSessions as $session) {
                        if ($session->router) {
                            $apiService = new \App\Services\Mikrotik\RouterApiService($session->router);
                            $userType = $user->type ?? 'pppoe';
                            $apiService->disconnectUser($user->username, $userType);
                            $session->update(['status' => 'disconnected', 'disconnected_at' => now()]);
                        }
                    }
                    $user->update(['online' => false]);
                }
                
                Log::info("User suspended (No package for 10mins): {$user->username}");
                $this->info("Suspended: {$user->username}");

            } catch (\Exception $e) {
                Log::error("Error suspending user {$user->username}: " . $e->getMessage());
                $this->error("Error: {$user->username}");
            }
        }

        return 0;
    }
}
