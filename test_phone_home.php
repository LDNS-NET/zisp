<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Tenants\TenantMikrotikController;
use App\Models\Tenants\TenantMikrotik;

// Simulate a phone-home sync request with public IP
function simulatePhoneHomeSync()
{
    echo "Testing phone-home sync with public IP capture...\n";
    
    // Find a test router (you'll need to adjust this based on your actual data)
    $router = TenantMikrotik::first();
    
    if (!$router) {
        echo "❌ No router found in database. Please create a test router first.\n";
        return;
    }
    
    echo "Found router: {$router->name} (ID: {$router->id})\n";
    echo "Current IP: {$router->ip_address}\n";
    echo "Current Public IP: " . ($router->public_ip_address ?: 'Not set') . "\n";
    
    // Create a mock request that simulates a router phone-home from a public IP
    $request = new Request([
        'token' => $router->sync_token,
        'ip_address' => '203.0.113.123', // Test public IP (RFC 5737 TEST-NET-3)
        'board_name' => 'test-board',
        'system_version' => '7.1.5',
        'model' => 'RB4011',
    ]);
    
    // Mock the client IP as well (this is what would be used if ip_address isn't provided)
    $_SERVER['REMOTE_ADDR'] = '203.0.113.123';
    
    try {
        $controller = new TenantMikrotikController();
        $response = $controller->sync($router->id, $request);
        
        if ($response->getStatusCode() === 200) {
            echo "✅ Phone-home sync successful!\n";
            
            // Refresh router data
            $router->refresh();
            echo "Updated Public IP: " . ($router->public_ip_address ?: 'Not set') . "\n";
            echo "Preferred IP: " . $router->getPreferredIpAddress() . "\n";
            
            if ($router->public_ip_address === '203.0.113.123') {
                echo "✅ Public IP capture working correctly!\n";
            } else {
                echo "❌ Public IP was not captured correctly.\n";
            }
        } else {
            echo "❌ Phone-home sync failed with status: " . $response->getStatusCode() . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Exception during sync: " . $e->getMessage() . "\n";
    }
}

// Run the test
simulatePhoneHomeSync();
