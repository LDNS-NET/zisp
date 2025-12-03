<?php

namespace App\Console\Commands;

use App\Models\Radius\Radreply;
use Illuminate\Console\Command;

class RemoveSessionTimeout extends Command
{
    protected $signature = 'users:remove-session-timeout 
                            {--dry-run : Run without making changes}';

    protected $description = 'Remove Session-Timeout attribute from all users to prevent disruptive re-authentications';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('Starting Session-Timeout removal...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Count existing Session-Timeout attributes
        $count = Radreply::where('attribute', 'Session-Timeout')->count();

        if ($count === 0) {
            $this->info('No Session-Timeout attributes found.');
            return 0;
        }

        $this->info("Found {$count} users with Session-Timeout attribute.");

        if (!$dryRun) {
            $deleted = Radreply::where('attribute', 'Session-Timeout')->delete();
            $this->info("Successfully removed {$deleted} Session-Timeout attributes.");
        } else {
            $this->info("Would remove {$count} attributes.");
        }

        $this->newLine();
        $this->info('✓ Cleanup completed!');

        return 0;
    }
}
