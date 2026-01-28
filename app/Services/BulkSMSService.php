<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BulkSMSService
{
    protected $username;
    protected $password;
    protected $endpoint = 'https://api.bulksms.com/v1/messages';
    
    /**
     * Set credentials for BulkSMS
     */
    public function setCredentials(array $credentials)
    {
        $this->username = $credentials['username'] ?? null;
        $this->password = $credentials['password'] ?? null;
    }
    
    /**
     * Send SMS via BulkSMS
     * 
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSMS(string $phoneNumber, string $message): array
    {
        if (!$this->username || !$this->password) {
            return [
                'success' => false,
                'message' => 'BulkSMS credentials not properly configured.'
            ];
        }
        
        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->post($this->endpoint, [
                    'to' => $phoneNumber,
                    'body' => $message,
                ]);
            
            $data = $response->json();
            
            // BulkSMS returns 201 for successful message submission
            if ($response->status() === 201) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully via BulkSMS.',
                    'provider_response' => $data
                ];
            }
            
            return [
                'success' => false,
                'message' => $data['detail'] ?? 'Failed to send SMS via BulkSMS.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('BulkSMS failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
