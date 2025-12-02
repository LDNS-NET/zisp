<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Radius\Radacct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TenantActiveUsersController extends Controller
{
    public function index(Request $request)
    {
        $activeUsers = [];
        $maxUsers = (int) ($request->input('limit', 500));
        $routers = TenantMikrotik::all()->keyBy('wireguard_address');

        // Fetch active sessions from RADIUS radacct where acctstoptime IS NULL
        $radRows = Radacct::whereNull('acctstoptime')->limit($maxUsers * 2)->get();

        foreach ($radRows as $row) {
            $router = $routers[$row->nasipaddress] ?? null;
            $routerName = $router?->name ?? ($row->nasipaddress);
            $type = str_contains(strtolower($row->callingstationid), ':') ? 'hotspot' : 'pppoe';

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
