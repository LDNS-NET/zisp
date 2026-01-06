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

            Log::debug("RADIUS Parsed: Status=$statusType, User=$username, Session=$sessionId, NAS=$nasIp");

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
            // Match by any of the possible IP addresses the RADIUS server might report
            $router = TenantMikrotik::withoutGlobalScopes()
                ->where(function($query) use ($nasIp) {
                    $query->where('wireguard_address', $nasIp)
                          ->orWhere('public_ip', $nasIp);
                })
                ->first();

            if (!$router) {
                Log::warning("RADIUS Accounting: Unknown NAS IP $nasIp");
                return response()->json(['message' => 'Unknown NAS IP'], 404);
            }

            // Find User (Case-insensitive)
            // Use withoutGlobalScopes to ensure we find the user regardless of current context
            $user = NetworkUser::withoutGlobalScopes()
                ->where('tenant_id', $router->tenant_id)
                ->where(function($q) use ($username) {
                    $q->where('username', $username)
                      ->orWhere(\DB::raw('lower(username)'), strtolower($username));
                })
                ->first();

            if (!$user) {
                Log::warning("RADIUS Accounting: User not found: $username for tenant {$router->tenant_id}");
            }

            // Unique Session Key
            $uniqueSessionKey = $sessionId;

            // Handle both string and numeric status types (1=Start, 3=Interim-Update)
            if (in_array($statusType, ['Start', 'Interim-Update', '1', 1, '3', 3])) {
                TenantActiveSession::withoutGlobalScopes()->updateOrCreate(
                    ['session_id' => $uniqueSessionKey],
                    [
                        'router_id' => $router->id,
                        'tenant_id' => $router->tenant_id,
                        'user_id' => $user ? $user->id : null,
                        'username' => $username,
                        'ip_address' => $framedIp ?? '0.0.0.0',
                        'mac_address' => $macAddress ?? '',
                        'status' => 'active',
                        'last_seen_at' => now(),
                    ]
                );

                if ($user) {
                    $user->withoutGlobalScopes()->update(['online' => true]);
                }

                Log::info("RADIUS Accounting: Updated active session for $username on router {$router->id}");
            } elseif (in_array($statusType, ['Stop', '2', 2])) {
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
                        ->where('tenant_id', $router->tenant_id)
                        ->where('status', 'active')
                        ->where('session_id', '!=', $uniqueSessionKey)
                        ->count();
                    
                    if ($activeCount === 0) {
                        $user->withoutGlobalScopes()->update(['online' => false]);
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
