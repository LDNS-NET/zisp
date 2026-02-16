<?php

namespace App\Observers;

use App\Models\Tenants\TenantActiveUsers;
use App\Models\Tenants\NetworkUser;
use Illuminate\Support\Facades\Log;

class TenantActiveUsersObserver
{
    /**
     * Handle the TenantActiveUsers "saved" event (covers created and updated).
     */
    public function saved(TenantActiveUsers $session): void
    {
        $this->syncOnlineStatus($session);
    }

    /**
     * Handle the TenantActiveUsers "deleted" event.
     */
    public function deleted(TenantActiveUsers $session): void
    {
        $this->syncOnlineStatus($session);
    }

    /**
     * Synchronize the online status of the NetworkUser.
     */
    protected function syncOnlineStatus(TenantActiveUsers $session): void
    {
        if (!$session->user_id) {
            return;
        }

        try {
            // Check if there are any active sessions for this user
            $hasActiveSessions = TenantActiveUsers::withoutGlobalScopes()
                ->where('user_id', $session->user_id)
                ->where('status', 'active')
                ->exists();

            NetworkUser::withoutGlobalScopes()
                ->where('id', $session->user_id)
                ->update(['online' => $hasActiveSessions]);

            Log::debug("Synced online status for NetworkUser {$session->user_id}", [
                'online' => $hasActiveSessions,
                'trigger_session_id' => $session->id,
                'trigger_status' => $session->status,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to sync online status for NetworkUser {$session->user_id}: " . $e->getMessage());
        }
    }
}
