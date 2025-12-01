<?php

namespace App\Jobs;

use App\Models\Tenants\TenantMikrotik;
use App\Services\WireGuardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ApplyWireGuardPeer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TenantMikrotik $router;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantMikrotik $router)
    {
        $this->router = $router;
        $this->onQueue('wireguard');
    }

    /**
     * Prevent job overlapping to avoid race conditions during sync
     */
    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping($this->router->id))->releaseAfter(60)];
    }

    /**
     * Execute the job.
     */
    public function handle(WireGuardService $wgService)
    {
        try {
            // 1. Apply the specific peer (updates DB status)
            // Note: We use the service to add the peer to config, but we rely on the sync command
            // to do the actual reload to batch updates if multiple jobs run quickly.

            // However, per user request for "immediate reaction", we will trigger the full sync
            // which now handles batching and idempotency.

            Log::channel('wireguard')->info('Processing ApplyWireGuardPeer job', ['router_id' => $this->router->id]);

            // Trigger the full sync command which is now optimized
            \Illuminate\Support\Facades\Artisan::call('wireguard:sync-peers', ['--all' => true]);

            Log::channel('wireguard')->info('Triggered full sync from job');

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('ApplyWireGuardPeer job failed', [
                'router_id' => $this->router->id,
                'error' => $e->getMessage()
            ]);
            // Release job back to queue to retry
            $this->release(30);
        }
    }
}
