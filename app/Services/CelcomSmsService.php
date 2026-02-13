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
            // According to documentation, sendsms expects these parameters
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
            
            // Celcom returns status code in the response body often
            $statusCode = $data['status'] ?? $data['responses'][0]['response-code'] ?? null;
            
            $isSuccess = $response->successful() && ($statusCode == 200 || $statusCode === 'success');
            
            if ($isSuccess) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully via Celcom.',
                    'provider_response' => $data
                ];
            }
            
            // Get human-readable error message based on Documentation
            $errorMessage = $this->getErrorMessage($statusCode, $data);
            
            // Log failure details
            Log::warning("Celcom SMS failed (Code: {$statusCode}). Message: {$errorMessage}. Full Response: " . json_encode($data));
            
            return [
                'success' => false,
                'message' => $errorMessage,
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

    /**
     * Map Celcom error codes to human-readable messages
     */
    protected function getErrorMessage($code, array $data): string
    {
        $errors = [
            '200'  => 'Successful Request Call',
            '1001' => 'Invalid sender id / shortcode',
            '1002' => 'Network not allowed',
            '1003' => 'Invalid mobile number',
            '1004' => 'Low bulk credits',
            '1005' => 'Failed. System error API',
            '1006' => 'Invalid credentials (Partner ID or API Key)',
            '1007' => 'Failed. System error',
            '1008' => 'No Delivery Report',
            '1009' => 'Unsupported data type',
            '1010' => 'Unsupported request type shortcode',
            '4090' => 'Internal Error. Try again after 5 minutes',
            '4091' => 'No Partner ID is set',
            '4092' => 'No API KEY provided',
            '4093' => 'Details not found',
        ];

        return $errors[$code] ?? $data['message'] ?? $data['responses'][0]['response-description'] ?? 'Failed to send SMS via Celcom.';
    }
}
