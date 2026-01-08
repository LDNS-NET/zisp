<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\TenantActiveUsers;
use App\Models\Tenants\NetworkUser;
use Illuminate\Support\Facades\DB;

class SyncOnlineStatus extends Command
{
    protected $signature = 'app:sync-online-status';
    protected $description = 'Sync NetworkUser.online flag from tenant_active_users (real-time)';

    public function handle()
    {
        $this->info('Starting sync of online status from tenant_active_users...');

        // Load all sessions centralised (no tenant scope)
        $sessions = TenantActiveUsers::withoutGlobalScopes()
            ->whereNotNull('username')
            ->get(['tenant_id', 'username', 'status']);

        // Build grouping by tenant
        $sessionsByTenant = $sessions->groupBy('tenant_id');

        // Get list of all tenant IDs that have network users
        $tenantIds = NetworkUser::withoutGlobalScopes()->distinct()->pluck('tenant_id')->filter()->unique()->values();

        foreach ($tenantIds as $tenantId) {
            $this->info("Processing tenant: {$tenantId}");

            $tenantSessions = $sessionsByTenant->get($tenantId, collect());

            $sessionStatuses = [];
            $activeUsernames = [];
            $nonActiveUsernames = [];

            if ($tenantSessions->isNotEmpty()) {
                $grouped = $tenantSessions->groupBy(fn ($s) => strtolower(trim($s->username)));

                foreach ($grouped as $username => $group) {
                    $statuses = $group->pluck('status')->map(fn($st) => strtolower(trim((string)$st)));
                    $final = $statuses->contains('active') ? 'active' : ($statuses->first() ?? 'deactivated');
                    $sessionStatuses[$username] = $final;
                    if ($final === 'active') {
                        $activeUsernames[] = $username;
                    } else {
                        $nonActiveUsernames[] = $username;
                    }
                }
            }

            // Sync DB
            // Mark active users online
            if (!empty($activeUsernames)) {
                NetworkUser::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn(DB::raw('lower(trim(username))'), $activeUsernames)
                    ->where('online', false)
                    ->update(['online' => true]);
            }

            // Users present in sessions but not active -> offline
            if (!empty($nonActiveUsernames)) {
                NetworkUser::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn(DB::raw('lower(trim(username))'), $nonActiveUsernames)
                    ->where('online', true)
                    ->update(['online' => false]);
            }

            // Users with NO session records should be offline
            $sessionUsernames = array_keys($sessionStatuses);
            if (!empty($sessionUsernames)) {
                NetworkUser::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn(DB::raw('lower(trim(username))'), $sessionUsernames)
                    ->where('online', true)
                    ->update(['online' => false]);
            } else {
                // No sessions at all for this tenant => mark all users offline
                NetworkUser::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('online', true)
                    ->update(['online' => false]);
            }

            $this->info("Tenant {$tenantId} sync complete: active=" . count($activeUsernames) . ", non_active=" . count($nonActiveUsernames));
        }

        $this->info('Online status sync finished.');
        return 0;
    }
}
