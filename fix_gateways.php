<?php

use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\Package;
use App\Models\Tenants\NetworkUser;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Starting configuration fix...\n";

// 1. Find the Tenant
$tenant = Tenant::where('name', 'LDNS NETWORKS')->first();
if (!$tenant) {
    echo "Tenant 'LDNS NETWORKS' not found. Please create it first or update this script.\n";
    exit(1);
}
echo "Found Tenant: {$tenant->name} ({$tenant->id})\n";

// 2. Configure Gateways
$gateways = [
    'mpesa' => [
        'label' => 'M-Pesa Express',
        'mpesa_consumer_key' => 'test_key', // Replace with real credentials on prod if needed
        'mpesa_consumer_secret' => 'test_secret',
        'mpesa_passkey' => 'test_passkey',
        'mpesa_shortcode' => '174379',
        'mpesa_env' => 'sandbox'
    ],
    'momo' => [
        'label' => 'MTN MoMo',
        'momo_api_user' => 'test_user',
        'momo_api_key' => 'test_key',
        'momo_subscription_key' => 'test_sub',
        'momo_env' => 'sandbox'
    ]
];

foreach ($gateways as $provider => $data) {
    $gateway = TenantPaymentGateway::firstOrNew([
        'tenant_id' => $tenant->id,
        'provider' => $provider
    ]);
    
    $gateway->fill($data);
    $gateway->is_active = true;
    $gateway->save();
    echo "Configured Gateway: {$data['label']}\n";
}

// 3. Ensure PPPoE Package Exists
$package = Package::where('tenant_id', $tenant->id)->where('type', 'pppoe')->first();
if (!$package) {
    Package::create([
        'tenant_id' => $tenant->id,
        'name' => 'Premium PPPoE',
        'type' => 'pppoe',
        'price' => 2000,
        'download_speed' => 10,
        'upload_speed' => 10,
        'duration_value' => 1,
        'duration_unit' => 'months',
        'created_by' => 1
    ]);
    echo "Created missing PPPoE Package.\n";
} else {
    echo "PPPoE Package exists: {$package->name}\n";
}

echo "Configuration complete!\n";
