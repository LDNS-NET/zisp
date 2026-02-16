<?php

namespace App\Jobs;

use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikUserSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMikrotikActiveUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $router;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantMikrotik $router)
    {
        $this->router = $router;
    }

    /**
     * Execute the job.
     */
    public function handle(MikrotikUserSyncService $syncService): void
    {
        try {
            Log::info("Starting immediate active user sync for router {$this->router->id}");
            
            $result = $syncService->syncActiveUsers($this->router);
            
            Log::info("Immediate active user sync completed for router {$this->router->id}", [
                'synced' => $result['synced'],
                'online' => count($result['online']),
                'offline' => count($result['offline']),
            ]);
        } catch (\Exception $e) {
            Log::error("Immediate active user sync failed for router {$this->router->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
