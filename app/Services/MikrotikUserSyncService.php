<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantActiveUsers;
use App\Services\Mikrotik\RouterApiService;
use Illuminate\Support\Facades\Log;

class MikrotikUserSyncService
{
    /**
     * Fetch active users from Mikrotik and update tenant_active_users and network_users.online
     * @param TenantMikrotik $router
     * @return array
     */
    public function syncActiveUsers(TenantMikrotik $router)
    {
        $activeUsernames = $this->fetchActiveUsernamesFromMikrotik($router);
        $tenantId = $router->tenant_id;

        // Mark all users offline first
        NetworkUser::where('tenant_id', $tenantId)->update(['online' => false]);
        TenantActiveUsers::where('tenant_id', $tenantId)->update(['status' => 'deactivated']);

        $updated = [];
        foreach ($activeUsernames as $username) {
            // Update or create active session
            TenantActiveUsers::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'username' => $username,
                ],
                [
                    'router_id' => $router->id,
                    'status' => 'active',
                    'last_seen_at' => now(),
                ]
            );
            // Mark user online
            NetworkUser::where('tenant_id', $tenantId)
                ->where('username', $username)
                ->update(['online' => true]);
            $updated[] = $username;
        }
        Log::info("Mikrotik sync complete for tenant $tenantId. Online users: " . count($updated));
        return $updated;
    }

    /**
     * Fetch active user usernames from Mikrotik router in real time
     * Connects to router and fetches from hotspot and PPPoE active sessions
     */
    protected function fetchActiveUsernamesFromMikrotik(TenantMikrotik $router)
    {
        try {
            $apiService = new RouterApiService($router);
            
            // Check if router is reachable
            if (!$apiService->isOnline()) {
                Log::warning("Mikrotik router offline", ['router_id' => $router->id]);
                return [];
            }

            $usernames = [];

            // Fetch active hotspot users
            $hotspotUsers = $apiService->getHotspotActiveUsers();
            foreach ($hotspotUsers as $user) {
                if (isset($user['name'])) {
                    $usernames[] = $user['name'];
                }
            }

            // Fetch active PPPoE users
            $pppoeUsers = $apiService->getPppoeActiveUsers();
            foreach ($pppoeUsers as $user) {
                if (isset($user['name'])) {
                    $usernames[] = $user['name'];
                }
            }

            // Remove duplicates and return
            return array_unique($usernames);
        } catch (\Exception $e) {
            Log::error("Failed to fetch active users from Mikrotik", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
