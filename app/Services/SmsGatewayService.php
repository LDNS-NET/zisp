<?php

namespace App\Services;

use App\Models\TenantSmsGateway;
use Illuminate\Support\Facades\Log;

class SmsGatewayService
{
    protected $celcomService;
    protected $talksasaService;
    
    public function __construct(CelcomSmsService $celcomService, TalksasaSmsService $talksasaService)
    {
        $this->celcomService = $celcomService;
        $this->talksasaService = $talksasaService;
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
                // To be implemented
                return [
                    'success' => false,
                    'message' => 'Africa\'s Talking not yet implemented in SmsGatewayService'
                ];
                
            case 'twilio':
                // To be implemented
                return [
                    'success' => false,
                    'message' => 'Twilio not yet implemented in SmsGatewayService'
                ];
                
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
