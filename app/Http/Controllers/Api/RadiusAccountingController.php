<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\TenantActiveSession;
use App\Models\Tenants\TenantMikrotik;
use App\Models\Tenants\NetworkUser;
use Illuminate\Support\Facades\Log;

class RadiusAccountingController extends Controller
{
    public function store(Request $request)
    {
        // Validate incoming request (basic validation)
        // RADIUS servers might send data in different formats (JSON, Form Data). 
        // We assume standard form data or JSON with specific keys.
        
        $data = $request->all();
        Log::info('RADIUS Accounting Webhook:', $data);

        $statusType = $data['Acct-Status-Type'] ?? null;
        $username = $data['User-Name'] ?? null;
        $sessionId = $data['Acct-Session-Id'] ?? null;
        $nasIp = $data['NAS-IP-Address'] ?? null;
        $framedIp = $data['Framed-IP-Address'] ?? null;
        $macAddress = $data['Calling-Station-Id'] ?? null;

        if (!$statusType || !$sessionId || !$username) {
            return response()->json(['message' => 'Missing required fields'], 400);
        }

        // Find Router and Tenant
        $router = TenantMikrotik::where('ip_address', $nasIp)
            ->orWhere('wireguard_address', $nasIp)
            ->first();

        if (!$router) {
            Log::warning("RADIUS Accounting: Unknown NAS IP $nasIp");
            return response()->json(['message' => 'Unknown NAS IP'], 404);
        }

        // Find User
        $user = NetworkUser::where('username', $username)
            ->where('tenant_id', $router->tenant_id)
            ->first();

        // Unique Session Key (same logic as SyncOnlineUsers)
        // Ideally, we just use Acct-Session-Id if it's unique enough. 
        // But SyncOnlineUsers uses a composite key if session_id is missing. 
        // Here we have session_id. Let's use it, but we might need to align with SyncOnlineUsers.
        // SyncOnlineUsers logic: $uniqueSessionKey = $sessionData['session_id'] ?? ...
        // So if we have session_id, we use it.
        $uniqueSessionKey = $sessionId;

        if ($statusType === 'Start' || $statusType === 'Interim-Update') {
            TenantActiveSession::updateOrCreate(
                ['session_id' => $uniqueSessionKey],
                [
                    'router_id' => $router->id,
                    'tenant_id' => $router->tenant_id,
                    'user_id' => $user ? $user->id : null,
                    'ip_address' => $framedIp ?? '0.0.0.0',
                    'mac_address' => $macAddress ?? '',
                    'status' => 'active',
                    'last_seen_at' => now(),
                    // 'connected_at' => now(), // Only on Start?
                ]
            );

            if ($user) {
                $user->update(['online' => true]);
            }

        } elseif ($statusType === 'Stop') {
            // Mark as disconnected
            TenantActiveSession::where('session_id', $uniqueSessionKey)->update([
                'status' => 'disconnected',
                'last_seen_at' => now(),
            ]);

            if ($user) {
                // Check if user has other active sessions?
                $activeCount = TenantActiveSession::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('session_id', '!=', $uniqueSessionKey)
                    ->count();
                
                if ($activeCount === 0) {
                    $user->update(['online' => false]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
