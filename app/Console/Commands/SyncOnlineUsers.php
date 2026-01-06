<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantActiveSession;
use App\Models\Radius\Radacct;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncOnlineUsers extends Command
{
    protected $signature = 'app:sync-online-users';
    protected $description = 'Sync online users from MikroTik and RADIUS to local cache';

    public function handle()
    {
        $this->info('Starting online users sync...');
        $startTime = microtime(true);

        // 1. Get all routers
        $routers = TenantMikrotik::where('status', 'online')->get();
        $activeSessions = [];

        // 2. Fetch from MikroTik (Real-time source)
        foreach ($routers as $router) {
            try {
                $service = MikrotikService::forMikrotik($router);
                // Use getOnlineUsers which fetches Hotspot, PPPoE, and Static
                $onlineUsers = $service->getOnlineUsers();

                foreach ($onlineUsers as $user) {
                    // Key for uniqueness: router_id + username + ip
                    $key = $router->id . '_' . ($user['username'] ?? 'unknown') . '_' . ($user['ip'] ?? 'unknown');
                    
                    $activeSessions[$key] = [
                        'router_id' => $router->id,
                        'tenant_id' => $router->tenant_id,
                        'username' => $user['username'],
                        'ip_address' => $user['ip'],
                        'mac_address' => $user['mac'],
                        'session_start' => $user['session_start'], // Might be uptime or login-by
                        'type' => $user['user_type'],
                        'source' => 'mikrotik',
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Failed to sync router {$router->id}: " . $e->getMessage());
                // Continue to next router, don't stop the whole process
            }
        }

        // 3. Fetch from RADIUS (Secondary source)
        // Only consider sessions that are NOT closed (acctstoptime IS NULL)
        // AND have been updated recently (e.g. last 10 mins) to avoid stale sessions
        $radiusSessions = Radacct::whereNull('acctstoptime')
            ->where('acctupdatetime', '>', now()->subMinutes(10))
            ->get();

        // Map RADIUS sessions to routers based on NAS IP
        // We need a map of Router IP -> [Router ID, Tenant ID]
        $routerMap = [];
        $allRouters = TenantMikrotik::all(); // Get all, even offline ones, for RADIUS mapping
        foreach ($allRouters as $r) {
            $data = ['id' => $r->id, 'tenant_id' => $r->tenant_id];
            if ($r->wireguard_address) $routerMap[$r->wireguard_address] = $data;
            if ($r->ip_address) $routerMap[$r->ip_address] = $data;
        }

        foreach ($radiusSessions as $session) {
            $routerData = $routerMap[$session->nasipaddress] ?? null;
            if (!$routerData) continue; // Unknown router
            
            $routerId = $routerData['id'];
            $tenantId = $routerData['tenant_id'];

            $key = $routerId . '_' . $session->username . '_' . $session->framedipaddress;

            // If already found in MikroTik, just update/enrich if needed
            // If not found, add it (RADIUS is secondary source)
            if (!isset($activeSessions[$key])) {
                $activeSessions[$key] = [
                    'router_id' => $routerId,
                    'tenant_id' => $tenantId,
                    'username' => $session->username,
                    'ip_address' => $session->framedipaddress,
                    'mac_address' => $session->callingstationid,
                    'session_start' => $session->acctstarttime,
                    'type' => 'radius', // Generic type, could infer from other fields
                    'source' => 'radius',
                    'session_id' => $session->acctsessionid,
                ];
            } else {
                // Enrich MikroTik data with RADIUS session ID if matching
                $activeSessions[$key]['session_id'] = $session->acctsessionid;
            }
        }

        // 4. Sync to Local Database
        // We need to resolve User IDs
        $usernames = array_filter(array_column($activeSessions, 'username'));
        $users = NetworkUser::whereIn('username', $usernames)->pluck('id', 'username')->toArray();
        // Also handle case-insensitive or fuzzy matching if needed, but strict for now

        $currentSessionIds = [];

        foreach ($activeSessions as $sessionData) {
            $userId = $users[$sessionData['username']] ?? null;
            
            // Generate a unique session ID for our table if not provided by RADIUS
            // We use a composite key of router_id + username + ip for the "session" concept in our DB
            $uniqueSessionKey = $sessionData['session_id'] ?? ('local_' . md5($sessionData['router_id'] . $sessionData['username'] . $sessionData['ip_address']));

            $currentSessionIds[] = $uniqueSessionKey;

            TenantActiveSession::updateOrCreate(
                [
                    'session_id' => $uniqueSessionKey,
                ],
                [
                    'router_id' => $sessionData['router_id'],
                    'tenant_id' => $sessionData['tenant_id'] ?? null,
                    'user_id' => $userId,
                    'ip_address' => $sessionData['ip_address'] ?? '0.0.0.0',
                    'mac_address' => $sessionData['mac_address'] ?? '',
                    'status' => 'active',
                    'last_seen_at' => now(),
                    // 'connected_at' => ... parse session_start if possible
                ]
            );
        }

        // 5. Cleanup: Mark sessions not in our list as disconnected
        // We only touch 'active' sessions. If they are not in the current list, they are gone.
        TenantActiveSession::where('status', 'active')
            ->whereNotIn('session_id', $currentSessionIds)
            ->update(['status' => 'disconnected', 'last_seen_at' => now()]);

        // 6. Sync NetworkUser 'online' status
        // Set all users to offline first (or just those that were active?)
        // Better: Set online=true for users in activeSessions, online=false for others.
        // To be efficient:
        // Get IDs of currently active users
        $activeUserIds = array_filter(array_unique(array_values($users))); // $users is username -> id map
        
        // But we need the IDs from the active sessions we just processed
        $activeUserIds = TenantActiveSession::where('status', 'active')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->toArray();

        if (!empty($activeUserIds)) {
            NetworkUser::whereIn('id', $activeUserIds)->update(['online' => true]);
            NetworkUser::whereNotIn('id', $activeUserIds)->update(['online' => false]);
        } else {
            NetworkUser::query()->update(['online' => false]);
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Sync complete in {$duration}s. Active users: " . count($activeSessions));
    }
}
