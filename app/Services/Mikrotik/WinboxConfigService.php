<?php

namespace App\Services\Mikrotik;

use App\Models\Tenants\TenantMikrotik;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service for managing Remote Winbox access over WireGuard VPN.
 * 
 * Note: Winbox is configured to be accessible from anywhere (no IP restriction)
 * as per the existing onboarding script configuration.
 */
class WinboxConfigService
{
    /**
     * Generate RouterOS commands to enable Winbox.
     *
     * @param TenantMikrotik $mikrotik
     * @return string RouterOS script
     */
    public function generateWinboxConfig(TenantMikrotik $mikrotik): string
    {
        $winboxPort = 8291;
        $hostname = $mikrotik->name;
        $wgIp = $mikrotik->wireguard_address;

        $script = <<<RSC
# ================= Remote Winbox Configuration =================
# Router: {$hostname}
# WireGuard IP: {$wgIp}
# Generated at: {{ now }}

:put "==================== CONFIGURING REMOTE WINBOX ===================="

# ---------- Enable Winbox Service ----------
:do {
    /ip service set winbox disabled=no port={$winboxPort} address=""
    :put "✓ Winbox service enabled on port {$winboxPort} (accessible from anywhere)"
} on-error={
    :put "✗ Failed to enable Winbox service"
}

:put "==================== REMOTE WINBOX CONFIGURED ===================="
:put "You can now connect via: winbox://{$wgIp}:{$winboxPort}"
:put "Note: Connect through WireGuard VPN for secure access"

RSC;

        return str_replace('{{ now }}', now()->toDateTimeString(), $script);
    }

    /**
     * Push Winbox configuration to router via API.
     *
     * @param TenantMikrotik $mikrotik
     * @return array
     */
    public function enableWinboxOverWireGuard(TenantMikrotik $mikrotik): array
    {
        try {
            if (!$mikrotik->wireguard_address) {
                throw new Exception('Router does not have a WireGuard IP address configured.');
            }

            $apiService = new RouterApiService($mikrotik);
            
            if (!$apiService->isOnline()) {
                throw new Exception('Router is not online. Cannot push configuration.');
            }

            // Enable Winbox service (matches onboarding script configuration)
            $this->enableWinboxService($apiService);

            Log::info('Remote Winbox enabled', [
                'router_id' => $mikrotik->id,
                'wg_ip' => $mikrotik->wireguard_address,
            ]);

            return [
                'success' => true,
                'message' => 'Remote Winbox enabled successfully',
                'winbox_url' => "winbox://{$mikrotik->wireguard_address}:8291",
            ];

        } catch (Exception $e) {
            Log::error('Failed to enable Remote Winbox', [
                'router_id' => $mikrotik->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to enable Remote Winbox: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Enable Winbox service on the router.
     * Matches onboarding script: address="" (accessible from anywhere)
     *
     * @param RouterApiService $apiService
     * @return void
     */
    protected function enableWinboxService(RouterApiService $apiService): void
    {
        $client = $apiService->getClient();
        
        // Enable Winbox service with no address restriction (matches onboarding script)
        $query = (new \RouterOS\Query('/ip/service/set'))
            ->equal('.id', 'winbox')
            ->equal('disabled', 'no')
            ->equal('port', '8291')
            ->equal('address', '');
            
        $client->query($query)->read();
    }


    /**
     * Ping router to check if it's online.
     *
     * @param string $wgIp WireGuard IP address
     * @return array
     */
    public function pingRouter(string $wgIp): array
    {
        if (empty($wgIp)) {
            return [
                'online' => false,
                'message' => 'No WireGuard IP configured',
            ];
        }

        // Use system ping command
        $output = [];
        $returnVar = 0;
        
        // Windows vs Linux ping
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $pingCommand = $isWindows 
            ? "ping -n 1 -w 1000 {$wgIp}" 
            : "ping -c 1 -W 1 {$wgIp}";
        
        exec($pingCommand, $output, $returnVar);
        
        $online = ($returnVar === 0);

        return [
            'online' => $online,
            'wg_ip' => $wgIp,
            'message' => $online ? 'Router is online' : 'Router is offline or unreachable',
        ];
    }
}
