<?php
// Replace with your Primary Subscription Key from the portal
$subscriptionKey = '5552521ca61549fb858741e546b46aa9'; 
// 1. Generate API User (UUID v4)
$apiUserId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);
echo "Generating API User: $apiUserId\n";
// 2. Create API User
$ch = curl_init("https://sandbox.momodeveloper.mtn.com/v1_0/apiuser");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['providerCallbackHost' => 'localhost']));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Reference-Id: $apiUserId",
    "Ocp-Apim-Subscription-Key: $subscriptionKey",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($status == 201) {
    echo "API User created successfully!\n";
    
    // 3. Generate API Key
    curl_setopt($ch, CURLOPT_URL, "https://sandbox.momodeveloper.mtn.com/v1_0/apiuser/$apiUserId/apikey");
    curl_setopt($ch, CURLOPT_POSTFIELDS, "");
    $keyResponse = curl_exec($ch);
    $keyData = json_decode($keyResponse, true);
    
    if (isset($keyData['apiKey'])) {
        echo "\n--- YOUR CREDENTIALS ---\n";
        echo "API User: $apiUserId\n";
        echo "API Key:  " . $keyData['apiKey'] . "\n";
        echo "Subscription Key: $subscriptionKey\n";
        echo "Environment: sandbox\n";
        echo "------------------------\n";
    } else {
        echo "Failed to generate API Key: " . $keyResponse . "\n";
    }
} else {
    echo "Failed to create API User (Status $status): " . $response . "\n";
}
curl_close($ch);