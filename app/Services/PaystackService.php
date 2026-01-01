<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected $secretKey;
    protected $baseUrl = 'https://api.paystack.co';

    public function __construct($secretKey = null)
    {
        $this->secretKey = $secretKey ?: config('services.paystack.secret_key');
    }

    /**
     * Initialize a transaction.
     */
    public function initializeTransaction(array $data)
    {
        if (!$this->secretKey) {
            Log::error('Paystack: Secret key is missing');
            return null;
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/transaction/initialize", $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack: Failed to initialize transaction', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack: Initialization exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verify a transaction.
     */
    public function verifyTransaction($reference)
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Paystack: Failed to verify transaction', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack: Verification exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
