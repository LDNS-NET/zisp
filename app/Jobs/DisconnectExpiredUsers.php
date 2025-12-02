<?php

namespace App\Jobs;

use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Radius\Radacct;
use App\Services\Mikrotik\RouterApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DisconnectExpiredUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get all active sessions from RADIUS (acctstoptime is NULL)
        $activeSessions = Radacct::whereNull('acctstoptime')->get();

        foreach ($activeSessions as $session) {
            // Find the network user
            $user = NetworkUser::where('username', $session->username)->first();

            // Skip if user not found or not expired
            // If expires_at is NULL, user never expires
            if (!$user || !$user->expires_at || $user->expires_at->isFuture()) {
                continue;
            }

            // User is expired and still connected - disconnect them
            // Find the router by the NAS IP address (which is the WireGuard IP)
            $router = TenantMikrotik::where('wireguard_address', $session->nasipaddress)->first();

            if ($router) {
                Log::info("Disconnecting expired user: {$user->username}", [
                    'expiration' => $user->expires_at,
                    'router' => $router->name,
                    'nas_ip' => $session->nasipaddress
                ]);

                $apiService = new RouterApiService($router);
                $apiService->disconnectUser($session->username, $user->type ?? 'pppoe');
            } else {
                Log::warning("Could not find router for expired user session", [
                    'username' => $session->username,
                    'nas_ip' => $session->nasipaddress
                ]);
            }
        }
    }
}
