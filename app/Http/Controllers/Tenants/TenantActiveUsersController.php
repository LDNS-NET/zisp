<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Radius\Radacct;
use App\Services\Mikrotik\RouterApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TenantActiveUsersController extends Controller
{
    public function index(Request $request)
    {
        $activeUsers = [];
        $maxUsers = (int) ($request->input('limit', 500));
        $routers = TenantMikrotik::all();

        // Collect all valid IPs for this tenant's routers (prefer wireguard, fallback to ip_address)
        $routerIps = $routers->pluck('wireguard_address')
            ->merge($routers->pluck('ip_address'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $routers = $routers->keyBy('wireguard_address');

        // Fetch active sessions from RADIUS radacct where acctstoptime IS NULL AND belongs to tenant routers
        $radRows = Radacct::whereNull('acctstoptime')
            ->whereIn('nasipaddress', $routerIps)
            ->where('acctupdatetime', '>', now()->subMinutes(10)) // Ignore stale sessions
            ->limit($maxUsers * 2)
            ->get();

        // Get all network users with their packages for type lookup - standardize to lowercase for lookup
        $networkUsers = NetworkUser::with('package')->get()->keyBy(function ($item) {
            return strtolower($item->username);
        });

        foreach ($radRows as $row) {
            $router = $routers[$row->nasipaddress] ?? null;
            $routerName = $router?->name ?? ($row->nasipaddress);

            // Get user type from NetworkUser's package
            // Lookup using lowercase username
            $networkUser = $networkUsers[strtolower($row->username)] ?? null;
            
            if ($networkUser) {
                // If we found the user, use their type directly
                $type = $networkUser->type ?? ($networkUser->package?->type ?? 'unknown');
            } else {
                // If user not found in DB but is in RADIUS (orphan session?), try to guess
                // Check if username looks like a MAC address (often Hotspot trial/login by MAC)
                $isMacUser = preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $row->username);
                
                if ($isMacUser) {
                    $type = 'hotspot';
                } else {
                    // Start with 'pppoe' as default for named users if not found, 
                    // or check framedprotocol/nasporttype if available (but they aren't in this query)
                    // For now, default to 'pppoe' for non-MAC usernames as it's the more common "account" type
                    $type = 'pppoe';
                }
            }

            $activeUsers[] = [
                'username' => $row->username,
                'user_type' => $type,
                'ip' => $row->framedipaddress,
                'mac' => $row->callingstationid ?? 'N/A',
                'session_start' => $row->acctstarttime,
                'session_end' => null,
                'package_name' => $row->groupname ?? 'N/A',
                'router_name' => $routerName,
            ];
        }

        // Fetch Static/DHCP Users from Online Routers via API
        $onlineRouters = TenantMikrotik::where('status', 'online')->get();
        foreach ($onlineRouters as $router) {
            try {
                // Only fetch if we haven't hit the limit (soft check)
                if (count($activeUsers) >= $maxUsers)
                    break;

                $apiService = new RouterApiService($router);
                $dhcpLeases = $apiService->getDhcpLeases();

                foreach ($dhcpLeases as $lease) {
                    $activeUsers[] = [
                        'username' => $lease['comment'] ?? $lease['host-name'] ?? 'Unknown',
                        'user_type' => 'static',
                        'ip' => $lease['address'] ?? 'N/A',
                        'mac' => $lease['mac-address'] ?? 'N/A',
                        'session_start' => $lease['last-seen'] ?? 'N/A',
                        'session_end' => null,
                        'package_name' => 'N/A',
                        'router_name' => $router->name,
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch DHCP leases for active users', [
                    'router_id' => $router->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // If too many users, cap to maxUsers to avoid memory issues
        $totalFetched = count($activeUsers);
        if ($totalFetched > $maxUsers) {
            $activeUsers = array_slice($activeUsers, 0, $maxUsers);
            $message = "Showing first {$maxUsers} out of {$totalFetched} active users. Refine filters to see more.";
        } else {
            $message = null;
        }

        return Inertia::render('Activeusers/Index', [
            'activeUsers' => $activeUsers,
            'message' => $message,
        ]);
    }
}
