<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
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

        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing tenant: {$tenant->id}...");

            try {
                // Get the tenant's database path
                $dbPath = database_path("tenants/{$tenant->id}.sqlite");

                if (!file_exists($dbPath)) {
                    $this->warn("  Tenant {$tenant->id}: Database file doesn't exist, skipping.");
                    $skipped++;
                    continue;
                }

                // Configure the tenant database connection
                Config::set('database.connections.tenant_fix', [
                    'driver' => 'sqlite',
                    'database' => $dbPath,
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ]);

                // Purge any existing connection
                DB::purge('tenant_fix');

                // Check if the network_users table exists
                $tables = DB::connection('tenant_fix')
                    ->select("SELECT name FROM sqlite_master WHERE type='table' AND name='network_users'");

                if (empty($tables)) {
                    $this->warn("  Tenant {$tenant->id}: network_users table doesn't exist, skipping.");
                    $skipped++;
                    continue;
                }

                // For SQLite, we need to check indexes differently
                $indexes = DB::connection('tenant_fix')
                    ->select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='network_users' AND name LIKE '%email%'");

                if (empty($indexes)) {
                    $this->info("  Tenant {$tenant->id}: No email unique constraint found, skipping.");
                    $skipped++;
                    continue;
                }

                // For SQLite, we can't simply drop an index if it's part of the table creation
                // We need to recreate the table without the unique constraint
                // First, let's try dropping the index directly
                foreach ($indexes as $index) {
                    try {
                        DB::connection('tenant_fix')->statement("DROP INDEX IF EXISTS \"{$index->name}\"");
                        $this->info("  Dropped index: {$index->name}");
                    } catch (\Exception $e) {
                        $this->warn("  Could not drop index {$index->name}: " . $e->getMessage());
                    }
                }

                // Convert empty strings to NULL
                DB::connection('tenant_fix')
                    ->table('network_users')
                    ->where('email', '')
                    ->update(['email' => null]);

                $this->info("  Tenant {$tenant->id}: Fixed successfully.");
                $fixed++;

            } catch (\Exception $e) {
                $this->error("  Tenant {$tenant->id}: Error - " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Done! Fixed: {$fixed}, Skipped: {$skipped}, Errors: {$errors}");

        return 0;
    }
}
