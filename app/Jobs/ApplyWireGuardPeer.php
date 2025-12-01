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
     * Execute the job.
     */
    public function handle(WireGuardService $wgService)
    {
        try {
            // 1. Add to config ONLY (no reload yet)
            // This is fast and safe to do multiple times
            $wgService->applyPeer($this->router, false);

            // 2. Dispatch activation job
            // This job is unique (debounced) so it will only run once even if multiple peers are added
            ActivateWireGuardChanges::dispatch();

        } catch (\Exception $e) {
            Log::error('ApplyWireGuardPeer job failed', ['router_id' => $this->router->id, 'error' => $e->getMessage()]);
        }
    }
}
