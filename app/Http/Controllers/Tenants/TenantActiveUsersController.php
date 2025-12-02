<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use App\Services\Mikrotik\RouterApiService;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TenantActiveUsersController extends Controller
{
    public function index(Request $request)
    {
        $activeUsers = [];
        $maxUsers = (int) ($request->input('limit', 500));
        $routers = TenantMikrotik::where('status', 'online')->get();

        foreach ($routers as $router) {
            try {
                $apiService = new RouterApiService($router);

                // Fetch Hotspot Users
                $hotspotUsers = $apiService->getHotspotActiveUsers();
                foreach ($hotspotUsers as $user) {
                    $activeUsers[] = [
                        'username' => $user['user'] ?? 'Unknown',
                        'user_type' => 'hotspot',
                        'ip' => $user['address'] ?? 'N/A',
                        'mac' => $user['mac-address'] ?? 'N/A',
                        'session_start' => isset($user['uptime']) ? $user['uptime'] : 'N/A',
                        'session_end' => null,
                        'package_name' => 'N/A',
                        'router_name' => $router->name,
                    ];
                }

                // Fetch PPPoE Users
                $pppoeUsers = $apiService->getPppoeActiveUsers();
                foreach ($pppoeUsers as $user) {
                    $activeUsers[] = [
                        'username' => $user['name'] ?? 'Unknown',
                        'user_type' => 'pppoe',
                        'ip' => $user['address'] ?? 'N/A',
                        'mac' => $user['caller-id'] ?? 'N/A',
                        'session_start' => isset($user['uptime']) ? $user['uptime'] : 'N/A',
                        'session_end' => null,
                        'package_name' => $user['service'] ?? 'N/A',
                        'router_name' => $router->name,
                    ];
                }

            } catch (\Exception $e) {
                // Log error but continue with other routers
                Log::error('Failed to fetch active users from router', [
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
