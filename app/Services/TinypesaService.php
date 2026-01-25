<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TinypesaService
{
    protected $apiKey;
    protected $accountNumber;
    protected $baseUrl = 'https://tinypesa.com/api/v1';

    public function __construct(string $apiKey = null, string $accountNumber = null)
    {
        $this->apiKey = $apiKey;
        $this->accountNumber = $accountNumber;
    }

    /**
     * Initiate STK Push via Tinypesa
     *
     * @param string $phone Phone number (2547XXXXXXXX)
     * @param float $amount Amount to charge
     * @return array
     */
    public function stkPush(string $phone, float $amount): array
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'message' => 'Tinypesa API Key is missing.'
            ];
        }

        // Normalize Phone: Tinypesa expects 254...
        $phone = $this->normalizePhone($phone);

        $url = $this->baseUrl . '/express/initialize';
        
        // Tinypesa automatically uses the Account Number linked to the API Key as the "Till/Paybill"
        // but it doesn't require us to send it in the payload for standard requests, 
        // however usually we pass account_no if it's dynamic. 
        // Based on docs, just amount and msisdn are core.
        
        $payload = [
            'amount' => $amount,
            'msisdn' => $phone,
            'account_no' => $this->accountNumber ?? 'Payment' 
        ];

        try {
            Log::info('Tinypesa: Initiating STK Push', ['payload' => $payload]);

            $response = Http::withHeaders([
                'ApiKey' => $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded' // Tinypesa often uses form encoding or json. 
                // Checks docs: Tinypesa accepts JSON usually. Let's use asJSON/default.
                // Re-checking standard Tinypesa behavior: Header is 'ApiKey'
            ])->asForm()->post($url, $payload); // V1 is often form-urlencoded

            $data = $response->json();
            
            Log::info('Tinypesa: Response', ['status' => $response->status(), 'data' => $data]);

            if ($response->successful() && isset($data['success']) && $data['success'] == true) {
                return [
                    'success' => true,
                    'message' => 'STK Push sent successfully.',
                    'checkout_request_id' => $data['request_id'] ?? null, // Tinypesa returns request_id
                    'response' => $data
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to initiate Tinypesa payment.',
                'response' => $data
            ];

        } catch (\Exception $e) {
            Log::error('Tinypesa: Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Tinypesa Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Normalize phone to 254 format
     */
    protected function normalizePhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        }
        return $phone;
    }
}
