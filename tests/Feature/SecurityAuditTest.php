<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenants\TenantMikrotik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that public script download requires a valid token.
     */
    public function test_public_script_download_requires_valid_token()
    {
        $router = TenantMikrotik::withoutGlobalScopes()->create([
            'name' => 'Test Router',
            'sync_token' => 'valid-token',
            'tenant_id' => 1,
            'router_username' => 'admin',
            'router_password' => 'password'
        ]);

        // No token
        $response = $this->get(route('mikrotiks.downloadScriptPublic', $router->id));
        $response->assertStatus(403);

        // Invalid token
        $response = $this->get(route('mikrotiks.downloadScriptPublic', $router->id) . '?token=invalid');
        $response->assertStatus(403);

        // Valid token
        $response = $this->get(route('mikrotiks.downloadScriptPublic', $router->id) . '?token=valid-token');
        $response->assertStatus(200);
    }

    /**
     * Test M-Pesa C2B IP whitelisting.
     */
    public function test_mpesa_c2b_ip_whitelisting()
    {
        // Force production environment for testing IP whitelisting
        config(['mpesa.environment' => 'production']);

        // Unauthorized IP
        $response = $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])
            ->postJson('/api/mpesa/c2b/validation', []);
        $response->assertStatus(403);

        // Authorized IP (Safaricom range)
        $response = $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200'])
            ->postJson('/api/mpesa/c2b/validation', [
                'BillRefNumber' => 'test',
                'TransID' => 'test'
            ]);
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    /**
     * Test MoMo C2B IP whitelisting.
     */
    public function test_momo_c2b_ip_whitelisting()
    {
        // Force production environment for testing IP whitelisting
        config(['momo.environment' => 'production']);

        // Unauthorized IP
        $response = $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])
            ->postJson('/api/momo/c2b/callback', []);
        $response->assertStatus(403);

        // Authorized IP (MTN range)
        $response = $this->withServerVariables(['REMOTE_ADDR' => '196.11.240.10'])
            ->postJson('/api/momo/c2b/callback', [
                'externalId' => 'test',
                'status' => 'successful'
            ]);
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
