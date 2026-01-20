<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Services\PredictiveAnalyticsService;

class GeneratePredictions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:predict {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate churn and revenue predictions for tenants';

    /**
     * Execute the console command.
     */
    public function handle(PredictiveAnalyticsService $service)
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $this->processTenant($tenantId, $service);
        } else {
            $tenants = Tenant::all();
            foreach ($tenants as $tenant) {
                $this->processTenant($tenant->id, $service);
            }
        }

        $this->info('Predictions generated successfully!');
    }

    protected function processTenant($tenantId, $service)
    {
        $this->info("Processing predictions for tenant: {$tenantId}");
        
        // 1. Predict Churn
        $churnResults = $service->predictChurn($tenantId);
        $this->line("- Churn predictions: " . count($churnResults));

        // 2. Forecast Revenue
        $revenueForecast = $service->forecastRevenue($tenantId);
        if ($revenueForecast) {
            $this->line("- Revenue forecast generated");
        } else {
            $this->warn("- Not enough data for revenue forecast");
        }
    }
}
