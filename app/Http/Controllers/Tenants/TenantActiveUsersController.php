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
        $maxUsers = (int) ($request->input('limit', 500));
        
        // Fetch active users from local database (synced via background job or RADIUS)
        $query = \App\Models\Tenants\TenantActiveUsers::with(['user.package', 'router'])
            ->where('status', 'active')
            ->where('last_seen_at', '>', now()->subHours(24))
            ->orderBy('last_seen_at', 'desc');

        // Optional: Filter by router if needed
        if ($request->has('router_id')) {
            $query->where('router_id', $request->input('router_id'));
        }

        $activeUsersData = $query->limit($maxUsers)->get()->unique(function ($item) {
            return $item->username . $item->mac_address;
        });

        $activeUsers = $activeUsersData->map(function ($session) {
            $user = $session->user;
            // Determine type
            $type = 'unknown';
            if ($user) {
                $type = $user->type ?? ($user->package?->type ?? 'unknown');
            } elseif ($session->username) {
                 // Guess type if no user relation
                 $isMacUser = preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $session->username);
                 $type = $isMacUser ? 'hotspot' : 'pppoe';
            }

            return [
                'username' => $user->username ?? $session->username ?? $session->ip_address, // Fallback for static
                'user_type' => $type,
                'ip' => $session->ip_address,
                'mac' => $session->mac_address,
                'session_start' => $session->connected_at ? $session->connected_at->toDateTimeString() : $session->created_at->toDateTimeString(),
                'session_end' => null,
                'package_name' => $user?->package?->name ?? 'N/A',
                'router_name' => $session->router->name ?? 'Unknown',
                'last_seen' => $session->last_seen_at->diffForHumans(),
            ];
        })->values();

        return Inertia::render('Activeusers/Index', [
            'activeUsers' => $activeUsers,
            'message' => $activeUsersData->count() >= $maxUsers ? "Showing first {$maxUsers} active users." : null,
        ]);
    }
}
