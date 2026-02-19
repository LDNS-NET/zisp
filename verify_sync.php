<?php

use App\Models\Tenants\TenantActiveUsers;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikUserSyncService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Setup test data
$tenantId = 1; // Assuming tenant 1 exists
$username = 'test_user_' . time();

echo "Testing MikroTik sync logic for user: $username\n";

// Create a network user
$user = NetworkUser::create([
    'username' => $username,
    'password' => 'pass123',
    'tenant_id' => $tenantId,
    'type' => 'pppoe',
    'status' => 'active',
]);

// Create an active session with some usage (simulate RADIUS update)
$session = TenantActiveUsers::create([
    'tenant_id' => $tenantId,
    'username' => $username,
    'user_id' => $user->id,
    'status' => 'active',
    'bytes_in' => 1000000,
    'bytes_out' => 500000,
    'last_seen_at' => now(),
    'router_id' => 1, // Assuming router 1 exists
]);

echo "Initial session created. In: {$session->bytes_in}, Out: {$session->bytes_out}\n";

// Simulate MikroTik sync with 0 bytes (which was causing the overwrite)
$syncService = new class extends MikrotikUserSyncService {
    public function testSync(TenantMikrotik $router, array $activeSessions) {
        $tenantId = $router->tenant_id;
        $usersStillOnline = array_keys($activeSessions);
        
        $stillOnlineLower = array_map('strtolower', $usersStillOnline);
        $stillOnlineUsers = NetworkUser::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn(DB::raw('lower(username)'), $stillOnlineLower)
            ->pluck('id', DB::raw('lower(username)'))
            ->toArray();

        foreach ($usersStillOnline as $username) {
            $data = $activeSessions[$username];
            $loweredUsername = strtolower($username);
            
            $updateData = [
                'last_seen_at' => now(),
                'user_id' => $stillOnlineUsers[$loweredUsername] ?? null,
            ];

            if (($data['bytes_in'] ?? 0) > 0) {
                $updateData['bytes_in'] = $data['bytes_in'];
            }
            if (($data['bytes_out'] ?? 0) > 0) {
                $updateData['bytes_out'] = $data['bytes_out'];
            }

            TenantActiveUsers::where('tenant_id', $tenantId)
                ->where('router_id', $router->id)
                ->where('status', 'active')
                ->where(DB::raw('lower(trim(username))'), strtolower(trim($username)))
                ->update($updateData);
        }
    }
};

$router = TenantMikrotik::find(1) ?: new TenantMikrotik(['id' => 1, 'tenant_id' => $tenantId]);
$mockSessions = [
    $username => [
        'bytes_in' => 0,
        'bytes_out' => 0,
        'ip_address' => '1.2.3.4',
        'mac_address' => 'AA:BB:CC:DD:EE:FF'
    ]
];

echo "Simulating MikroTik sync with 0 bytes...\n";
$syncService->testSync($router, $mockSessions);

$updatedSession = TenantActiveUsers::where('username', $username)->first();
echo "Updated session. In: {$updatedSession->bytes_in}, Out: {$updatedSession->bytes_out}\n";

if ($updatedSession->bytes_in == 1000000 && $updatedSession->bytes_out == 500000) {
    echo "SUCCESS: Usage data was PRESERVED.\n";
} else {
    echo "FAILURE: Usage data was OVERWRITTEN!\n";
}

// Cleanup
$user->forceDelete();
$updatedSession->forceDelete();
