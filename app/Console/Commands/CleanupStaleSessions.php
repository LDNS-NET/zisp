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
        // Only cleanup sessions stale for 60 minutes or more (not 30)
        // Reduces unnecessary writes for large deployments
        $threshold = now()->subHours(1);

        // Find and close stale RADIUS sessions
        $staleCount = Radacct::whereNull('acctstoptime')
            ->where('acctupdatetime', '<', $threshold)
            ->update([
                'acctstoptime' => now(),
                'acctterminatecause' => 'Stale-Session'
            ]);

        // Cleanup stale TenantActiveUsers (no update in 60 min)
        $localStale = \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('last_seen_at', '<', $threshold)
            ->get();

        $localCount = 0;
        foreach ($localStale as $session) {
            $session->update(['status' => 'disconnected', 'disconnected_at' => now()]);
            
            // Sync user online flag if no other active sessions
            if ($session->user_id) {
                $activeCount = \App\Models\Tenants\TenantActiveUsers::withoutGlobalScopes()
                    ->where('user_id', $session->user_id)
                    ->where('status', 'active')
                    ->count();
                
                if ($activeCount === 0) {
                    \App\Models\Tenants\NetworkUser::withoutGlobalScopes()
                        ->where('id', $session->user_id)
                        ->update(['online' => false]);
                }
            }
            $localCount++;
        }

        $this->info("Cleanup complete: $staleCount RADIUS + $localCount local sessions");
        return 0;
    }
}
