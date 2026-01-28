<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSmsService
{
    protected $username;
    protected $apiKey;
    protected $senderId;
    protected $endpoint = 'https://api.africastalking.com/version1/messaging';
    
    /**
     * Set credentials for Africa's Talking SMS
     */
    public function setCredentials(array $credentials)
    {
        $this->username = $credentials['username'] ?? null;
        $this->apiKey = $credentials['api_key'] ?? null;
        $this->senderId = $credentials['sender_id'] ?? null;
    }
    
    /**
     * Send SMS via Africa's Talking
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
                'message' => 'Africa\'s Talking SMS credentials not properly configured.'
            ];
        }
        
        try {
            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($this->endpoint, [
                'username' => $this->username,
                'to' => $phoneNumber,
                'message' => $message,
                'from' => $this->senderId,
            ]);
            
            $data = $response->json();
            
            // Africa's Talking returns SMSMessageData with Recipients array
            if ($response->successful() && isset($data['SMSMessageData']['Recipients'])) {
                $recipients = $data['SMSMessageData']['Recipients'];
                
                // Check if first recipient was successful (status code 100-102)
                if (!empty($recipients) && isset($recipients[0]['statusCode'])) {
                    $statusCode = $recipients[0]['statusCode'];
                    
                    if (in_array($statusCode, [100, 101, 102])) {
                        return [
                            'success' => true,
                            'message' => 'SMS sent successfully via Africa\'s Talking.',
                            'provider_response' => $data
                        ];
                    }
                }
                
                return [
                    'success' => false,
                    'message' => $data['SMSMessageData']['Message'] ?? 'Failed to send SMS.',
                    'provider_response' => $data
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to send SMS via Africa\'s Talking.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('Africa\'s Talking SMS failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
