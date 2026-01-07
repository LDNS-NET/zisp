<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Radius\Radacct;
use App\Models\Tenants\TenantActiveSession;
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
        // PPPoE: 5 minutes
        // Hotspot: 10 minutes
        // For simplicity, we'll use a safe 24 hour threshold for all for now, 
        // or we could query based on acctsessionid prefix or other indicators if available.
        $threshold = now()->subHours(24);

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
            // We search by session_id (if we have it) or by router/user/ip
            // Since we might not have the exact mapping here easily without router lookup,
            // we'll rely on the SyncOnlineUsers job to clean up the local cache on its next run
            // OR we can try to find it via session_id if we stored it.
            
            if ($session->acctsessionid) {
                TenantActiveSession::where('session_id', $session->acctsessionid)
                    ->update(['status' => 'disconnected', 'last_seen_at' => now()]);
            }

            $count++;
        }

        $this->info("Closed {$count} stale sessions.");
    }
}
