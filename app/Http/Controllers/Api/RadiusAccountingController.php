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
        try {
            // Validate incoming request (basic validation)
            // RADIUS servers might send data in different formats (JSON, Form Data). 
            // We assume standard form data or JSON with specific keys.
            
            $data = $request->all();
            Log::info('RADIUS Accounting Webhook:', $data);

            // Helper to extract value from RADIUS JSON format
            $extract = function($value) {
                if (!is_array($value)) return $value;
                // Handle verbose format: ['type' => 'string', 'value' => ['data']]
                if (isset($value['value']) && is_array($value['value'])) {
                    return $value['value'][0] ?? null;
                }
                // Handle simple array: ['data']
                return $value[0] ?? null;
            };

            $statusType = $extract($data['Acct-Status-Type'] ?? null);
            $username = $extract($data['User-Name'] ?? null);
            $sessionId = $extract($data['Acct-Session-Id'] ?? null);
            $nasIp = $extract($data['NAS-IP-Address'] ?? null);
            $framedIp = $extract($data['Framed-IP-Address'] ?? null);
            $macAddress = $extract($data['Calling-Station-Id'] ?? null);

            if (!$statusType || !$sessionId || !$username) {
                // Return received data to help debugging in FreeRADIUS logs
                return response()->json([
                    'message' => 'Missing required fields',
                    'received' => $data,
                    'parsed' => [
                        'statusType' => $statusType,
                        'username' => $username,
                        'sessionId' => $sessionId
                    ]
                ], 400);
            }

            // Find Router and Tenant
            // Use withoutGlobalScopes to ensure we find the router regardless of current context
            $router = TenantMikrotik::withoutGlobalScopes()
                ->where('wireguard_address', $nasIp)
                ->first();

            if (!$router) {
                Log::warning("RADIUS Accounting: Unknown NAS IP $nasIp");
                return response()->json(['message' => 'Unknown NAS IP'], 404);
            }

            // Find User
            // Use withoutGlobalScopes to ensure we find the user regardless of current context
            $user = NetworkUser::withoutGlobalScopes()
                ->where('username', $username)
                ->where('tenant_id', $router->tenant_id)
                ->first();

            // Unique Session Key
            $uniqueSessionKey = $sessionId;

            if ($statusType === 'Start' || $statusType === 'Interim-Update') {
                TenantActiveSession::withoutGlobalScopes()->updateOrCreate(
                    ['session_id' => $uniqueSessionKey],
                    [
                        'router_id' => $router->id,
                        'tenant_id' => $router->tenant_id,
                        'user_id' => $user ? $user->id : null,
                        'ip_address' => $framedIp ?? '0.0.0.0',
                        'mac_address' => $macAddress ?? '',
                        'status' => 'active',
                        'last_seen_at' => now(),
                    ]
                );

                if ($user) {
                    $user->update(['online' => true]);
                }

            } elseif ($statusType === 'Stop') {
                // Mark as disconnected
                TenantActiveSession::withoutGlobalScopes()
                    ->where('session_id', $uniqueSessionKey)
                    ->update([
                        'status' => 'disconnected',
                        'last_seen_at' => now(),
                    ]);

                if ($user) {
                    // Check if user has other active sessions
                    $activeCount = TenantActiveSession::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->where('session_id', '!=', $uniqueSessionKey)
                        ->count();
                    
                    if ($activeCount === 0) {
                        $user->update(['online' => false]);
                    }
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('RADIUS Accounting Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }
}
