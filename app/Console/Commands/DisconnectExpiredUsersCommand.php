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
        $this->info('Checking for expired users who are still online...');

        // 1. Find all users who have expired but are still marked as online
        // Use withoutGlobalScopes to check across all tenants
        $expiredUsers = NetworkUser::withoutGlobalScopes()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('online', true)
            ->get();

        if ($expiredUsers->isEmpty()) {
            $this->info('No expired online users found.');
            return 0;
        }

        $this->info("Found {$expiredUsers->count()} expired users marked as online.");

        $disconnectedCount = 0;
        $errorCount = 0;

        foreach ($expiredUsers as $user) {
            try {
                // 2. Find their active session(s)
                $activeSessions = \App\Models\Tenants\TenantActiveSession::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->with('router')
                    ->get();

                if ($activeSessions->isEmpty()) {
                    // If no active session found but user is marked online, just sync the flag
                    $user->update(['online' => false]);
                    continue;
                }

                foreach ($activeSessions as $session) {
                    $router = $session->router;

                    if (!$router) {
                        Log::warning("Could not find router for expired user session", [
                            'username' => $user->username,
                            'session_id' => $session->session_id
                        ]);
                        continue;
                    }

                    // 3. Disconnect the user from the router
                    $apiService = new RouterApiService($router);
                    $userType = $user->package->type ?? $user->type ?? 'pppoe';

                    $this->info("Attempting to disconnect expired user: {$user->username} from router: {$router->name}");

                    if ($apiService->disconnectUser($user->username, $userType)) {
                        // Mark session as disconnected in our DB
                        $session->update([
                            'status' => 'disconnected',
                            'last_seen_at' => now()
                        ]);
                        
                        $disconnectedCount++;
                        $this->info("✓ Disconnected: {$user->username}");
                    } else {
                        $errorCount++;
                        $this->warn("✗ Failed to disconnect: {$user->username}");
                    }
                }

                // Final sync of the online flag
                $user->update(['online' => false]);

                Log::info("Expired user disconnected: {$user->username}", [
                    'user_id' => $user->id,
                    'expiration' => $user->expires_at
                ]);

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Error disconnecting expired user', [
                    'username' => $user->username,
                    'error' => $e->getMessage()
                ]);
                $this->error("✗ Error for {$user->username}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("=== Summary ===");
        $this->info("Disconnected: {$disconnectedCount}");
        if ($errorCount > 0) {
            $this->warn("Errors: {$errorCount}");
        }

        return 0;
    }
}
