<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tenants\TenantDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDeviceIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure we have a clean state
        TenantDevice::withoutGlobalScopes()->delete();
    }

    public function test_tenant_can_only_see_their_own_devices()
    {
        // 1. Create two tenants
        $tenant1 = Tenant::create(['id' => 'tenant1', 'name' => 'Tenant One']);
        $tenant2 = Tenant::create(['id' => 'tenant2', 'name' => 'Tenant Two']);

        // 2. Create devices for each tenant
        TenantDevice::withoutGlobalScopes()->create([
            'serial_number' => 'DEV-T1',
            'tenant_id' => 'tenant1',
        ]);

        TenantDevice::withoutGlobalScopes()->create([
            'serial_number' => 'DEV-T2',
            'tenant_id' => 'tenant2',
        ]);

        // 3. Create an unassigned device (discovery mode)
        TenantDevice::withoutGlobalScopes()->create([
            'serial_number' => 'DEV-DISC',
            'tenant_id' => null,
        ]);

        // 4. Test Tenant 1 context
        tenancy()->initialize($tenant1);
        
        $this->assertEquals(1, TenantDevice::count());
        $this->assertEquals('DEV-T1', TenantDevice::first()->serial_number);
        
        // Ensure they cannot see unassigned or other tenant's devices
        $this->assertFalse(TenantDevice::where('serial_number', 'DEV-T2')->exists());
        $this->assertFalse(TenantDevice::where('serial_number', 'DEV-DISC')->exists());

        // 5. Test Tenant 2 context
        tenancy()->end();
        tenancy()->initialize($tenant2);

        $this->assertEquals(1, TenantDevice::count());
        $this->assertEquals('DEV-T2', TenantDevice::first()->serial_number);
        $this->assertFalse(TenantDevice::where('serial_number', 'DEV-T1')->exists());
        $this->assertFalse(TenantDevice::where('serial_number', 'DEV-DISC')->exists());

        // 6. Test Central / Global Context (Super Admin)
        tenancy()->end();
        $this->assertEquals(3, TenantDevice::count());
    }

    public function test_sync_to_context_updates_unassigned_device()
    {
        $tenant1 = Tenant::create(['id' => 'tenant1', 'name' => 'Tenant One']);
        
        // Create an unassigned device
        TenantDevice::withoutGlobalScopes()->create([
            'serial_number' => 'DEV-SYNC',
            'tenant_id' => null,
        ]);

        $job = new \App\Jobs\SyncGenieACSDevicesJob();
        $service = $this->createMock(\App\Services\GenieACSService::class);
        
        // Call the private syncToContext using reflection or just test it via a public entry point if possible
        // Since it's private, I'll use reflection for a precise unit test of the logic
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('syncToContext');
        $method->setAccessible(true);
        
        $method->invoke($job, $service, ['_id' => 'DEV-SYNC'], 'DEV-SYNC', 'tenant1');

        // Verify the device now belongs to tenant1 and no duplicate was created
        $this->assertEquals(1, TenantDevice::withoutGlobalScopes()->where('serial_number', 'DEV-SYNC')->count());
        $device = TenantDevice::withoutGlobalScopes()->where('serial_number', 'DEV-SYNC')->first();
        $this->assertEquals('tenant1', $device->tenant_id);
    }
}
