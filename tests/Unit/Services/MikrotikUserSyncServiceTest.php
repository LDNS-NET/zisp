<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Tenants\TenantActiveUsers;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikUserSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class MikrotikUserSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_preserves_non_zero_usage_when_mikrotik_returns_zero()
    {
        // Setup
        $tenantId = 1;
        $username = 'testuser';

        $user = NetworkUser::factory()->create([
            'username' => $username,
            'tenant_id' => $tenantId,
            'type' => 'pppoe',
        ]);

        $router = TenantMikrotik::factory()->create([
            'id' => 1,
            'tenant_id' => $tenantId,
        ]);

        // Create an active session with existing usage
        $session = TenantActiveUsers::create([
            'tenant_id' => $tenantId,
            'username' => $username,
            'user_id' => $user->id,
            'router_id' => $router->id,
            'status' => 'active',
            'bytes_in' => 1000,
            'bytes_out' => 2000,
            'last_seen_at' => now(),
        ]);

        // Mock the service to only test the sync logic without actual router connection
        $service = new MikrotikUserSyncService();
        
        // We need to bypass the fetch and call the logic. 
        // Since syncActiveUsers is the entry point, we can mock fetchActiveSessionsFromMikrotik
        $partialService = $this->getMockBuilder(MikrotikUserSyncService::class)
            ->onlyMethods(['fetchActiveSessionsFromMikrotik'])
            ->get();

        // Simulate MikroTik returning 0 bytes
        $partialService->method('fetchActiveSessionsFromMikrotik')
            ->willReturn([
                $username => [
                    'bytes_in' => 0,
                    'bytes_out' => 0,
                    'ip_address' => '1.1.1.1',
                    'mac_address' => 'AA:BB:CC'
                ]
            ]);

        // Act
        $partialService->syncActiveUsers($router);

        // Assert
        $session->refresh();
        $this->assertEquals(1000, $session->bytes_in, 'Bytes in should be preserved');
        $this->assertEquals(2000, $session->bytes_out, 'Bytes out should be preserved');
    }

    public function test_sync_updates_usage_when_mikrotik_returns_positive_values()
    {
        // Setup
        $tenantId = 1;
        $username = 'testuser';

        $user = NetworkUser::factory()->create([
            'username' => $username,
            'tenant_id' => $tenantId,
            'type' => 'hotspot',
        ]);

        $router = TenantMikrotik::factory()->create([
            'id' => 1,
            'tenant_id' => $tenantId,
        ]);

        $session = TenantActiveUsers::create([
            'tenant_id' => $tenantId,
            'username' => $username,
            'user_id' => $user->id,
            'router_id' => $router->id,
            'status' => 'active',
            'bytes_in' => 1000,
            'bytes_out' => 2000,
            'last_seen_at' => now(),
        ]);

        $partialService = $this->getMockBuilder(MikrotikUserSyncService::class)
            ->onlyMethods(['fetchActiveSessionsFromMikrotik'])
            ->get();

        // Simulate MikroTik returning HIGHER bytes
        $partialService->method('fetchActiveSessionsFromMikrotik')
            ->willReturn([
                $username => [
                    'bytes_in' => 5000,
                    'bytes_out' => 6000,
                    'ip_address' => '1.1.1.1',
                    'mac_address' => 'AA:BB:CC'
                ]
            ]);

        // Act
        $partialService->syncActiveUsers($router);

        // Assert
        $session->refresh();
        $this->assertEquals(5000, $session->bytes_in, 'Bytes in should be updated');
        $this->assertEquals(6000, $session->bytes_out, 'Bytes out should be updated');
    }
}
