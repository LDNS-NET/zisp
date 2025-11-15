<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WireGuardService
{
    protected string $wgInterface;

    public function __construct(string $wgInterface = null)
    {
        $this->wgInterface = $wgInterface ?: (config('wireguard.wg_interface') ?? env('WG_INTERFACE', 'wg0'));
    }

    /**
     * Apply or update a peer for the given router on the server WireGuard interface.
     */
    public function applyPeer(TenantMikrotik $router): bool
    {
        if (empty($router->wireguard_public_key)) {
            Log::warning('applyPeer called without public key', ['router_id' => $router->id]);
            return false;
        }

        $peerPub = $router->wireguard_public_key;
        $addr = $router->wireguard_address;
        $allowedIps = $router->wireguard_allowed_ips ?? ($addr ? ($addr . '/32') : '10.254.0.0/16');

        // Build wg command for server side: do NOT set endpoint here (client sets server endpoint).
        $wgBinary = config('wireguard.wg_binary') ?? env('WG_BINARY', '/usr/bin/wg');
        $wgCmd = sprintf("%s set %s peer %s allowed-ips %s persistent-keepalive 25",
            escapeshellarg($wgBinary),
            escapeshellarg($this->wgInterface),
            escapeshellarg($peerPub),
            escapeshellarg($allowedIps)
        );

        // On many systems `wg` binary is at /usr/bin/wg; run via shell to allow sudo.
        $cmd = "sudo /usr/bin/env sh -c '" . $wgCmd . "'";

        Log::info('Applying WireGuard peer', [
            'router_id' => $router->id,
            'interface' => $this->wgInterface,
            'allowed_ips' => $allowedIps,
        ]);

        try {
            $process = Process::fromShellCommandline($cmd);
            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('WireGuard apply failed', ['router_id' => $router->id, 'output' => $process->getErrorOutput() ?: $process->getOutput()]);
                $router->wireguard_status = 'failed';
                $router->save();
                return false;
            }

            Log::info('WireGuard peer applied', ['router_id' => $router->id, 'output' => $process->getOutput()]);
            $router->wireguard_status = 'active';
            $router->save();
            return true;
        } catch (ProcessFailedException $e) {
            Log::error('WireGuard process failed', ['router_id' => $router->id, 'error' => $e->getMessage()]);
            $router->wireguard_status = 'failed';
            $router->save();
            return false;
        } catch (\Exception $e) {
            Log::error('WireGuard apply exception', ['router_id' => $router->id, 'error' => $e->getMessage()]);
            $router->wireguard_status = 'failed';
            $router->save();
            return false;
        }
    }
}
