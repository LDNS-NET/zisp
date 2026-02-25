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
        $perPage = (int) ($request->input('per_page', 50));
        
        // Fetch active users from local database (synced via background job or RADIUS)
        $query = \App\Models\Tenants\TenantActiveUsers::with(['user:id,account_number,full_name,username,type', 'user.package', 'router'])
            ->where('status', 'active')
            ->where('last_seen_at', '>', now()->subHours(24));

        // Search filtering
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('mac_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%");
                  });
            });
        }

        // Optional: Filter by router if needed
        if ($request->has('router_id')) {
            $query->where('router_id', $request->input('router_id'));
        }

        $query->orderBy('last_seen_at', 'desc');

        // Use pagination instead of limit
        $activeUsersData = $query->paginate($perPage)->withQueryString();

        // Calculate total stats across all pages
        $stats = [
            'all' => (clone $query)->count(),
            'hotspot' => (clone $query)->where(function($q) {
                $q->whereHas('user', fn($sq) => $sq->where('type', 'hotspot'))
                  ->orWhere(function($sq) {
                      $sq->whereNull('user_id')
                         ->where('username', 'REGEXP', '^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$');
                  });
            })->count(),
            'pppoe' => (clone $query)->where(function($q) {
                $q->whereHas('user', fn($sq) => $sq->where('type', 'pppoe'))
                  ->orWhere(function($sq) {
                      $sq->whereNull('user_id')
                         ->where('username', 'NOT REGEXP', '^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$');
                  });
            })->count(),
            'static' => (clone $query)->whereHas('user', fn($sq) => $sq->where('type', 'static'))->count(),
        ];

        $activeUsers = $activeUsersData->getCollection()->map(function ($session) {
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
                'username' => $user?->username ?? $session->username ?? $session->ip_address, // Fallback for static
                'user_type' => $type,
                'ip' => $session->ip_address,
                'mac' => $session->mac_address,
                'session_start' => $session->connected_at ? $session->connected_at->toDateTimeString() : $session->created_at->toDateTimeString(),
                'session_end' => null,
                'package_name' => $user?->package?->name ?? 'N/A',
                'router_name' => $session->router->name ?? 'Unknown',
                'last_seen' => $session->last_seen_at->diffForHumans(),
            ];
        });

        // Replace the collection in the paginator
        $activeUsersData->setCollection($activeUsers);

        return Inertia::render('Activeusers/Index', [
            'activeUsers' => $activeUsersData,
            'stats' => $stats,
        ]);
    }
}
