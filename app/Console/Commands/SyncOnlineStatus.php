<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantActiveUsers;
use App\Models\Tenants\NetworkUser;
use Illuminate\Support\Facades\DB;

class SyncOnlineStatus extends Command
{
    protected $signature = 'app:sync-online-status';
    protected $description = 'Sync NetworkUser.online flag from tenant_active_users (only changed users)';

    public function handle()
    {
        // Only get sessions updated in last 5 minutes (skip older sessions)
        $recentTimestamp = now()->subMinutes(5);
        
        $sessions = TenantActiveUsers::withoutGlobalScopes()
            ->whereNotNull('username')
            ->where('updated_at', '>=', $recentTimestamp)
            ->get(['tenant_id', 'username', 'status']);

        if ($sessions->isEmpty()) {
            $this->info('No recent session changes detected, skipping sync.');
            return 0;
        }

        $sessionsByTenant = $sessions->groupBy('tenant_id');
        $totalUpdates = 0;

        foreach ($sessionsByTenant as $tenantId => $tenantSessions) {
            $activeUsernames = [];
            $inactiveUsernames = [];

            foreach ($tenantSessions as $session) {
                $username = strtolower(trim($session->username));
                if (strtolower(trim($session->status)) === 'active') {
                    $activeUsernames[] = $username;
                } else {
                    $inactiveUsernames[] = $username;
                }
            }

            // Only update users whose status changed
            if (!empty($activeUsernames)) {
                $updated = NetworkUser::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn(DB::raw('lower(trim(username))'), $activeUsernames)
                    ->where('online', false)
                    ->update(['online' => true]);
                $totalUpdates += $updated;
            }

            if (!empty($inactiveUsernames)) {
                $updated = NetworkUser::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn(DB::raw('lower(trim(username))'), $inactiveUsernames)
                    ->where('online', true)
                    ->update(['online' => false]);
                $totalUpdates += $updated;
            }
        }

        $this->info("Online status sync complete. Updated $totalUpdates users.");
        return 0;
    }
}
