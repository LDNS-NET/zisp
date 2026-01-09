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
     * Fetch active users from Mikrotik and sync only changed users
     * Only updates DB for users whose status actually changed (login/logout)
     * @param TenantMikrotik $router
     * @return array ['online' => [...], 'offline' => [...]]
     */
    public function syncActiveUsers(TenantMikrotik $router)
    {
        $tenantId = $router->tenant_id;
        $activeUsernames = $this->fetchActiveUsernamesFromMikrotik($router);
        
        if ($activeUsernames === false) {
            // Router unreachable, skip this router
            Log::warning("Router unreachable, skipping sync", ['router_id' => $router->id]);
            return ['online' => [], 'offline' => []];
        }

        // Get current online users for this tenant from DB
        $currentlyOnline = NetworkUser::where('tenant_id', $tenantId)
            ->where('online', true)
            ->pluck('username')
            ->map(fn($u) => strtolower(trim($u)))
            ->toArray();

        $activeUsernamesLower = array_map(fn($u) => strtolower(trim($u)), $activeUsernames);

        // Determine who went online and who went offline
        $newlyOnline = array_diff($activeUsernamesLower, $currentlyOnline);
        $newlyOffline = array_diff($currentlyOnline, $activeUsernamesLower);

        // Only update users whose status changed
        if (!empty($newlyOnline)) {
            foreach ($newlyOnline as $username) {
                TenantActiveUsers::updateOrCreate(
                    ['tenant_id' => $tenantId, 'username' => $username],
                    ['router_id' => $router->id, 'status' => 'active', 'last_seen_at' => now()]
                );
                NetworkUser::where('tenant_id', $tenantId)
                    ->where(fn($q) => $q->whereRaw('lower(trim(username)) = ?', [$username]))
                    ->update(['online' => true]);
            }
            Log::info("Mikrotik sync: Users went online", ['router_id' => $router->id, 'count' => count($newlyOnline)]);
        }

        if (!empty($newlyOffline)) {
            foreach ($newlyOffline as $username) {
                TenantActiveUsers::where('tenant_id', $tenantId)
                    ->where(fn($q) => $q->whereRaw('lower(trim(username)) = ?', [$username]))
                    ->update(['status' => 'deactivated', 'last_seen_at' => now()]);
                NetworkUser::where('tenant_id', $tenantId)
                    ->where(fn($q) => $q->whereRaw('lower(trim(username)) = ?', [$username]))
                    ->update(['online' => false]);
            }
            Log::info("Mikrotik sync: Users went offline", ['router_id' => $router->id, 'count' => count($newlyOffline)]);
        }

        return ['online' => $newlyOnline, 'offline' => $newlyOffline];
    }

    /**
     * Fetch active user usernames from Mikrotik router in real time
     * Returns false if router is unreachable, empty array if no users
     */
    protected function fetchActiveUsernamesFromMikrotik(TenantMikrotik $router)
    {
        try {
            $apiService = new RouterApiService($router);
            
            // Check if router is reachable
            if (!$apiService->isOnline()) {
                return false;
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
            return false;
        }
    }
}
