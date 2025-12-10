<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GrantTenantPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:grant-tenant-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = config('database.connections.mysql.username');
        $host = 'localhost'; // Assuming localhost, adjust if needed

        $this->info("Granting permissions to user '$username'@'$host' for tenant databases...");

        try {
            \Illuminate\Support\Facades\DB::statement("GRANT ALL PRIVILEGES ON `tenant%`.* TO '$username'@'$host'");
            \Illuminate\Support\Facades\DB::statement("FLUSH PRIVILEGES");
            $this->info('Permissions granted successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to grant permissions: ' . $e->getMessage());
        }
    }
}
