#!/usr/bin/env php
<?php

// Test script to update existing routers with Winbox addresses
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikScriptGenerator;
use Illuminate\Support\Facades\Log;

echo "Updating existing routers with Winbox addresses...\n\n";

$scriptGenerator = new MikrotikScriptGenerator();
$wgSubnet = config('wireguard.subnet') ?? env('WG_SUBNET', '10.100.0.0/16');

$routers = TenantMikrotik::whereNull('winbox')->get();

echo "Found " . $routers->count() . " routers without Winbox addresses\n\n";

foreach ($routers as $router) {
    $vpnIp = $scriptGenerator->deriveClientIpFromSubnet($wgSubnet, $router->id);
    
    if (!empty($vpnIp)) {
        $winboxAddress = $vpnIp . ':8291';
        $router->update(['winbox' => $winboxAddress]);
        
        echo "✓ Router ID {$router->id} ({$router->name}): {$winboxAddress}\n";
        
        Log::info('Winbox address assigned (backfill)', [
            'router_id' => $router->id,
            'winbox_address' => $winboxAddress,
        ]);
    } else {
        echo "✗ Router ID {$router->id} ({$router->name}): Failed to derive IP\n";
    }
}

echo "\nDone!\n";
