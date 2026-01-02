<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());
use App\Services\MomoService;
// 1. Setup Service with your Sandbox Credentials
$momo = new MomoService([
    'api_user' => 'YOUR_API_USER',
    'api_key' => 'YOUR_API_KEY',
    'subscription_key' => 'YOUR_SUBSCRIPTION_KEY',
    'environment' => 'sandbox'
]);
// 2. Initiate Payment
// Use a random number for sandbox (e.g., 256772123456)
$phone = '256772123456';
$amount = 5.00;
$reference = 'TEST-' . time();
echo "Initiating payment of $amount EUR to $phone...\n";
$response = $momo->requestToPay($phone, $amount, $reference);
if ($response['success']) {
    $refId = $response['reference_id'];
    echo "Payment Initiated! Reference ID: $refId\n";
    echo "Waiting 5 seconds for sandbox to process...\n";
    sleep(5);
    // 3. Check Status
    $status = $momo->getRequestStatus($refId);
    echo "Current Status: " . strtoupper($status['status']) . "\n";
    print_r($status['data']);
} else {
    echo "Payment Failed: " . $response['message'] . "\n";
    print_r($response['response'] ?? []);
}