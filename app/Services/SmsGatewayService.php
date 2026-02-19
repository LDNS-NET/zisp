<?php

namespace App\Services;

use App\Models\TenantSmsGateway;
use Illuminate\Support\Facades\Log;

class SmsGatewayService
{
    protected $celcomService;
    protected $talksasaService;
    protected $africasTalkingService;
    protected $twilioService;
    protected $advantaService;
    protected $bulkSMSService;
    protected $clickSendService;
    protected $infobipService;
    
    public function __construct(
        CelcomSmsService $celcomService,
        TalksasaSmsService $talksasaService,
        AfricasTalkingSmsService $africasTalkingService,
        TwilioSmsService $twilioService,
        AdvantaSmsService $advantaService,
        BulkSMSService $bulkSMSService,
        ClickSendSmsService $clickSendService,
        InfobipSmsService $infobipService
    ) {
        $this->celcomService = $celcomService;
        $this->talksasaService = $talksasaService;
        $this->africasTalkingService = $africasTalkingService;
        $this->twilioService = $twilioService;
        $this->advantaService = $advantaService;
        $this->bulkSMSService = $bulkSMSService;
        $this->clickSendService = $clickSendService;
        $this->infobipService = $infobipService;
    }
    
    /**
     * Send SMS using the active gateway for the tenant
     * 
     * @param string $tenantId
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSMS(string $tenantId, string $phoneNumber, string $message): array
    {
        Log::info("[SMS] Initiating send for tenant {$tenantId} to {$phoneNumber}");

        // Get active gateway configuration
        $gateway = TenantSmsGateway::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
        
        // Default to Talksasa with system credentials if no gateway configured
        if (!$gateway) {
            Log::info("[SMS] No active gateway for tenant {$tenantId}. Falling back to Talksasa default.");
            $result = $this->sendViaTalksasaDefault($tenantId, $phoneNumber, $message);
            $this->logResult($tenantId, 'talksasa_default', $result);
            return $result;
        }
        
        $provider = $gateway->provider;
        Log::info("[SMS] Using configured provider '{$provider}' for tenant {$tenantId}");
        
        $result = match ($provider) {
            'celcom' => $this->sendViaCelcom($gateway, $phoneNumber, $message),
            'talksasa' => $this->sendViaTalksasa($gateway, $phoneNumber, $message),
            'africastalking' => $this->sendViaAfricasTalking($gateway, $phoneNumber, $message),
            'twilio' => $this->sendViaTwilio($gateway, $phoneNumber, $message),
            'advanta' => $this->sendViaAdvanta($gateway, $phoneNumber, $message),
            'bulksms' => $this->sendViaBulkSMS($gateway, $phoneNumber, $message),
            'clicksend' => $this->sendViaClickSend($gateway, $phoneNumber, $message),
            'infobip' => $this->sendViaInfobip($gateway, $phoneNumber, $message),
            default => [
                'success' => false,
                'message' => "Unsupported SMS provider: {$provider}"
            ],
        };

        $this->logResult($tenantId, $provider, $result);
        return $result;
    }

    /**
     * Helper to log SMS results
     */
    protected function logResult(string $tenantId, string $provider, array $result)
    {
        $status = $result['success'] ? 'SUCCESS' : 'FAILURE';
        $messageId = $result['provider_response']['id'] ?? $result['provider_response']['message_id'] ?? 'N/A';
        Log::info("[SMS] Result for tenant {$tenantId} via {$provider}: {$status} - ID: {$messageId} - " . ($result['message'] ?? 'No message'));
        
        if (!$result['success'] && isset($result['provider_response'])) {
            Log::debug("[SMS] Provider response: " . json_encode($result['provider_response']));
        }
    }
    
