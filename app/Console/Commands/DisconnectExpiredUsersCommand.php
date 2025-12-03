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
        $this->info('Checking for expired active users...');

        // Get all active sessions from RADIUS (acctstoptime is NULL)
        $activeSessions = Radacct::whereNull('acctstoptime')->get();

        if ($activeSessions->isEmpty()) {
            $this->info('No active sessions found.');
            return 0;
        }

        $this->info("Found {$activeSessions->count()} active sessions.");

        $disconnectedCount = 0;
        $errorCount = 0;

        foreach ($activeSessions as $session) {
            try {
                // Find the network user (within current tenant context)
                $user = NetworkUser::where('username', $session->username)->first();

                // Skip if user not found
                if (!$user) {
                    continue;
                }

                // Skip if user doesn't have an expiration date
                if (!$user->expires_at) {
                    continue;
                }

                // Skip if user hasn't expired yet
                if ($user->expires_at->isFuture()) {
                    continue;
                }

                // User is expired and still connected - disconnect them
                Log::info("Disconnecting expired user: {$user->username}", [
                    'user_id' => $user->id,
                    'expiration' => $user->expires_at,
                    'nas_ip' => $session->nasipaddress
                ]);

                // Find the router by the NAS IP address (which is the WireGuard IP)
                $router = TenantMikrotik::where('wireguard_address', $session->nasipaddress)->first();

                if (!$router) {
                    Log::warning("Could not find router for expired user session", [
                        'username' => $session->username,
                        'nas_ip' => $session->nasipaddress
                    ]);
                    $errorCount++;
                    continue;
                }

                // Disconnect the user
                $apiService = new RouterApiService($router);
                $userType = $user->package->type ?? $user->type ?? 'pppoe';

                if ($apiService->disconnectUser($session->username, $userType)) {
                    $disconnectedCount++;
                    $this->info("✓ Disconnected expired user: {$user->username}");
                } else {
                    $errorCount++;
                    $this->warn("✗ Failed to disconnect user: {$user->username}");
                }

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Error disconnecting expired user', [
                    'username' => $session->username ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                $this->error("✗ Error: {$e->getMessage()}");
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
