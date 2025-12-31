<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MpesaService
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $shortcode;
    protected $passkey;
    protected $baseUrl;
    protected $callbackUrl;

    public function __construct()
    {
        $this->consumerKey = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');
        $this->shortcode = config('mpesa.shortcode');
        $this->passkey = config('mpesa.passkey');
        $this->baseUrl = config('mpesa.base_url');
        $this->callbackUrl = config('mpesa.callback_url');
    }

    /**
     * Get OAuth access token from M-Pesa API
     * Tokens are cached for 55 minutes (they expire after 1 hour)
     */
    public function getAccessToken(): ?string
    {
        return Cache::remember('mpesa_access_token', 55 * 60, function () {
            try {
                $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
                
                $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    Log::info('M-Pesa: Access token obtained', ['expires_in' => $data['expires_in'] ?? null]);
                    return $data['access_token'] ?? null;
                }

                Log::error('M-Pesa: Failed to get access token', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('M-Pesa: Token generation exception', [
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Initiate STK Push (Lipa Na M-Pesa Online)
     *
     * @param string $phone Phone number in format 2547XXXXXXXX
     * @param float $amount Amount to charge
     * @param string $reference Account reference (e.g., package_id or invoice number)
     * @param string $description Transaction description
     * @return array Response with success status and data
     */
    public function stkPush(string $phone, float $amount, string $reference, string $description = 'Payment'): array
    {
        try {
            $token = $this->getAccessToken();
            
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Failed to obtain access token'
                ];
            }

            // Normalize phone number
            $phone = $this->normalizePhoneNumber($phone);

            // Generate timestamp and password
            $timestamp = Carbon::now()->format('YmdHis');
            $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

            $url = $this->baseUrl . '/mpesa/stkpush/v1/processrequest';

            $payload = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => round($amount),
                'PartyA' => $phone,
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $this->callbackUrl,
                'AccountReference' => $reference,
                'TransactionDesc' => $description
            ];

            Log::info('M-Pesa: Initiating STK Push', [
                'phone' => $phone,
                'amount' => $amount,
                'reference' => $reference
            ]);

            $response = Http::withToken($token)
                ->post($url, $payload);

            $data = $response->json();

            Log::info('M-Pesa: STK Push response', [
                'status_code' => $response->status(),
                'response' => $data
            ]);

            if ($response->successful() && isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
                return [
                    'success' => true,
                    'message' => $data['CustomerMessage'] ?? 'STK Push sent successfully',
                    'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
                    'merchant_request_id' => $data['MerchantRequestID'] ?? null,
                    'response' => $data
                ];
            }

            return [
                'success' => false,
                'message' => $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'STK Push failed',
                'response' => $data
            ];

        } catch (\Exception $e) {
            Log::error('M-Pesa: STK Push exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Query STK Push transaction status
     *
     * @param string $checkoutRequestId The CheckoutRequestID from STK Push response
     * @return array Response with transaction status
     */
    public function queryTransactionStatus(string $checkoutRequestId): array
    {
        try {
            $token = $this->getAccessToken();
            
            if (!$token) {
                return [
                    'success' => false,
                    'message' => 'Failed to obtain access token'
                ];
            }

            $timestamp = Carbon::now()->format('YmdHis');
            $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

            $url = $this->baseUrl . '/mpesa/stkpushquery/v1/query';

            $payload = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId
            ];

            Log::info('M-Pesa: Querying transaction status', [
                'checkout_request_id' => $checkoutRequestId
            ]);

            $response = Http::withToken($token)
                ->post($url, $payload);

            $data = $response->json();

            Log::info('M-Pesa: Transaction status response', [
                'response' => $data
            ]);

            if ($response->successful() && isset($data['ResponseCode']) && $data['ResponseCode'] == '0') {
                // ResultCode 0 = success, anything else = failed/pending
                $resultCode = $data['ResultCode'] ?? null;
                
                return [
                    'success' => true,
                    'status' => $resultCode == '0' ? 'paid' : ($resultCode == '1032' ? 'cancelled' : 'pending'),
                    'result_code' => $resultCode,
                    'result_desc' => $data['ResultDesc'] ?? null,
                    'response' => $data
                ];
            }

            return [
                'success' => false,
                'message' => $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'Query failed',
                'response' => $data
            ];

        } catch (\Exception $e) {
            Log::error('M-Pesa: Transaction query exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Query error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse and validate M-Pesa callback data
     *
     * @param array $callbackData The callback data from M-Pesa
     * @return array Parsed callback with normalized structure
     */
    public function parseCallback(array $callbackData): array
    {
        try {
            Log::info('M-Pesa: Parsing callback', ['data' => $callbackData]);

            $body = $callbackData['Body'] ?? $callbackData;
            $stkCallback = $body['stkCallback'] ?? [];

            $resultCode = $stkCallback['ResultCode'] ?? null;
            $resultDesc = $stkCallback['ResultDesc'] ?? null;
            $merchantRequestId = $stkCallback['MerchantRequestID'] ?? null;
            $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;

            // Extract callback metadata
            $callbackMetadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
            $metadata = [];
            
            foreach ($callbackMetadata as $item) {
                $name = $item['Name'] ?? null;
                $value = $item['Value'] ?? null;
                if ($name) {
                    $metadata[$name] = $value;
                }
            }

            return [
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'merchant_request_id' => $merchantRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'amount' => $metadata['Amount'] ?? null,
                'mpesa_receipt_number' => $metadata['MpesaReceiptNumber'] ?? null,
                'transaction_date' => $metadata['TransactionDate'] ?? null,
                'phone_number' => $metadata['PhoneNumber'] ?? null,
                'status' => $resultCode == 0 ? 'paid' : ($resultCode == 1032 ? 'cancelled' : 'failed'),
                'raw_data' => $callbackData
            ];

        } catch (\Exception $e) {
            Log::error('M-Pesa: Callback parsing exception', [
                'error' => $e->getMessage(),
                'data' => $callbackData
            ]);

            return [
                'result_code' => -1,
                'result_desc' => 'Parsing error',
                'status' => 'error',
                'raw_data' => $callbackData
            ];
        }
    }

    /**
     * Normalize phone number to M-Pesa format (2547XXXXXXXX)
     *
     * @param string $phone Input phone number
     * @return string Normalized phone number
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        // Remove any spaces, dashes, or plus signs
        $phone = preg_replace('/[\s\-\+]/', '', $phone);

        // If starts with 0, replace with 254
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }

        // If starts with 7 or 1, add 254
        if (preg_match('/^[71]/', $phone)) {
            $phone = '254' . $phone;
        }

        return $phone;
    }
}
