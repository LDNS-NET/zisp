<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioSmsService
{
    protected $accountSid;
    protected $authToken;
    protected $fromNumber;
    protected $endpoint;
    
    /**
     * Set credentials for Twilio SMS
     */
    public function setCredentials(array $credentials)
    {
        $this->accountSid = $credentials['account_sid'] ?? null;
        $this->authToken = $credentials['auth_token'] ?? null;
        $this->fromNumber = $credentials['from_number'] ?? null;
        $this->endpoint = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";
    }
    
    /**
     * Send SMS via Twilio
     * 
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSMS(string $phoneNumber, string $message): array
    {
        if (!$this->accountSid || !$this->authToken || !$this->fromNumber) {
            return [
                'success' => false,
                'message' => 'Twilio SMS credentials not properly configured.'
            ];
        }
        
        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post($this->endpoint, [
                    'To' => $phoneNumber,
                    'From' => $this->fromNumber,
                    'Body' => $message,
                ]);
            
            $data = $response->json();
            
            // Twilio returns status in the response
            if ($response->successful() && isset($data['sid'])) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully via Twilio.',
                    'provider_response' => $data
                ];
            }
            
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to send SMS via Twilio.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('Twilio SMS failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
