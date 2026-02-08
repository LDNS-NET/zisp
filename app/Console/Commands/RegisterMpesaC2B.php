<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MpesaService;
use App\Models\TenantPaymentGateway;

class RegisterMpesaC2B extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mpesa:register-c2b 
                            {--tenant= : Tenant ID to register C2B URLs for (optional, will use default credentials if not provided)}
                            {--validation-url= : Custom validation URL (optional)}
                            {--confirmation-url= : Custom confirmation URL (optional)}
                            {--response-type=Completed : Response type (Completed or Cancelled)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register C2B validation and confirmation URLs with Safaricom M-Pesa API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting M-Pesa C2B URL Registration...');
        $this->newLine();

        $tenantId = $this->option('tenant');
        $responseType = $this->option('response-type');

        // Get credentials
        $credentials = $this->getCredentials($tenantId);
        
        if (!$credentials) {
            $this->error('❌ Failed to retrieve M-Pesa credentials.');
            return Command::FAILURE;
        }

        // Get URLs
        $validationUrl = $this->option('validation-url') ?? config('app.url') . '/api/mpesa/c2b/validation';
        $confirmationUrl = $this->option('confirmation-url') ?? config('app.url') . '/api/mpesa/c2b/confirmation';

        $this->info('📋 Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Environment', $credentials['environment'] ?? 'N/A'],
                ['Shortcode', $credentials['shortcode'] ?? 'N/A'],
                ['Validation URL', $validationUrl],
                ['Confirmation URL', $confirmationUrl],
                ['Response Type', $responseType],
            ]
        );
        $this->newLine();

        // Confirm before proceeding
        if (!$this->confirm('Do you want to proceed with C2B URL registration?', true)) {
            $this->warn('⚠️  Registration cancelled by user.');
            return Command::SUCCESS;
        }

        // Initialize M-Pesa service
        $mpesa = new MpesaService($credentials);

        // Register C2B URLs
        $this->info('📡 Registering URLs with Safaricom...');
        $result = $mpesa->registerC2BURLS($validationUrl, $confirmationUrl, $responseType);

        $this->newLine();

        if ($result['success']) {
            $this->info('✅ C2B URLs registered successfully!');
            $this->newLine();
            $this->info('📝 Response: ' . ($result['message'] ?? 'Success'));
            
            if (isset($result['response'])) {
                $this->newLine();
                $this->info('🔍 Full Response:');
                $this->line(json_encode($result['response'], JSON_PRETTY_PRINT));
            }

            return Command::SUCCESS;
        } else {
            $this->error('❌ C2B URL registration failed!');
            $this->error('Error: ' . ($result['message'] ?? 'Unknown error'));
            
            if (isset($result['response'])) {
                $this->newLine();
                $this->error('🔍 Full Response:');
                $this->line(json_encode($result['response'], JSON_PRETTY_PRINT));
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get M-Pesa credentials from tenant or config
     */
    private function getCredentials(?string $tenantId): ?array
    {
        if ($tenantId) {
            $this->info("🔍 Retrieving credentials for tenant ID: {$tenantId}");
            
            $gateway = TenantPaymentGateway::where('tenant_id', $tenantId)
                ->where('provider', 'mpesa')
                ->where('is_active', true)
                ->first();

            if (!$gateway) {
                $this->warn('⚠️  No active M-Pesa gateway found for this tenant. Using default credentials...');
                return $this->getDefaultCredentials();
            }

            return [
                'consumer_key' => $gateway->mpesa_consumer_key,
                'consumer_secret' => $gateway->mpesa_consumer_secret,
                'shortcode' => $gateway->mpesa_shortcode,
                'passkey' => $gateway->mpesa_passkey,
                'environment' => $gateway->mpesa_env ?? 'sandbox',
            ];
        }

        return $this->getDefaultCredentials();
    }

    /**
     * Get default M-Pesa credentials from config
     */
    private function getDefaultCredentials(): array
    {
        $this->info('🔍 Using default M-Pesa credentials from config...');
        
        return [
            'consumer_key' => config('mpesa.consumer_key'),
            'consumer_secret' => config('mpesa.consumer_secret'),
            'shortcode' => config('mpesa.shortcode'),
            'passkey' => config('mpesa.passkey'),
            'environment' => config('mpesa.environment', 'sandbox'),
        ];
    }
}
