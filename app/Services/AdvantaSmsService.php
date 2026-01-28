<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdvantaSmsService
{
    protected $partnerId;
    protected $apiKey;
    protected $shortcode;
    protected $endpoint = 'https://quicksms.advantasms.com/api/services/sendsms/';
    
    /**
     * Set credentials for Advanta SMS
     */
    public function setCredentials(array $credentials)
    {
        $this->partnerId = $credentials['partner_id'] ?? null;
        $this->apiKey = $credentials['api_key'] ?? null;
        $this->shortcode = $credentials['shortcode'] ?? null;
    }
    
    /**
     * Send SMS via Advanta
     * 
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSMS(string $phoneNumber, string $message): array
    {
        if (!$this->partnerId || !$this->apiKey || !$this->shortcode) {
            return [
                'success' => false,
                'message' => 'Advanta SMS credentials not properly configured.'
            ];
        }
        
        try {
            $response = Http::post($this->endpoint, [
                'partnerID' => $this->partnerId,
                'apikey' => $this->apiKey,
                'shortcode' => $this->shortcode,
                'mobile' => $phoneNumber,
                'message' => $message,
            ]);
            
            $data = $response->json();
            
            // Advanta returns response-code 200 for success
            if ($response->successful() && isset($data['responses'][0]['response-code'])) {
                $responseCode = $data['responses'][0]['response-code'];
                
                if ($responseCode == 200) {
                    return [
                        'success' => true,
                        'message' => 'SMS sent successfully via Advanta.',
                        'provider_response' => $data
                    ];
                }
                
                return [
                    'success' => false,
                    'message' => $data['responses'][0]['response-description'] ?? 'Failed to send SMS.',
                    'provider_response' => $data
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to send SMS via Advanta.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('Advanta SMS failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
