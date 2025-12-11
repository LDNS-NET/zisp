#!/usr/bin/env php
<?php

// Update script for Winbox addresses - Port Forwarding Strategy
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikScriptGenerator;
use Illuminate\Support\Facades\Log;

echo "Updating routers with Winbox addresses (Port Forwarding Strategy)...\n\n";

$scriptGenerator = new MikrotikScriptGenerator();
$wgSubnet = config('wireguard.subnet') ?? env('WG_SUBNET', '10.100.0.0/16');
$vpnServerIp = config('wireguard.server_public_ip') ?? env('WG_SERVER_PUBLIC_IP', '10.100.0.1');

// Get all routers (including those with old winbox format)
$routers = TenantMikrotik::all();

// Find the highest existing port
$existingPorts = $routers->whereNotNull('winbox')
    ->pluck('winbox')
    ->map(function($winbox) {
        $parts = explode(':', $winbox);
        return count($parts) === 2 ? (int)$parts[1] : null;
    })
    ->filter()
    ->toArray();

$nextPort = !empty($existingPorts) ? max($existingPorts) + 1 : 5000;

echo "VPN Server IP: {$vpnServerIp}\n";
echo "Starting port: {$nextPort}\n";
echo "Found " . $routers->count() . " routers\n\n";

foreach ($routers as $router) {
    $routerVpnIp = $scriptGenerator->deriveClientIpFromSubnet($wgSubnet, $router->id);
    
    // Check if router already has a winbox address in the new format
    if ($router->winbox && strpos($router->winbox, $vpnServerIp) === 0) {
        echo "⊙ Router ID {$router->id} ({$router->name}): Already configured - {$router->winbox}\n";
        continue;
    }
    
    // Assign new port
    $winboxAddress = $vpnServerIp . ':' . $nextPort;
    $router->update(['winbox' => $winboxAddress]);
    
    echo "✓ Router ID {$router->id} ({$router->name}): {$winboxAddress}\n";
    echo "  → NAT forward: {$vpnServerIp}:{$nextPort} -> {$routerVpnIp}:8291\n";
    
    Log::info('Winbox address updated (backfill - port forwarding)', [
        'router_id' => $router->id,
        'winbox_address' => $winboxAddress,
        'router_vpn_ip' => $routerVpnIp,
    ]);
    
    $nextPort++;
}

echo "\n✅ Done! Next available port: {$nextPort}\n";
echo "\n⚠️  IMPORTANT: Configure NAT forwarding rules on your VPN server!\n";

