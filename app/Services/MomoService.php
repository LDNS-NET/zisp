<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MomoService
{
    protected $apiUser;
    protected $apiKey;
    protected $subscriptionKey;
    protected $environment;
    protected $baseUrl;
    protected $targetEnvironment;

    public function __construct(array $credentials = [])
    {
        $this->setCredentials($credentials);
    }

    /**
     * Set or override MoMo credentials
     */
    public function setCredentials(array $credentials = []): self
    {
        $this->apiUser = $credentials['api_user'] ?? null;
        $this->apiKey = $credentials['api_key'] ?? null;
        $this->subscriptionKey = $credentials['subscription_key'] ?? null;
        $this->environment = $credentials['environment'] ?? config('momo.environment', 'sandbox');

        // MTN MoMo API Base URLs
        $this->baseUrl = $this->environment === 'production'
            ? 'https://proxy.momoapi.mtn.com'
            : 'https://sandbox.momodeveloper.mtn.com';

        // X-Target-Environment header
        $this->targetEnvironment = $this->environment === 'production'
            ? 'mtnproduction'
            : 'sandbox';

        return $this;
    }

    /**
     * Get OAuth access token
     */
    public function getAccessToken(): ?string
    {
        if (!$this->apiUser || !$this->apiKey || !$this->subscriptionKey) {
            Log::error('MoMo: Missing credentials for token generation');
            return null;
        }

        $cacheKey = 'momo_access_token_' . md5($this->apiUser . $this->apiKey);

        return Cache::remember($cacheKey, 50 * 60, function () {
            try {
                $url = $this->baseUrl . '/collection/token/';
                
                $response = Http::withHeaders([
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                ])->withBasicAuth($this->apiUser, $this->apiKey)
                  ->post($url);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['access_token'] ?? null;
                }

                Log::error('MoMo: Failed to get access token', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('MoMo: Token generation exception', [
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Request to Pay (Collection)
     *
     * @param string $phone Phone number (e.g., 2567XXXXXXXX)
     * @param float $amount Amount to charge
     * @param string $externalId External reference ID
     * @param string $payerMessage Message to the payer
     * @param string $payeeNote Note for the payee
     * @return array Response status and reference ID
     */
    public function requestToPay(string $phone, float $amount, string $externalId, string $currency = null, string $payerMessage = 'Payment', string $payeeNote = 'Hotspot Access'): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Failed to obtain access token'];
            }

            $referenceId = (string) Str::uuid();
            $url = $this->baseUrl . '/collection/v1_0/requesttopay';

            // Resolve currency: In sandbox, it MUST be EUR. In production, use provided or fallback to UGX.
            $resolvedCurrency = $this->environment === 'production' ? ($currency ?: 'UGX') : 'EUR';

            Log::info('MoMo Currency Resolution', [
                'input_currency' => $currency,
                'environment' => $this->environment,
                'resolved_currency' => $resolvedCurrency,
                'phone' => $phone
            ]);

            $payload = [
                'amount' => (string) round($amount, 2),
                'currency' => $resolvedCurrency,
                'externalId' => $externalId,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->normalizePhoneNumber($phone)
                ],
                'payerMessage' => $payerMessage,
                'payeeNote' => $payeeNote
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Reference-Id' => $referenceId,
                'X-Target-Environment' => $this->targetEnvironment,
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if (in_array($response->status(), [200, 201, 202])) {
                return [
                    'success' => true,
                    'message' => 'Request to pay initiated',
                    'reference_id' => $referenceId,
                ];
            }

            Log::error('MoMo: Request to pay failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initiate payment',
                'response' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('MoMo: Request to pay exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get Request to Pay Status
     */
    public function getRequestStatus(string $referenceId): array
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Failed to obtain access token'];
            }

            $url = $this->baseUrl . '/collection/v1_0/requesttopay/' . $referenceId;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'X-Target-Environment' => $this->targetEnvironment,
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'status' => strtolower($data['status'] ?? 'pending'), // SUCCESSFUL, FAILED, PENDING
                    'data' => $data
                ];
            }

            return ['success' => false, 'message' => 'Failed to fetch status'];

        } catch (\Exception $e) {
            Log::error('MoMo: Status query exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Normalize phone number
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[\s\-\+]/', '', $phone);
        
        // Add country code if missing (example for Uganda +256)
        // This should ideally be handled based on the tenant's country
    }

    /**
     * Validate if the request is coming from a trusted MTN MoMo IP address.
     * 
     * @param string $ip
     * @return bool
     */
    public function isValidSourceIp(string $ip): bool
    {
        // In sandbox, allow all IPs for testing convenience
        if ($this->environment === 'sandbox') {
            return true;
        }

        // Trusted MTN MoMo IP ranges (Example ranges, should be verified with MTN documentation)
        // Note: These should ideally be moved to a config file
        $trustedIps = [
            '196.11.240.0/24',
            '196.11.242.0/24',
        ];

        foreach ($trustedIps as $range) {
            if ($this->ipInNetwork($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is in a network range (CIDR)
     */
    private function ipInNetwork($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }
}
