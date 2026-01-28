<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TalksasaSmsService
{
    protected $apiKey;
    protected $senderId;
    protected $endpoint = 'https://bulksms.talksasa.com/api/v3/sms/send';
    
    /**
     * Set credentials for Talksasa SMS
     */
    public function setCredentials(array $credentials)
    {
        $this->apiKey = $credentials['api_key'] ?? null;
        $this->senderId = $credentials['sender_id'] ?? null;
    }
    
    /**
     * Send SMS via Talksasa
     * 
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSMS(string $phoneNumber, string $message): array
    {
        if (!$this->apiKey || !$this->senderId) {
            return [
                'success' => false,
                'message' => 'Talksasa SMS credentials not properly configured.'
            ];
        }
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->endpoint, [
                'recipient' => $phoneNumber,
                'sender_id' => $this->senderId,
                'type' => 'plain',
                'message' => $message,
            ]);
            
            $data = $response->json();
            
            if ($response->successful() && isset($data['status']) && $data['status'] === 'success') {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully via Talksasa.',
                    'provider_response' => $data
                ];
            }
            
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to send SMS via Talksasa.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('Talksasa SMS failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
