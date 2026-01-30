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
        // Get active gateway configuration
        $gateway = TenantSmsGateway::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
        
        // Default to Talksasa with system credentials if no gateway configured
        if (!$gateway) {
            return $this->sendViaTalksasaDefault($phoneNumber, $message);
        }
        
        $provider = $gateway->provider;
        
        switch ($provider) {
            case 'celcom':
                return $this->sendViaCelcom($gateway, $phoneNumber, $message);
                
            case 'talksasa':
                return $this->sendViaTalksasa($gateway, $phoneNumber, $message);
                
            case 'africastalking':
                return $this->sendViaAfricasTalking($gateway, $phoneNumber, $message);
                
            case 'twilio':
                return $this->sendViaTwilio($gateway, $phoneNumber, $message);
                
            case 'advanta':
                return $this->sendViaAdvanta($gateway, $phoneNumber, $message);
                
            case 'bulksms':
                return $this->sendViaBulkSMS($gateway, $phoneNumber, $message);
                
            case 'clicksend':
                return $this->sendViaClickSend($gateway, $phoneNumber, $message);
                
            case 'infobip':
                return $this->sendViaInfobip($gateway, $phoneNumber, $message);
                
            default:
                return [
                    'success' => false,
                    'message' => "Unsupported SMS provider: {$provider}"
                ];
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
            return $this->sendViaTalksasaDefault($phoneNumber, $message);
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
    protected function sendViaTalksasaDefault(string $phoneNumber, string $message): array
    {
        $this->talksasaService->setCredentials([
            'api_key' => env('TALKSASA_API_KEY'),
            'sender_id' => env('TALKSASA_SENDER_ID'),
        ]);
        
        return $this->talksasaService->sendSMS($phoneNumber, $message);
    }
}
