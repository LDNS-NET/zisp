<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KopokopoService
{
    protected string $clientId;
    protected string $clientSecret;
    protected ?string $tillNumber;
    protected string $baseUrl;
    protected ?string $callbackUrl;

    public function __construct()
    {
        $config = config('services.kopokopo', []);

        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->tillNumber = $config['till'] ?? null;
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://sandbox.kopokopo.com', '/');
        $this->callbackUrl = $config['callback_url'] ?? null;
    }

    /**
     * Optionally override credentials at runtime.
     */
    public function setCredentials(array $credentials = []): void
    {
        $this->clientId = $credentials['client_id'] ?? $this->clientId;
        $this->clientSecret = $credentials['client_secret'] ?? $this->clientSecret;
        $this->tillNumber = $credentials['till'] ?? $this->tillNumber;
        $this->baseUrl = rtrim($credentials['base_url'] ?? $this->baseUrl, '/');
        $this->callbackUrl = $credentials['callback_url'] ?? $this->callbackUrl;
    }

    /**
     * Initiate an M-Pesa STK Push collection via KopoKopo.
     *
     * @param string $phone E.164 MSISDN (e.g. 2547XXXXXXXX)
     * @param float  $amount
     * @param string $currency
     * @param string $reference Local reference / receipt
     * @param string $description Description shown to the customer
     * @param array  $metadata Extra metadata to send to KopoKopo
     * @return array
     */
    public function initiateStkPush(
        string $phone,
        float $amount,
        string $currency,
        string $reference,
        string $description,
        array $metadata = []
    ): array {
        if (!$this->clientId || !$this->clientSecret || !$this->tillNumber) {
            return [
                'success' => false,
                'message' => 'KopoKopo credentials are missing (client_id, client_secret, or till).',
            ];
        }

        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Failed to obtain KopoKopo access token.',
                ];
            }

            $payload = [
                'payment_channel' => 'M-PESA STK Push',
                'till_number' => $this->tillNumber,
                'subscriber' => [
                    'phone_number' => $phone,
                ],
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'metadata' => array_merge($metadata, [
                    'reference' => $reference,
                ]),
            ];

            if ($this->callbackUrl) {
                $payload['callback_url'] = $this->callbackUrl;
            }

            Log::info('KopoKopo: initiating STK Push', ['payload' => $payload]);

            $response = Http::withToken($token)
                ->acceptJson()
                ->post($this->baseUrl . '/api/v1/incoming_payments', $payload);

            $data = $response->json();

            Log::info('KopoKopo: STK Push response', [
                'status' => $response->status(),
                'data' => $data,
            ]);

            if ($response->successful()) {
                // KopoKopo typically returns a location or resource ID representing the payment request
                $requestId = $data['data']['id'] ?? $data['id'] ?? null;

                return [
                    'success' => true,
                    'message' => 'Payment request sent. Please check your phone.',
                    'request_id' => $requestId,
                    'response' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['error'] ?? $data['message'] ?? 'KopoKopo payment initiation failed.',
                'response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('KopoKopo: exception during STK Push', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'KopoKopo Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch an OAuth access token from KopoKopo.
     */
    protected function getAccessToken(): ?string
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/oauth/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            $data = $response->json();

            Log::info('KopoKopo: token response', [
                'status' => $response->status(),
                'data' => $data,
            ]);

            if ($response->successful() && !empty($data['access_token'])) {
                return $data['access_token'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('KopoKopo: token exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

