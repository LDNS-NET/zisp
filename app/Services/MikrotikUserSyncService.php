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
        $activeSessions = $this->fetchActiveSessionsFromMikrotik($router);
        $tenantId = $router->tenant_id;
        
        // Extract usernames for the diff logic
        $activeUsernames = array_keys($activeSessions);

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

        // Update EXISTING online sessions (refresh MAC/IP even if already online)
        $usersStillOnline = array_intersect($currentOnline, $previouslyOnlineOnRouter);
        foreach ($usersStillOnline as $username) {
            $data = $activeSessions[$username];
            TenantActiveUsers::where('tenant_id', $tenantId)
                ->where('router_id', $router->id)
                ->where('username', $username)
                ->where('status', 'active')
                ->update([
                    'mac_address' => $data['mac_address'] ?? null,
                    'ip_address' => $data['ip_address'] ?? null,
                    'last_seen_at' => now()
                ]);
        }

        // Only update status changed records
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
                $data = $activeSessions[$username];
                TenantActiveUsers::updateOrCreate(
                    ['tenant_id' => $tenantId, 'username' => $username, 'router_id' => $router->id],
                    [
                        'status' => 'active', 
                        'mac_address' => $data['mac_address'] ?? null,
                        'ip_address' => $data['ip_address'] ?? null,
                        'last_seen_at' => now()
                    ]
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

        Log::info("Mikrotik sync optimized for router {$router->id}: {$updated} status changes", [
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
     * Fetch active session details from Mikrotik router in real time
     * Returns map: username => [mac_address, ip_address]
     */
    protected function fetchActiveSessionsFromMikrotik(TenantMikrotik $router)
    {
        try {
            $apiService = new RouterApiService($router);
            
            // Check if router is reachable
            if (!$apiService->isOnline()) {
                Log::warning("Mikrotik router offline", ['router_id' => $router->id]);
                return [];
            }

            $sessions = [];

            // Fetch active hotspot users
            $hotspotUsers = $apiService->getHotspotActiveUsers();
            foreach ($hotspotUsers as $user) {
                $username = $user['user'] ?? ($user['name'] ?? null);
                if ($username) {
                    $sessions[strtolower(trim($username))] = [
                        'mac_address' => $user['mac-address'] ?? null,
                        'ip_address' => $user['address'] ?? null,
                    ];
                }
            }

            // Fetch active PPPoE users
            $pppoeUsers = $apiService->getPppoeActiveUsers();
            foreach ($pppoeUsers as $user) {
                if (isset($user['name'])) {
                    $username = strtolower(trim($user['name']));
                    // Prefer existing hotspot session if present (dual login handling)
                    if (!isset($sessions[$username])) {
                        $sessions[$username] = [
                            'mac_address' => $user['caller-id'] ?? null,
                            'ip_address' => $user['address'] ?? null,
                        ];
                    }
                }
            }

            return $sessions;
        } catch (\Exception $e) {
            Log::error("Failed to fetch active sessions from Mikrotik", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
