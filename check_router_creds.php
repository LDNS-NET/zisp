<?php

use App\Models\Tenants\TenantMikrotik;

// Find the router that was just onboarded
$router = TenantMikrotik::where('name', 'like', '%hAP%')
    ->orWhere('model', 'like', '%hAP%')
    ->latest()
    ->first();

if (!$router) {
    echo "Router not found. Showing all routers:\n";
    $routers = TenantMikrotik::latest()->take(5)->get();
    foreach ($routers as $r) {
        echo "ID: {$r->id} | Name: {$r->name} | Model: {$r->model}\n";
    }
    exit;
}

echo "Router Found:\n";
echo "ID: {$router->id}\n";
echo "Name: {$router->name}\n";
echo "Model: {$router->model}\n";
echo "VPN IP: {$router->wireguard_address}\n";
echo "\nCredentials:\n";
echo "Admin Username: {$router->router_username}\n";
echo "Admin Password: {$router->router_password}\n";
echo "API Username: {$router->api_username}\n";
echo "API Password: {$router->api_password}\n";
echo "\nAPI Password Length: " . strlen($router->api_password) . " characters\n";
