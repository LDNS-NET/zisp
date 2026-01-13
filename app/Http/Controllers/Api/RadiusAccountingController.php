<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

            // Extract metrics
            $inputOctets = (int) $extract($data['Acct-Input-Octets'] ?? 0);
            $outputOctets = (int) $extract($data['Acct-Output-Octets'] ?? 0);
            $inputGigawords = (int) $extract($data['Acct-Input-Gigawords'] ?? 0);
            $outputGigawords = (int) $extract($data['Acct-Output-Gigawords'] ?? 0);
            $sessionTime = (int) $extract($data['Acct-Session-Time'] ?? 0);

            // Calculate total bytes (Gigawords * 4GB + Octets)
            $bytesIn = ($inputGigawords * 4294967296) + $inputOctets;
            $bytesOut = ($outputGigawords * 4294967296) + $outputOctets;

            Log::debug("RADIUS Parsed: Status=$statusType, User=$username, Session=$sessionId, NAS=$nasIp, In=$bytesIn, Out=$bytesOut");

            // Find Router and Tenant first (needed for all packet types including Accounting-On)
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

            // Handle NAS-wide events (Accounting-On/Off)
            // These signify router reboot or shutdown and don't have username/session_id
            if (in_array($statusType, ['Accounting-On', 'Accounting-Off', '7', 7, '8', 8])) {
                Log::info("RADIUS Accounting: Router reboot/shutdown detected for router {$router->id}. Marking all sessions as disconnected.");
                
                \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
                    ->where('router_id', $router->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'disconnected',
                        'last_seen_at' => now(),
                        'disconnected_at' => now(),
                    ]);
                
                // Update all users of this router to offline (simplified)
                NetworkUser::withoutGlobalScopes()
                    ->where('tenant_id', $router->tenant_id)
                    ->update(['online' => false]);

                return response()->json(['status' => 'success', 'message' => 'NAS sessions cleared']);
            }

            // Normal session validation (Start/Interim/Stop)
            if (!$statusType || !$sessionId || !$username) {
                // Return received data to help debugging in FreeRADIUS logs
                return response()->json([
                    'message' => 'Missing required fields for session packet',
                    'received' => $data,
                    'parsed' => [
                        'statusType' => $statusType,
                        'username' => $username,
                        'sessionId' => $sessionId
                    ]
                ], 400);
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

            if (in_array($statusType, ['Start', 'Interim-Update', '1', 1, '3', 3])) {
                // Prune other active sessions for this user/MAC to prevent duplicates
                // We mark them as disconnected if they have a different session_id
                \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
                    ->where('tenant_id', $router->tenant_id)
                    ->where('status', 'active')
                    ->where('session_id', '!=', $uniqueSessionKey)
                    ->where(function($q) use ($username) {
                        $q->where('username', $username);
                    })
                    ->update([
                        'status' => 'disconnected',
                        'last_seen_at' => now(),
                        'disconnected_at' => now(),
                    ]);

                \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()->updateOrCreate(
                    ['session_id' => $uniqueSessionKey],
                    [
                        'router_id' => $router->id,
                        'tenant_id' => $router->tenant_id,
                        'user_id' => $user ? $user->id : null,
                        'username' => $username,
                        'ip_address' => $framedIp ?? '0.0.0.0',
                        'mac_address' => $macAddress ?? '',
                        'status' => 'active',
                        'bytes_in' => $bytesIn,
                        'bytes_out' => $bytesOut,
                        'last_seen_at' => now(),
                        'connected_at' => $statusType == 'Start' ? now() : null,
                    ]
                );

                if ($user) {
                    $user->withoutGlobalScopes()->update(['online' => true]);
                }

                Log::info("RADIUS Accounting: Updated active user for $username on router {$router->id}");
            } elseif (in_array($statusType, ['Stop', '2', 2])) {
                // Mark as disconnected
                \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
                    ->where('session_id', $uniqueSessionKey)
                    ->update([
                        'status' => 'disconnected',
                        'bytes_in' => $bytesIn,
                        'bytes_out' => $bytesOut,
                        'last_seen_at' => now(),
                        'disconnected_at' => now(),
                    ]);

                if ($user) {
                    // Check if user has other active sessions
                    $activeCount = \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
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
