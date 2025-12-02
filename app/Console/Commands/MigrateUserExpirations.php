<?php

namespace App\Console\Commands;

use App\Models\Tenants\NetworkUser;
use App\Models\Radius\Radcheck;
use App\Models\Radius\Radreply;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateUserExpirations extends Command
{
    protected $signature = 'users:migrate-expirations 
                            {--dry-run : Run without making changes}
                            {--batch= : Process users in batches (default: 100)}';

    protected $description = 'Migrate existing users to use RADIUS Expiration and Session-Timeout attributes';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) ($this->option('batch') ?? 100);

        $this->info('Starting user expiration migration...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get all network users
        $totalUsers = NetworkUser::count();
        $this->info("Found {$totalUsers} total users");

        $processedCount = 0;
        $expirationAddedCount = 0;
        $sessionTimeoutAddedCount = 0;
        $errorCount = 0;

        // Process users in batches to avoid memory issues
        NetworkUser::chunk($batchSize, function ($users) use ($dryRun, &$processedCount, &$expirationAddedCount, &$sessionTimeoutAddedCount, &$errorCount) {
            foreach ($users as $user) {
                try {
                    $processedCount++;

                    // Add Expiration attribute if user has expires_at
                    if ($user->expires_at) {
                        $expirationExists = Radcheck::where('username', $user->username)
                            ->where('attribute', 'Expiration')
                            ->exists();

                        if (!$expirationExists) {
                            if (!$dryRun) {
                                Radcheck::create([
                                    'username' => $user->username,
                                    'attribute' => 'Expiration',
                                    'op' => ':=',
                                    'value' => $user->expires_at->format('d M Y H:i:s'),
                                ]);
                            }
                            $expirationAddedCount++;
                            $this->line("  ✓ Added Expiration for user: {$user->username}");
                        }
                    }

                    // Add Session-Timeout attribute
                    $sessionTimeoutExists = Radreply::where('username', $user->username)
                        ->where('attribute', 'Session-Timeout')
                        ->exists();

                    if (!$sessionTimeoutExists) {
                        if (!$dryRun) {
                            Radreply::create([
                                'username' => $user->username,
                                'attribute' => 'Session-Timeout',
                                'op' => ':=',
                                'value' => '300', // 5 minutes
                            ]);
                        }
                        $sessionTimeoutAddedCount++;
                        $this->line("  ✓ Added Session-Timeout for user: {$user->username}");
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("  ✗ Error processing user {$user->username}: {$e->getMessage()}");
                }
            }

            // Show progress
            $this->info("Processed {$processedCount} users...");
        });

        // Summary
        $this->newLine();
        $this->info('=== Migration Summary ===');
        $this->info("Total users processed: {$processedCount}");
        $this->info("Expiration attributes added: {$expirationAddedCount}");
        $this->info("Session-Timeout attributes added: {$sessionTimeoutAddedCount}");

        if ($errorCount > 0) {
            $this->error("Errors encountered: {$errorCount}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN COMPLETED - No changes were made');
            $this->info('Run without --dry-run to apply changes');
        } else {
            $this->newLine();
            $this->info('✓ Migration completed successfully!');
        }

        return 0;
    }
}
