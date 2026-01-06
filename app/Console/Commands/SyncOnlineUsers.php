<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantActiveSession;
use Illuminate\Support\Facades\Log;

class SyncOnlineUsers extends Command
{
    protected $signature = 'app:sync-online-users';
    protected $description = 'Cleanup stale online sessions and sync user online status';

    public function handle()
    {
        $this->info('Starting online sessions cleanup...');
        $totalSessions = TenantActiveSession::withoutGlobalScopes()->count();
        $this->info("Total sessions in database: $totalSessions");
        
        // 1. Mark stale sessions as disconnected
        // If we haven't heard from a session in 15 minutes (no interim update), it's likely gone.
        $staleThreshold = now()->subMinutes(15);
        
        $affected = TenantActiveSession::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('last_seen_at', '<', $staleThreshold)
            ->update([
                'status' => 'disconnected',
                'last_seen_at' => now(),
            ]);

        if ($affected > 0) {
            $this->info("Marked {$affected} stale sessions as disconnected.");
        }

        // 2. Sync NetworkUser 'online' status based on CURRENT active sessions
        // This ensures the online flag in network_users matches the active_sessions table
        
        // Get IDs of all users who have at least one 'active' session
        $activeUserIds = TenantActiveSession::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // Mark users as online/offline
        if (!empty($activeUserIds)) {
            NetworkUser::withoutGlobalScopes()->whereIn('id', $activeUserIds)->update(['online' => true]);
            NetworkUser::withoutGlobalScopes()->whereNotIn('id', $activeUserIds)->update(['online' => false]);
        } else {
            NetworkUser::withoutGlobalScopes()->update(['online' => false]);
        }

        $this->info("Sync complete. Active users: " . count($activeUserIds));
    }
}
