<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected $secretKey;
    protected $publicKey;
    protected $baseUrl = 'https://api.paystack.co';

    public function __construct(array $credentials = [])
    {
        $this->setCredentials($credentials);
    }

    /**
     * Set or override Paystack credentials
     */
    public function setCredentials(array $credentials = []): self
    {
        $this->secretKey = $credentials['secret_key'] ?? config('services.paystack.secret_key');
        $this->publicKey = $credentials['public_key'] ?? config('services.paystack.public_key');

        return $this;
    }

    /**
     * Initialize a transaction
     *
     * @param string $email Customer email
     * @param float $amount Amount in major currency unit (e.g., NGN, not kobo)
     * @param string $reference Unique transaction reference
     * @param string $currency Currency code (e.g., KES, NGN, GHS)
     * @param array $metadata Additional metadata
     * @return array|null Response with access_code and reference
     */
    public function initializeTransaction(string $email, float $amount, string $reference, string $currency = 'KES', array $metadata = []): ?array
    {
        if (!$this->secretKey) {
            Log::error('Paystack: Secret key is missing');
            return ['success' => false, 'message' => 'Paystack not configured'];
        }

        try {
            // Convert amount to kobo/cents (Paystack expects smallest currency unit)
            $amountInKobo = (int)($amount * 100);

            $payload = [
                'email' => $email,
                'amount' => $amountInKobo,
                'reference' => $reference,
                'currency' => strtoupper($currency),
                'metadata' => $metadata,
                'channels' => ['card', 'bank', 'ussd', 'qr', 'mobile_money', 'bank_transfer'],
            ];

            Log::info('Paystack: Initializing transaction', ['payload' => $payload]);

            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/transaction/initialize", $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === true) {
                    return [
                        'success' => true,
                        'access_code' => $data['data']['access_code'],
                        'reference' => $data['data']['reference'],
                        'authorization_url' => $data['data']['authorization_url'],
                    ];
                }
            }

            Log::error('Paystack: Failed to initialize transaction', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return ['success' => false, 'message' => 'Failed to initialize payment'];
        } catch (\Exception $e) {
            Log::error('Paystack: Initialization exception', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Payment initialization error'];
        }
    }

    /**
     * Verify a transaction
     *
     * @param string $reference Transaction reference
     * @return array|null Verification result
     */
    public function verifyTransaction(string $reference): ?array
    {
        if (!$this->secretKey) {
            Log::error('Paystack: Secret key is missing for verification');
            return null;
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === true) {
                    return [
                        'success' => true,
                        'status' => $data['data']['status'], // 'success', 'failed', 'abandoned'
                        'amount' => $data['data']['amount'] / 100, // Convert from kobo to major unit
                        'reference' => $data['data']['reference'],
                        'paid_at' => $data['data']['paid_at'] ?? null,
                        'channel' => $data['data']['channel'] ?? null,
                        'customer' => $data['data']['customer'] ?? null,
                        'metadata' => $data['data']['metadata'] ?? null,
                    ];
                }
            }

            Log::error('Paystack: Failed to verify transaction', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return ['success' => false, 'message' => 'Verification failed'];
        } catch (\Exception $e) {
            Log::error('Paystack: Verification exception', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Verification error'];
        }
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw webhook payload
     * @param string $signature Signature from X-Paystack-Signature header
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (!$this->secretKey) {
            Log::error('Paystack: Secret key is missing for webhook verification');
            return false;
        }

        $computedSignature = hash_hmac('sha512', $payload, $this->secretKey);
        
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Generate a unique payment reference
     *
     * @param string $prefix Prefix for the reference
     * @return string
     */
    public function generateReference(string $prefix = 'PAYSTACK'): string
    {
        return strtoupper($prefix . '-' . uniqid() . '-' . time());
    }

    /**
     * Get public key for frontend integration
     *
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }
}
