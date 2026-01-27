<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\QuickBooksService;
use Illuminate\Support\Facades\Log;

class SyncQuickBooksData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quickbooks:sync {--tenant= : Sync data for a specific tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync finance data (invoices, payments, expenses, and equipment) to QuickBooks Online every 30 minutes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $this->syncForTenant($tenantId);
        } else {
            $this->syncForAllTenants();
        }

        $this->info('QuickBooks synchronization completed.');
    }

    /**
     * Sync data for all tenants with QuickBooks connected.
     */
    protected function syncForAllTenants()
    {
        $settings = TenantSetting::where('category', 'quickbooks')->get();

        foreach ($settings as $setting) {
            $this->syncForTenant($setting->tenant_id);
        }
    }

    /**
     * Sync data for a specific tenant.
     */
    protected function syncForTenant($tenantId)
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found.");
            return;
        }

        $this->info("Syncing data for tenant: {$tenant->name} ({$tenantId})");

        try {
            // Initialize tenancy to set global scopes and tenant context
            tenancy()->initialize($tenant);

            $service = new QuickBooksService($tenantId);
            
            if (!$service->getDataServiceForTenant()) {
                $this->warn("QuickBooks not connected for tenant {$tenantId}.");
                return;
            }

            $this->info("Syncing Equipment...");
            $service->syncEquipment();

            $this->info("Syncing Invoices...");
            $service->syncInvoices();

            $this->info("Syncing Payments...");
            $service->syncPayments();

            $this->info("Syncing Expenses...");
            $service->syncExpenses();

            $this->info("Done for tenant {$tenantId}.");

            // End tenancy session
            tenancy()->end();

        } catch (\Exception $e) {
            // Ensure tenancy is ended even on failure
            if (tenant()) {
                tenancy()->end();
            }
            $this->error("Failed to sync data for tenant {$tenantId}: " . $e->getMessage());
            Log::error("QuickBooks Sync Command Failed for Tenant {$tenantId}: " . $e->getMessage());
        }
    }
}
