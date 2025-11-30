<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Carbon\Carbon;

/**
 * WireGuard Service
 * 
 * All MikroTik routers communicate with the server using VPN tunnel only (10.100.0.0/16).
 * Public IP communication is deprecated.
 * 
 * Architecture:
 * - Server VPN interface: 10.100.0.1/16
 * - Router VPN IPs: 10.100.0.2 - 10.100.255.254 (within /16 subnet)
 * - AllowedIPs: 10.100.0.0/16 (unified subnet, no longer /32 per-peer)
 */
class WireGuardService
{
    protected string $wgInterface;
    protected string $configPath;
    protected string $backupDir;
    protected string $wgBinary;
    protected string $wgQuickBinary;

    public function __construct()
    {
        $this->wgInterface = config('wireguard.wg_interface', 'wg0');
        $this->configPath = config('wireguard.config_path', '/etc/wireguard/wg0.conf');
        $this->backupDir = config('wireguard.backup_dir', '/etc/wireguard/backups');
        $this->wgBinary = config('wireguard.wg_binary', '/usr/bin/wg');
        $this->wgQuickBinary = config('wireguard.wg_quick_binary', '/usr/bin/wg-quick');
    }

    /**
     * Apply or update a peer for the given router on the server WireGuard interface.
     * Uses unified /16 subnet (10.100.0.0/16) for all routers.
     */
    public function applyPeer(TenantMikrotik $router): bool
    {
        if (empty($router->wireguard_public_key)) {
            Log::channel('wireguard')->warning('applyPeer called without public key', ['router_id' => $router->id]);
            return false;
        }

        try {
            // Step 1: Add/update peer in configuration file
            if (!$this->addPeerToConfig($router)) {
                Log::channel('wireguard')->error('Failed to add peer to config', ['router_id' => $router->id]);
                return false;
            }

            // Step 2: Apply to running interface without disruption
            if (!$this->applyConfigSafely()) {
                Log::channel('wireguard')->error('Failed to apply config safely', ['router_id' => $router->id]);
                return false;
            }

            Log::channel('wireguard')->info('WireGuard peer applied successfully', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'router_model' => $router->model,
                'public_key' => substr($router->wireguard_public_key, 0, 16) . '...',
                'address' => $router->wireguard_address,
            ]);

            $router->wireguard_status = 'active';
            $router->save();
            return true;

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('WireGuard apply exception', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $router->wireguard_status = 'failed';
            $router->save();
            return false;
        }
    }

    /**
     * Remove a peer from configuration and running interface
     */
    public function removePeer(TenantMikrotik $router): bool
    {
        if (empty($router->wireguard_public_key)) {
            Log::channel('wireguard')->warning('removePeer called without public key', ['router_id' => $router->id]);
            return false;
        }

        try {
            // Step 1: Remove from configuration file
            if (!$this->removePeerFromConfig($router->wireguard_public_key)) {
                Log::channel('wireguard')->error('Failed to remove peer from config', ['router_id' => $router->id]);
                return false;
            }

            // Step 2: Remove from running interface
            $cmd = sprintf(
                "sudo %s set %s peer %s remove",
                escapeshellarg($this->wgBinary),
                escapeshellarg($this->wgInterface),
                escapeshellarg($router->wireguard_public_key)
            );

            $process = Process::fromShellCommandline($cmd);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::channel('wireguard')->warning('Failed to remove peer from interface (may not exist)', [
                    'router_id' => $router->id,
                    'output' => $process->getErrorOutput() ?: $process->getOutput(),
                ]);
                // Continue anyway - peer might not have been active
            }

            Log::channel('wireguard')->info('WireGuard peer removed successfully', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'public_key' => substr($router->wireguard_public_key, 0, 16) . '...',
            ]);

