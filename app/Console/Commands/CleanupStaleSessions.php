<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Radius\Radacct;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupStaleSessions extends Command
{
    protected $signature = 'app:cleanup-stale-sessions';
    protected $description = 'Close stale RADIUS sessions and update local cache';

    public function handle()
    {
        $this->info('Starting stale session cleanup...');
        
        // Thresholds
        // Mark sessions as stale if they haven't sent an update in 30 minutes
        // This is aggressive enough to catch dead sessions but lenient enough
        // to avoid false positives from routers with infrequent interim updates
        $threshold = now()->subMinutes(30);

        // 1. Find stale RADIUS sessions
        // acctstoptime IS NULL AND acctupdatetime < threshold
        $staleSessions = Radacct::whereNull('acctstoptime')
            ->where('acctupdatetime', '<', $threshold)
            ->get();

        $count = 0;
        foreach ($staleSessions as $session) {
            // Close the session in RADIUS
            $session->acctstoptime = now();
            $session->acctterminatecause = 'Stale-Session'; // Custom cause
            $session->save();

            // Also mark as disconnected in local cache if exists
            if ($session->acctsessionid) {
                \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
                    ->where('session_id', $session->acctsessionid)
                    ->update([
                        'status' => 'disconnected', 
                        'last_seen_at' => now(),
                        'disconnected_at' => now(),
                    ]);
            }

            $count++;
        }

        $this->info("Closed {$count} stale RADIUS sessions.");

        // 2. Also cleanup TenantActiveUsers directly (in case RADIUS didn't send Stop)
        $staleLocalSessions = \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('last_seen_at', '<', $threshold)
            ->get();

        $localCount = 0;
        foreach ($staleLocalSessions as $session) {
            $session->update([
                'status' => 'disconnected',
                'last_seen_at' => now(),
                'disconnected_at' => now(),
            ]);

            // Also update user's online flag if they have no other active sessions
            if ($session->user_id) {
                $user = \App\Models\Tenants\NetworkUser::withoutGlobalScopes()->find($session->user_id);
                if ($user) {
                    $activeCount = \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->count();
                    
                    if ($activeCount === 0) {
                        $user->update(['online' => false]);
                    }
                }
            }

            $localCount++;
        }

        $this->info("Closed {$localCount} stale local sessions.");
        $this->info("Total cleanup: {$count} RADIUS + {$localCount} local = " . ($count + $localCount) . " sessions.");
    }
}
