<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantActiveUsersController extends Controller
{
    public function index(Request $request)
    {
        // Get all online users from the database
        $activeUsers = NetworkUser::where('online', true)
            ->with('package')
            ->get()
            ->map(function ($user) {
                return [
                    'username' => $user->username,
                    'user_type' => $user->type,
                    'ip' => $user->location ?? 'N/A', // Using location field as IP placeholder
                    'mac' => null, // MAC address not stored in network_users
                    'session_start' => $user->registered_at ? $user->registered_at->format('Y-m-d H:i:s') : null,
                    'session_end' => $user->expires_at ? $user->expires_at->format('Y-m-d H:i:s') : null,
                    'package_name' => $user->package->name ?? 'N/A',
                ];
            })
            ->toArray();

        return Inertia::render('Activeusers/Index', [
            'activeUsers' => $activeUsers,
        ]);
    }
}
