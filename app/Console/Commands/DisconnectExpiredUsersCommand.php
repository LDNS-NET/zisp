<?php

namespace App\Console\Commands;

use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Radius\Radacct;
use App\Services\Mikrotik\RouterApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DisconnectExpiredUsersCommand extends Command
{
    protected $signature = 'users:disconnect-expired';

    protected $description = 'Disconnect users who are currently active but have expired';

    public function handle()
    {
        // Only check users that expired in last 5 minutes to avoid checking all expired users
        // This is more efficient: if they haven't been disconnected in 5 mins, they likely won't be
        $recentlyExpired = NetworkUser::withoutGlobalScopes()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('expires_at', '>=', now()->subMinutes(5))
            ->where('online', true) // Only check users still marked online
            ->get();

        if ($recentlyExpired->isEmpty()) {
            $this->info('No recently expired online users found.');
            return 0;
        }

        $this->info("Found {$recentlyExpired->count()} recently expired users to disconnect.");

        $disconnectedCount = 0;
        $errorCount = 0;

        foreach ($recentlyExpired as $user) {
            try {
                // Find active sessions for this user
                $activeSessions = \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->with('router')
                    ->get();

                if ($activeSessions->isEmpty()) {
                    // User marked online but no active session - just mark offline
                    $user->update(['online' => false]);
                    continue;
                }

                $this->info("Disconnecting {$user->username} ({$activeSessions->count()} sessions)");

                foreach ($activeSessions as $session) {
                    if (!$session->router) {
                        Log::warning("Router not found for session", ['session_id' => $session->session_id]);
                        continue;
                    }

                    $apiService = new RouterApiService($session->router);
                    $userType = $user->package->type ?? $user->type ?? 'pppoe';

                    if ($apiService->disconnectUser($user->username, $userType)) {
                        $session->update(['status' => 'disconnected', 'disconnected_at' => now()]);
                        $disconnectedCount++;
                    } else {
                        $errorCount++;
                    }
                }

                $user->update(['online' => false]);
                Log::info("Expired user disconnected: {$user->username}");

            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Error disconnecting expired user: {$user->username}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Disconnected: $disconnectedCount, Errors: $errorCount");
        return 0;
    }
}
