<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantActiveUsersController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = tenant('id');
        $mikrotikModel = TenantMikrotik::where('tenant_id', $tenantId)->first();

        // If no Mikrotik exists, return empty array
        if (!$mikrotikModel) {
            return Inertia::render('Activeusers/Index', [
                'activeUsers' => [],
                'message' => 'No MikroTik device configured. Please add a MikroTik router first.',
            ]);
        }

        try {
            $mikrotik = new MikrotikService($mikrotikModel);
            $activeUsers = $mikrotik->getOnlineUsers();
        } catch (\Exception $e) {
            \Log::error('Failed to get active users: ' . $e->getMessage());
            $activeUsers = [];
        }

        return Inertia::render('Activeusers/Index', [
            'activeUsers' => $activeUsers,
        ]);
    }
}