            return true;

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('WireGuard removal exception', [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Add or update peer in configuration file
     */
    protected function addPeerToConfig(TenantMikrotik $router): bool
    {
        try {
            // Backup current config
            $this->backupConfig();

            // Read current config
            $configContent = $this->readConfig();

            // Parse existing peers
            $peers = $this->parsePeers($configContent);

            // Check if peer already exists
            $publicKey = $router->wireguard_public_key;
            $peerExists = false;

            foreach ($peers as $index => $peer) {
                if (isset($peer['PublicKey']) && $peer['PublicKey'] === $publicKey) {
                    $peerExists = true;
                    // Update existing peer
                    $peers[$index] = $this->buildPeerArray($router);
                    break;
                }
            }

            // Add new peer if it doesn't exist
            if (!$peerExists) {
                $peers[] = $this->buildPeerArray($router);
            }

            // Rebuild config with updated peers
            $newConfig = $this->buildConfigContent($configContent, $peers);

            // Write to temporary file
            $tempPath = $this->configPath . '.tmp';
            $result = $this->writeConfigSecurely($tempPath, $newConfig);

            if (!$result) {
                return false;
            }

            // Move temp file to actual config
            $mvCmd = sprintf("sudo mv %s %s", escapeshellarg($tempPath), escapeshellarg($this->configPath));
            $process = Process::fromShellCommandline($mvCmd);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::channel('wireguard')->error('Failed to move temp config', [
                    'output' => $process->getErrorOutput(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('Failed to add peer to config', [
                'error' => $e->getMessage(),
                'router_id' => $router->id,
            ]);
            return false;
        }
    }

    /**
     * Remove peer from configuration file
     */
    protected function removePeerFromConfig(string $publicKey): bool
    {
        try {
            // Backup current config
            $this->backupConfig();

            // Read current config
            $configContent = $this->readConfig();

            // Parse existing peers
            $peers = $this->parsePeers($configContent);

            // Filter out the peer to remove
            $filteredPeers = array_filter($peers, function ($peer) use ($publicKey) {
                return !isset($peer['PublicKey']) || $peer['PublicKey'] !== $publicKey;
            });

            // Rebuild config without the removed peer
            $newConfig = $this->buildConfigContent($configContent, array_values($filteredPeers));

            // Write to temporary file
            $tempPath = $this->configPath . '.tmp';
            $result = $this->writeConfigSecurely($tempPath, $newConfig);

            if (!$result) {
                return false;
            }

            // Move temp file to actual config
            $mvCmd = sprintf("sudo mv %s %s", escapeshellarg($tempPath), escapeshellarg($this->configPath));
            $process = Process::fromShellCommandline($mvCmd);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::channel('wireguard')->error('Failed to move temp config', [
                    'output' => $process->getErrorOutput(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('Failed to remove peer from config', [
                'error' => $e->getMessage(),
                'public_key' => substr($publicKey, 0, 16) . '...',
            ]);
            return false;
        }
    }

    /**
     * Apply configuration to running interface without disrupting connections
     * Uses 'wg syncconf' which doesn't drop existing connections
     */
    protected function applyConfigSafely(): bool
    {
        try {
            // Use wg syncconf for zero-downtime updates
            $cmd = sprintf(
                "sudo %s syncconf %s <(sudo %s strip %s)",
                escapeshellarg($this->wgBinary),
                escapeshellarg($this->wgInterface),
                escapeshellarg($this->wgQuickBinary),
                escapeshellarg($this->wgInterface)
            );

            $process = Process::fromShellCommandline($cmd);
            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::channel('wireguard')->error('wg syncconf failed', [
                    'output' => $process->getErrorOutput() ?: $process->getOutput(),
                ]);
                return false;
            }

            Log::channel('wireguard')->info('Configuration applied successfully via wg syncconf');
            return true;

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('Failed to apply config safely', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Read current configuration file
     */
    protected function readConfig(): string
    {
        $cmd = sprintf("sudo cat %s", escapeshellarg($this->configPath));
        $process = Process::fromShellCommandline($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Failed to read config file: ' . $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Write configuration file securely using sudo
     */
    protected function writeConfigSecurely(string $path, string $content): bool
    {
        try {
            // Write to local temp file first
            $localTemp = storage_path('app/wireguard_temp_' . time() . '.conf');
            file_put_contents($localTemp, $content);

            // Copy to target location with sudo
            $cpCmd = sprintf("sudo cp %s %s", escapeshellarg($localTemp), escapeshellarg($path));
            $process = Process::fromShellCommandline($cpCmd);
            $process->run();

            // Delete local temp file
            unlink($localTemp);

            if (!$process->isSuccessful()) {
                Log::channel('wireguard')->error('Failed to write config', [
                    'output' => $process->getErrorOutput(),
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('Failed to write config securely', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Parse peers from configuration content
     */
    protected function parsePeers(string $configContent): array
    {
        $peers = [];
        $lines = explode("\n", $configContent);
        $currentPeer = null;
        $currentComment = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // Check for peer section start
            if (stripos($line, '[Peer]') === 0) {
                if ($currentPeer !== null) {
                    $peers[] = $currentPeer;
                }
                $currentPeer = ['comment' => $currentComment];
                $currentComment = '';
                continue;
            }

            // Capture comments
            if (strpos($line, '#') === 0) {
                $currentComment .= $line . "\n";
                continue;
            }

            // Parse key-value pairs within peer section
            if ($currentPeer !== null && strpos($line, '=') !== false) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $currentPeer[$key] = $value;
            }
        }

        // Add last peer
        if ($currentPeer !== null) {
            $peers[] = $currentPeer;
        }

        return $peers;
    }

    /**
     * Build peer configuration array
     */
    protected function buildPeerArray(TenantMikrotik $router): array
    {
        $comment = sprintf(
            "# Router ID: %d | Name: %s | Model: %s",
            $router->id,
            $router->name,
            $router->model ?? 'Unknown'
        );

        return [
            'comment' => $comment,
            'PublicKey' => $router->wireguard_public_key,
            'AllowedIPs' => $router->wireguard_allowed_ips ?? '10.100.0.0/16',
            'PersistentKeepalive' => '25',
        ];
    }

    /**
     * Build complete configuration content
     */
    protected function buildConfigContent(string $originalConfig, array $peers): string
    {
        // Extract [Interface] section
        $lines = explode("\n", $originalConfig);
        $interfaceSection = [];
        $inInterface = false;

        foreach ($lines as $line) {
            if (stripos(trim($line), '[Interface]') === 0) {
                $inInterface = true;
                $interfaceSection[] = $line;
                continue;
            }

            if (stripos(trim($line), '[Peer]') === 0) {
                break; // Stop at first peer
            }

            if ($inInterface) {
                $interfaceSection[] = $line;
            }
        }

        // Build new config
        $config = implode("\n", $interfaceSection) . "\n\n";

        // Add all peers
        foreach ($peers as $peer) {
            if (isset($peer['comment']) && !empty($peer['comment'])) {
                $config .= trim($peer['comment']) . "\n";
            }
            $config .= "[Peer]\n";
            if (isset($peer['PublicKey'])) {
                $config .= "PublicKey = " . $peer['PublicKey'] . "\n";
            }
            if (isset($peer['AllowedIPs'])) {
                $config .= "AllowedIPs = " . $peer['AllowedIPs'] . "\n";
            }
            if (isset($peer['PersistentKeepalive'])) {
                $config .= "PersistentKeepalive = " . $peer['PersistentKeepalive'] . "\n";
            }
            $config .= "\n";
        }

        return $config;
    }

    /**
     * Create backup of current configuration
     */
    protected function backupConfig(): bool
    {
        try {
            // Ensure backup directory exists
            $mkdirCmd = sprintf("sudo mkdir -p %s", escapeshellarg($this->backupDir));
            $process = Process::fromShellCommandline($mkdirCmd);
            $process->run();

            // Create backup with timestamp
            $timestamp = Carbon::now()->format('Y-m-d_His');
            $backupPath = $this->backupDir . '/wg0.conf.backup.' . $timestamp;

            $cpCmd = sprintf(
                "sudo cp %s %s",
                escapeshellarg($this->configPath),
                escapeshellarg($backupPath)
            );
            $process = Process::fromShellCommandline($cpCmd);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::channel('wireguard')->warning('Failed to create backup', [
                    'output' => $process->getErrorOutput(),
                ]);
                return false;
            }

            Log::channel('wireguard')->info('Configuration backup created', [
                'backup_path' => $backupPath,
            ]);

            // Clean old backups
            $this->cleanOldBackups();

            return true;

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('Backup failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clean old backups based on retention policy
     */
    protected function cleanOldBackups(): void
    {
        try {
            $retentionDays = config('wireguard.backup_retention_days', 30);
            $cutoffDate = Carbon::now()->subDays($retentionDays)->format('Ymd');

            $findCmd = sprintf(
                "sudo find %s -name 'wg0.conf.backup.*' -type f",
                escapeshellarg($this->backupDir)
            );

            $process = Process::fromShellCommandline($findCmd);
            $process->run();

            if ($process->isSuccessful()) {
                $files = array_filter(explode("\n", $process->getOutput()));

                foreach ($files as $file) {
                    // Extract date from filename (format: wg0.conf.backup.YYYY-MM-DD_HHmmss)
                    if (preg_match('/wg0\.conf\.backup\.(\d{4}-\d{2}-\d{2})_/', basename($file), $matches)) {
                        $fileDate = str_replace('-', '', $matches[1]);
                        if ($fileDate < $cutoffDate) {
                            $rmCmd = sprintf("sudo rm %s", escapeshellarg($file));
                            Process::fromShellCommandline($rmCmd)->run();
                            Log::channel('wireguard')->info('Deleted old backup', ['file' => $file]);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            Log::channel('wireguard')->warning('Failed to clean old backups', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync all peers from database to configuration
     */
    public function syncAllPeers(): array
    {
        $results = [
            'added' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        try {
            $routers = TenantMikrotik::whereNotNull('wireguard_public_key')->get();

            foreach ($routers as $router) {
                try {
                    if ($this->applyPeer($router)) {
                        if ($router->wireguard_status === 'pending') {
                            $results['added']++;
                        } else {
                            $results['updated']++;
                        }
                    } else {
                        $results['failed']++;
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = sprintf('Router %d: %s', $router->id, $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::channel('wireguard')->error('Sync all peers failed', [
                'error' => $e->getMessage(),
            ]);
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }
}
