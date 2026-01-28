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
            Log::error('Africa\'s Talking: Missing credentials', [
                'has_username' => !empty($this->username),
                'has_api_key' => !empty($this->apiKey),
            ]);
            return [
                'success' => false,
                'message' => 'Africa\'s Talking SMS credentials not properly configured.'
            ];
        }
        
        Log::info('Africa\'s Talking: Attempting to send SMS', [
            'username' => $this->username,
            'phone_number' => $phoneNumber,
            'sender_id' => $this->senderId,
            'api_key_length' => strlen($this->apiKey ?? ''),
            'endpoint' => $this->endpoint,
        ]);
        
        $requestData = [
            'username' => $this->username,
            'to' => $phoneNumber,
            'message' => $message,
        ];
        
        // Only add 'from' if sender_id is provided
        if ($this->senderId) {
            $requestData['from'] = $this->senderId;
        }
        
        Log::info('Africa\'s Talking: Request details', [
            'headers' => [
                'apiKey' => substr($this->apiKey, 0, 10) . '...' . substr($this->apiKey, -5),
                'Accept' => 'application/json',
            ],
            'request_data' => $requestData,
        ]);
        
        try {
            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])->asForm()->post($this->endpoint, $requestData);
            
            $data = $response->json();
            $statusCode = $response->status();
            
            Log::info('Africa\'s Talking: Received response', [
                'http_status' => $statusCode,
                'response_body' => $data,
            ]);
            
            // Africa's Talking returns SMSMessageData with Recipients array
            if ($response->successful() && isset($data['SMSMessageData']['Recipients'])) {
                $recipients = $data['SMSMessageData']['Recipients'];
                
                // Check if first recipient was successful (status code 100-102)
                if (!empty($recipients) && isset($recipients[0]['statusCode'])) {
                    $recipientStatusCode = $recipients[0]['statusCode'];
                    $recipientStatus = $recipients[0]['status'] ?? 'Unknown';
                    
                    Log::info('Africa\'s Talking: Recipient status', [
                        'status_code' => $recipientStatusCode,
                        'status' => $recipientStatus,
                        'number' => $recipients[0]['number'] ?? 'Unknown',
                    ]);
                    
                    if (in_array($recipientStatusCode, [100, 101, 102])) {
                        return [
                            'success' => true,
                            'message' => 'SMS sent successfully via Africa\'s Talking.',
                            'provider_response' => $data
                        ];
                    }
                    
                    // Log failure with status code
                    Log::warning('Africa\'s Talking: Message not accepted', [
                        'status_code' => $recipientStatusCode,
                        'status' => $recipientStatus,
                        'cost' => $recipients[0]['cost'] ?? 'Unknown',
                    ]);
                }
                
                $errorMessage = $data['SMSMessageData']['Message'] ?? 'Failed to send SMS.';
                Log::error('Africa\'s Talking: Send failed', [
                    'error_message' => $errorMessage,
                    'recipients' => $recipients,
                ]);
                
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'provider_response' => $data
                ];
            }
            
            // Log if response structure is unexpected
            Log::error('Africa\'s Talking: Unexpected response structure', [
                'http_status' => $statusCode,
                'response_body' => $data,
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send SMS via Africa\'s Talking. Unexpected response format.',
                'provider_response' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('Africa\'s Talking SMS failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
}
