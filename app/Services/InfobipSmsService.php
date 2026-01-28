<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfobipSmsService
{
    protected $apiKey;
    protected $baseUrl;
    protected $from;
    
    /**
     * Set credentials for Infobip
     */
    public function setCredentials(array $credentials)
    {
        $this->apiKey = $credentials['api_key'] ?? null;
        $this->baseUrl = $credentials['base_url'] ?? 'https://api.infobip.com';
        $this->from = $credentials['sender_id'] ?? null;
    }
    
    /**
     * Send SMS via Infobip
     * 
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSMS(string $phoneNumber, string $message): array
    {
        if (!$this->apiKey || !$this->from) {
            return [
                'success' => false,
                'message' => 'Infobip credentials not properly configured.'
            ];
        }
        
        $endpoint = rtrim($this->baseUrl, '/') . '/sms/2/text/advanced';
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'App ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($endpoint, [
                'messages' => [
                    [
                        'from' => $this->from,
                        'destinations' => [
                            ['to' => $phoneNumber]
                        ],
                        'text' => $message,
                    ]
                ]
            ]);
            
            $data = $response->json();
            
            // Infobip returns messages array with status
            if ($response->successful() && isset($data['messages'][0]['status'])) {
                $status = $data['messages'][0]['status'];
                
                // Status group 1 (PENDING) and 2 (UNDELIVERABLE) indicate submission success
                if (isset($status['groupId']) && in_array($status['groupId'], [1, 2])) {
                    return [
                        'success' => true,
                        'message' => 'SMS sent successfully via Infobip.',
                        'provider_response' => $data
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => $data['requestError']['serviceException']['text'] ?? 'Failed to send SMS via Infobip.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('Infobip SMS failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