    /**
     * Send via Celcom using tenant credentials
     */
    protected function sendViaCelcom(TenantSmsGateway $gateway, string $phoneNumber, string $message): array
    {
        $this->celcomService->setCredentials([
            'partner_id' => $gateway->celcom_partner_id,
            'api_key' => $gateway->celcom_api_key,
            'shortcode' => $gateway->celcom_sender_id,
        ]);
        
        return $this->celcomService->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send via Talksasa using tenant credentials or fall back to system default
     */
    protected function sendViaTalksasa(TenantSmsGateway $gateway, string $phoneNumber, string $message): array
    {
        // Check if tenant has provided their own credentials
        $apiKey = $gateway->talksasa_api_key;
        $senderId = $gateway->talksasa_sender_id;
        
        // Fall back to system default if tenant hasn't provided credentials
        if (empty($apiKey) || empty($senderId)) {
            return $this->sendViaTalksasaDefault($gateway->tenant_id, $phoneNumber, $message);
        }
        
        $this->talksasaService->setCredentials([
            'api_key' => $apiKey,
            'sender_id' => $senderId,
        ]);
        
        return $this->talksasaService->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send via Africa's Talking using tenant credentials
     */
    protected function sendViaAfricasTalking(TenantSmsGateway $gateway, string $phoneNumber, string $message): array
    {
        $this->africasTalkingService->setCredentials([
            'username' => $gateway->africastalking_username,
            'api_key' => $gateway->africastalking_api_key,
            'sender_id' => $gateway->africastalking_sender_id,
            'environment' => $gateway->africastalking_environment ?? 'production',
        ]);
        
        return $this->africasTalkingService->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send via Twilio using tenant credentials
     */
    protected function sendViaTwilio(TenantSmsGateway $gateway, string $phoneNumber, string $message): array
    {
        $this->twilioService->setCredentials([
            'account_sid' => $gateway->twilio_account_sid,
            'auth_token' => $gateway->twilio_auth_token,
            'from_number' => $gateway->twilio_from_number,
        ]);
        
        return $this->twilioService->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send via Advanta using tenant credentials
     */
    protected function sendViaAdvanta(TenantSmsGateway $gateway, string $phoneNumber, string $message): array
    {
        $this->advantaService->setCredentials([
            'partner_id' => $gateway->advanta_partner_id,
            'api_key' => $gateway->advanta_api_key,
            'shortcode' => $gateway->advanta_shortcode,
        ]);
        
        return $this->advantaService->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send via BulkSMS using tenant credentials
     */
    protected function sendViaBulkSMS(TenantSmsGateway $gateway, string $phoneNumber, string $message): array
    {
        $this->bulkSMSService->setCredentials([
            'username' => $gateway->bulksms_username,
            'password' => $gateway->bulksms_password,
        ]);
        
        return $this->bulkSMSService->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send via ClickSend using tenant credentials
     */
    protected function sendViaClickSend(TenantSmsGateway $gateway, string $phoneNumber, string $message): array
    {
        $this->clickSendService->setCredentials([
            'username' => $gateway->clicksend_username,
            'api_key' => $gateway->clicksend_api_key,
        ]);
        
        return $this->clickSendService->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send via Infobip using tenant credentials
     */
    protected function sendViaInfobip(TenantSmsGateway $gateway, string $phoneNumber, string $message): array
    {
        $this->infobipService->setCredentials([
            'api_key' => $gateway->infobip_api_key,
            'base_url' => $gateway->infobip_base_url,
            'sender_id' => $gateway->infobip_sender_id,
        ]);
        
        return $this->infobipService->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send via Talksasa using system default credentials from .env
     */
    protected function sendViaTalksasaDefault(string $tenantId, string $phoneNumber, string $message): array
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        
        if (!$tenant) {
            return [
                'success' => false,
                'message' => "Tenant context not found."
            ];
        }

        // Calculate cost: 0.45 per 40 characters
        $charCount = mb_strlen($message);
        $units = ceil($charCount / 40);
        $cost = $units * 0.45;

        // Check balance
        if ($tenant->sms_balance < $cost) {
            return [
                'success' => false,
                'message' => "Insufficient SMS balance (Cost: {$cost}, Balance: {$tenant->sms_balance}). Please top up."
            ];
        }

        $this->talksasaService->setCredentials([
            'api_key' => env('TALKSASA_API_KEY'),
            'sender_id' => env('TALKSASA_SENDER_ID'),
        ]);
        
        $result = $this->talksasaService->sendSMS($phoneNumber, $message);

        // Deduct balance on success
        if ($result['success']) {
            $tenant->sms_balance -= $cost;
            $tenant->save();
            Log::info("[SMS] Deducted {$cost} from tenant {$tenantId} for SMS to {$phoneNumber}. Remaining balance: {$tenant->sms_balance}");
        }

        return $result;
    }
}
