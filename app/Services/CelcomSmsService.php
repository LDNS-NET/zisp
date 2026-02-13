<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CelcomSmsService
{
    protected $partnerId;
    protected $apiKey;
    protected $shortcode;
    protected $endpoint = 'https://isms.celcomafrica.com/api/services/sendsms/';
    
    /**
     * Set credentials for Celcom SMS
     */
    public function setCredentials(array $credentials)
    {
        $this->partnerId = $credentials['partner_id'] ?? null;
        $this->apiKey = $credentials['api_key'] ?? null;
        $this->shortcode = $credentials['shortcode'] ?? null;
    }
    
    /**
     * Send SMS via Celcom
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
                'message' => 'Celcom SMS credentials not properly configured.'
            ];
        }
        
        try {
            $response = Http::asForm()->post($this->endpoint, [
                'partnerID' => $this->partnerId,
                'apikey' => $this->apiKey,
                'shortcode' => $this->shortcode,
                'mobile' => $phoneNumber,
                'message' => $message,
            ]);
            
            $data = $response->json();
            
            // Log full response if not successful for debugging
            if (!$response->successful()) {
                Log::error('Celcom SMS HTTP error: ' . $response->status() . ' - ' . $response->body());
            }
            
            // Celcom may return different success indicators
            $isSuccess = $response->successful() && (
                (isset($data['status']) && ($data['status'] == '200' || $data['status'] === 'success')) ||
                (isset($data['responses'][0]['response-code']) && $data['responses'][0]['response-code'] == 200)
            );
            
            if ($isSuccess) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully via Celcom.',
                    'provider_response' => $data
                ];
            }
            
            // Log failure details
            Log::warning('Celcom SMS failed to send. Response: ' . json_encode($data));
            
            return [
                'success' => false,
                'message' => $data['message'] ?? $data['responses'][0]['response-description'] ?? 'Failed to send SMS via Celcom.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('Celcom SMS exception: ' . $e->getMessage(), [
                'exception' => $e,
                'phone' => $phoneNumber
            ]);
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
