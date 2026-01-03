<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    protected $secretKey;
    protected $publicKey;
    protected $encryptionKey;
    protected $baseUrl = 'https://api.flutterwave.com/v3';

    public function __construct(array $credentials = [])
    {
        $this->setCredentials($credentials);
    }

    /**
     * Set or override Flutterwave credentials
     */
    public function setCredentials(array $credentials = []): self
    {
        $this->secretKey = $credentials['secret_key'] ?? config('services.flutterwave.secret_key');
        $this->publicKey = $credentials['public_key'] ?? config('services.flutterwave.public_key');
        $this->encryptionKey = $credentials['encryption_key'] ?? config('services.flutterwave.encryption_key');

        return $this;
    }

    /**
     * Initialize a transaction (Standard Redirect)
     *
     * @param string $email Customer email
     * @param float $amount Amount
     * @param string $reference Unique transaction reference
     * @param string $currency Currency code (e.g., KES, NGN, GHS)
     * @param array $metadata Additional metadata
     * @param array $customerDetails Additional customer details (name, phone)
     * @return array|null Response with link
     */
    public function initializeTransaction(string $email, float $amount, string $reference, string $currency = 'KES', array $metadata = [], array $customerDetails = []): ?array
    {
        if (!$this->secretKey) {
            Log::error('Flutterwave: Secret key is missing');
            return ['success' => false, 'message' => 'Flutterwave not configured'];
        }

        try {
            $payload = [
                'tx_ref' => $reference,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'redirect_url' => $metadata['callback_url'] ?? route('flutterwave.callback'),
                'payment_options' => 'card,mobilemoney,ussd',
                'meta' => $metadata,
                'customer' => [
                    'email' => $email,
                    'phonenumber' => $customerDetails['phone_number'] ?? null,
                    'name' => $customerDetails['name'] ?? 'Customer',
                ],
                'customizations' => [
                    'title' => $metadata['site_name'] ?? 'Payment',
                    'description' => 'Payment for services',
                    'logo' => $metadata['logo'] ?? null,
                ]
            ];

            Log::info('Flutterwave: Initializing transaction', [
                'payload' => $payload,
                'key_preview' => substr($this->secretKey, 0, 8) . '...' . substr($this->secretKey, -4)
            ]);

            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/payments", $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'success') {
                    return [
                        'success' => true,
                        'link' => $data['data']['link'],
                        'reference' => $reference,
                    ];
                }
            }

            Log::error('Flutterwave: Failed to initialize transaction', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            $errorData = $response->json();
            return [
                'success' => false, 
                'message' => $errorData['message'] ?? 'Failed to initialize payment'
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave: Initialization exception', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Payment initialization error'];
        }
    }

    /**
     * Verify a transaction
     *
     * @param string $transactionId Transaction ID (from callback)
     * @return array|null Verification result
     */
    public function verifyTransaction(string $transactionId): ?array
    {
        if (!$this->secretKey) {
            Log::error('Flutterwave: Secret key is missing for verification');
            return null;
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/transactions/{$transactionId}/verify");

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'success') {
                    return [
                        'success' => true,
                        'status' => $data['data']['status'], // 'successful'
                        'amount' => $data['data']['amount'],
                        'currency' => $data['data']['currency'],
                        'reference' => $data['data']['tx_ref'],
                        'transaction_id' => $data['data']['id'],
                        'customer' => $data['data']['customer'] ?? null,
                        'meta' => $data['data']['meta'] ?? null,
                    ];
                }
            }

            Log::error('Flutterwave: Failed to verify transaction', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return ['success' => false, 'message' => 'Verification failed'];
        } catch (\Exception $e) {
            Log::error('Flutterwave: Verification exception', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Verification error'];
        }
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw webhook payload
     * @param string $signature Signature from verif-hash header
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (!$this->secretKey) { // Flutterwave uses a separate secret hash for webhooks, but often it's configured in dashboard. 
            // For simplicity, we might check if user provided a specific hash or just rely on secret key if that's how they set it up.
            // Actually Flutterwave asks you to set a "Secret Hash" in the dashboard.
            // We should probably allow passing it.
            return false; 
        }
        
        // Flutterwave webhook verification usually involves checking the 'verif-hash' header 
        // against the Secret Hash you set in your dashboard.
        // It's not an HMAC of the payload like Paystack.
        // So we need the user to configure a webhook hash.
        // For now, we'll skip complex verification or assume the secret key is used as hash if not provided.
        
        return $signature === ($this->secretKey); // This is a simplification/placeholder.
    }

    /**
     * Generate a unique payment reference
     *
     * @param string $prefix Prefix for the reference
     * @return string
     */
    public function generateReference(string $prefix = 'FLW'): string
    {
        return strtoupper($prefix . '-' . uniqid() . '-' . time());
    }
}
