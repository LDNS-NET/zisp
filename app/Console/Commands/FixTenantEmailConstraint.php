<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Tenant;

class FixTenantEmailConstraint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:fix-email-constraint {--tenant= : Specific tenant ID to fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove unique constraint on email column in network_users table for all tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificTenant = $this->option('tenant');

        if ($specificTenant) {
            $tenants = Tenant::where('id', $specificTenant)->get();
        } else {
            $tenants = Tenant::all();
        }

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');
            return 1;
        }

        $this->info("Processing {$tenants->count()} tenant(s)...");
        $bar = $this->output->createProgressBar($tenants->count());

        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, &$fixed, &$skipped) {
                    // Check if the network_users table exists
                    if (!Schema::hasTable('network_users')) {
                        $this->line(" Tenant {$tenant->id}: network_users table doesn't exist, skipping.");
                        $skipped++;
                        return;
                    }

                    // Check if the unique index exists
                    $indexes = DB::select("SHOW INDEX FROM network_users WHERE Key_name = 'network_users_email_unique'");

                    if (empty($indexes)) {
                        $this->line(" Tenant {$tenant->id}: email unique constraint already removed, skipping.");
                        $skipped++;
                        return;
                    }

                    // Drop the unique index
                    Schema::table('network_users', function ($table) {
                        $table->dropUnique(['email']);
                    });

                    // Convert empty strings to NULL
                    DB::table('network_users')
                        ->where('email', '')
                        ->update(['email' => null]);

                    $this->line(" Tenant {$tenant->id}: Fixed successfully.");
                    $fixed++;
                });
            } catch (\Exception $e) {
                $this->error(" Tenant {$tenant->id}: Error - " . $e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done! Fixed: {$fixed}, Skipped: {$skipped}, Errors: {$errors}");

        return 0;
    }
}
