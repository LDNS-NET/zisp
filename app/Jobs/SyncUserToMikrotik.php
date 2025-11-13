<?php

namespace App\Jobs;

use App\Models\Tenants\NetworkUser;
use App\Services\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncUserToMikrotik implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $action;
    public NetworkUser $user;

    /**
     * Create a new job instance.
     */
    public function __construct(NetworkUser $user, string $action = 'delete')
    {
        $this->user = $user;
        $this->action = $action;
        $this->onQueue('mikrotik');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $routerHost = config('mikrotik.host', '192.168.88.1');
        $routerUser = config('mikrotik.username', 'admin');
        $routerPass = config('mikrotik.password', 'password');
        $routerPort = (int) config('mikrotik.port', 8728);

        $svc = new MikrotikService($routerHost, $routerUser, $routerPass, $routerPort);
        if (!$svc->isConnected()) {
            \Log::warning('SyncUserToMikrotik: unable to connect to router');
            return;
        }

        $type = $this->user->type ?? 'ppp';
        $identifier = $this->user->mikrotik_id ?? $this->user->username ?? '';

        try {
            if ($this->action === 'delete') {
                $svc->deleteUser($type, $identifier);
            } elseif ($this->action === 'suspend') {
                $svc->suspendUser($type, $identifier);
            } elseif ($this->action === 'unsuspend') {
                $svc->unsuspendUser($type, $identifier);
            }
        } catch (\Throwable $e) {
            \Log::error('SyncUserToMikrotik failed: '.$e->getMessage());
        }
    }
}
