<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickSendSmsService
{
    protected $username;
    protected $apiKey;
    protected $endpoint = 'https://rest.clicksend.com/v3/sms/send';
    
    /**
     * Set credentials for ClickSend
     */
    public function setCredentials(array $credentials)
    {
        $this->username = $credentials['username'] ?? null;
        $this->apiKey = $credentials['api_key'] ?? null;
    }
    
    /**
     * Send SMS via ClickSend
     * 
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSMS(string $phoneNumber, string $message): array
    {
        if (!$this->username || !$this->apiKey) {
            return [
                'success' => false,
                'message' => 'ClickSend credentials not properly configured.'
            ];
        }
        
        try {
            $response = Http::withBasicAuth($this->username, $this->apiKey)
                ->post($this->endpoint, [
                    'messages' => [
                        [
                            'to' => $phoneNumber,
                            'body' => $message,
                            'source' => 'php',
                        ]
                    ]
                ]);
            
            $data = $response->json();
            
            // ClickSend returns http_code 200 and response_code SUCCESS for successful sends
            if ($response->successful() && isset($data['data']['messages'][0])) {
                $messageData = $data['data']['messages'][0];
                
                if ($messageData['status'] === 'SUCCESS') {
                    return [
                        'success' => true,
                        'message' => 'SMS sent successfully via ClickSend.',
                        'provider_response' => $data
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => $data['response_msg'] ?? 'Failed to send SMS via ClickSend.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('ClickSend SMS failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
