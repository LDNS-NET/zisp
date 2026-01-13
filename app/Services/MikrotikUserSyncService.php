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
     * Only updates records that changed since last sync (optimized for large deployments)
     * 
     * @param TenantMikrotik $router
     * @return array ['synced' => count, 'online' => usernames, 'offline' => usernames]
     */
    public function syncActiveUsers(TenantMikrotik $router)
    {
        $activeUsernames = $this->fetchActiveUsernamesFromMikrotik($router);
        $tenantId = $router->tenant_id;
        
        // Get previously known online users ON THIS SPECIFIC ROUTER
        $previouslyOnlineOnRouter = TenantActiveUsers::where('tenant_id', $tenantId)
            ->where('router_id', $router->id)
            ->where('status', 'active')
            ->pluck('username')
            ->map(fn($u) => strtolower(trim($u)))
            ->toArray();

        $currentOnline = array_map(fn($u) => strtolower(trim($u)), $activeUsernames);

        // Find changes relative to this router
        $usersToMarkOnline = array_diff($currentOnline, $previouslyOnlineOnRouter);
        $usersToMarkOffline = array_diff($previouslyOnlineOnRouter, $currentOnline);

        // Only update changed records
        $updated = 0;
        
        if (!empty($usersToMarkOnline)) {
            // Mark online in NetworkUser (if not already)
            $count = NetworkUser::where('tenant_id', $tenantId)
                ->whereIn(\DB::raw('lower(trim(username))'), $usersToMarkOnline)
                ->where('online', false)
                ->update(['online' => true]);
            $updated += $count;

            // Update session records for this router
            foreach ($usersToMarkOnline as $username) {
                TenantActiveUsers::updateOrCreate(
                    ['tenant_id' => $tenantId, 'username' => $username, 'router_id' => $router->id],
                    ['status' => 'active', 'last_seen_at' => now()]
                );
            }
        }

        if (!empty($usersToMarkOffline)) {
            // Deactivate session records for this router
            TenantActiveUsers::where('tenant_id', $tenantId)
                ->where('router_id', $router->id)
                ->whereIn(\DB::raw('lower(trim(username))'), $usersToMarkOffline)
                ->update(['status' => 'deactivated', 'last_seen_at' => now()]);

            // For each user being marked offline on this router, 
            // check if they are still active on ANY other router before marking them offline globally
            foreach ($usersToMarkOffline as $username) {
                $stillActiveElsewhere = TenantActiveUsers::where('tenant_id', $tenantId)
                    ->where('username', $username)
                    ->where('status', 'active')
                    ->exists();
                
                if (!$stillActiveElsewhere) {
                    NetworkUser::where('tenant_id', $tenantId)
                        ->where(\DB::raw('lower(trim(username))'), $username)
                        ->where('online', true)
                        ->update(['online' => false]);
                    $updated++;
                }
            }
        }

        Log::info("Mikrotik sync optimized for router {$router->id}: {$updated} changes", [
            'online_count' => count($currentOnline),
            'marked_online' => count($usersToMarkOnline),
            'marked_offline' => count($usersToMarkOffline),
        ]);

        return [
            'synced' => $updated,
            'online' => array_values($usersToMarkOnline),
            'offline' => array_values($usersToMarkOffline),
        ];
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
