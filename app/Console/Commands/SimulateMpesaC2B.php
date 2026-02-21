<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MpesaService;
use App\Models\TenantPaymentGateway;

class SimulateMpesaC2B extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mpesa:simulate-c2b 
                            {--tenant= : Tenant ID to use for simulation (optional)}
                            {--shortcode= : M-Pesa Shortcode (optional)}
                            {--amount=1 : Amount to simulate (default: 1)}
                            {--msisdn=254708374149 : Test phone number (default: Daraja test MSISDN)}
                            {--bill-ref=TestRef : Bill reference / Account number}
                            {--command-id=CustomerPayBillOnline : CustomerPayBillOnline or CustomerBuyGoodsOnline}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate an M-Pesa C2B transaction (Sandbox only)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Starting M-Pesa C2B Simulation...');
        $this->newLine();

        $tenantId = $this->option('tenant');
        $amount = $this->option('amount');
        $msisdn = $this->option('msisdn');
        $billRef = $this->option('bill-ref');
        $commandId = $this->option('command-id');

        // Get credentials
        $credentials = $this->getCredentials($tenantId);
        
        if (!$credentials) {
            $this->error('❌ Failed to retrieve M-Pesa credentials.');
            return Command::FAILURE;
        }

        if (($credentials['environment'] ?? '') === 'production') {
            $this->error('❌ Simulation is NOT supported in production!');
            return Command::FAILURE;
        }

        $this->info('📋 Simulation Details:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Shortcode', $credentials['shortcode'] ?? 'N/A'],
                ['Amount', $amount],
                ['MSISDN', $msisdn],
                ['Bill Ref', $billRef],
                ['Command ID', $commandId],
            ]
        );
        $this->newLine();

        // Initialize M-Pesa service
        $mpesa = new MpesaService($credentials);

        // Run simulation
        $this->info('📡 Sending simulation request to Safaricom...');
        $result = $mpesa->simulateC2B($amount, $msisdn, $billRef, $commandId);

        if ($result['success']) {
            $this->info('✅ Simulation request accepted!');
            $this->newLine();
            $this->info('📝 Response: ' . ($result['message'] ?? 'Success'));
            
            if (isset($result['response'])) {
                $this->newLine();
                $this->info('🔍 Full Response:');
                $this->line(json_encode($result['response'], JSON_PRETTY_PRINT));
            }

            $this->newLine();
            $this->info('💡 Note: Check your Laravel logs and database to see if the callback was processed.');
            
            return Command::SUCCESS;
        } else {
            $this->error('❌ Simulation failed!');
            $this->error('Error: ' . ($result['message'] ?? 'Unknown error'));
            
            if (isset($result['response'])) {
                $this->newLine();
                $this->info('🔍 Full Response:');
                $this->line(json_encode($result['response'], JSON_PRETTY_PRINT));
            }

            return Command::FAILURE;
        }
    }

    /**
     * Get M-Pesa credentials from options, tenant or config
     */
    private function getCredentials(?string $tenantId): ?array
    {
        // Priority 1: Direct options
        if ($this->option('shortcode')) {
            return [
                'consumer_key' => config('mpesa.consumer_key'),
                'consumer_secret' => config('mpesa.consumer_secret'),
                'shortcode' => $this->option('shortcode'),
                'environment' => config('mpesa.environment', 'sandbox'),
            ];
        }

        // Priority 2: Tenant
        if ($tenantId) {
            $gateway = TenantPaymentGateway::where('tenant_id', $tenantId)
                ->where('provider', 'mpesa')
                ->where('is_active', true)
                ->first();

            if ($gateway) {
                return [
                    'consumer_key' => $gateway->mpesa_consumer_key,
                    'consumer_secret' => $gateway->mpesa_consumer_secret,
                    'shortcode' => $gateway->mpesa_shortcode,
                    'passkey' => $gateway->mpesa_passkey,
                    'environment' => $gateway->mpesa_env ?? 'sandbox',
                ];
            }
        }

        // Priority 3: Default config
        return [
            'consumer_key' => config('mpesa.consumer_key'),
            'consumer_secret' => config('mpesa.consumer_secret'),
            'shortcode' => config('mpesa.shortcode'),
            'passkey' => config('mpesa.passkey'),
            'environment' => config('mpesa.environment', 'sandbox'),
        ];
    }
}
