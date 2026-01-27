<?php

namespace App\Services\Mikrotik;

use App\Models\Tenants\TenantMikrotik;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * RouterOS API Service
 * 
 * All router operations use VPN tunnel IP (wireguard_address) only.
 * Connects via RouterOS API through WireGuard VPN (10.100.0.0/16 subnet).
 */
class RouterApiService
{
    protected $mikrotik;
    protected $client;
    protected $connection;

    /**
     * Create a new RouterOS API service instance.
     *
     * @param TenantMikrotik $mikrotik
     */
    public function __construct(TenantMikrotik $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    /**
     * Connect to the router via RouterOS API.
     * Uses VPN IP (wireguard_address) and configured API port.
     *
     * @return bool True on success, false on failure
     */
    public function connect(): bool
    {
        try {
            $this->getClient();
            return true;
        } catch (Exception $e) {
            Log::error('RouterOS API connection failed', [
                'router_id' => $this->mikrotik->id,
                'vpn_ip' => $this->mikrotik->wireguard_address,
                'api_port' => $this->mikrotik->api_port ?? 8728,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if router is online via API.
     *
     * @return bool
     */
    public function isOnline(): bool
    {
        try {
            $client = $this->getClient();
            // Simple query to test connectivity
            $client->query('/system/resource/print')->read();
            return true;
        } catch (Exception $e) {
            Log::warning('RouterOS API online check failed', [
                'router_id' => $this->mikrotik->id,
                'vpn_ip' => $this->mikrotik->wireguard_address,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get system resource information (CPU, memory, uptime).
     *
     * @return array|false
     */
    public function getSystemResource(): array|false
    {
        try {
            $client = $this->getClient();
            $resources = $client->query('/system/resource/print')->read();

            if (empty($resources) || !is_array($resources)) {
                return false;
            }

            return $resources[0] ?? [];
        } catch (Exception $e) {
            Log::error('Failed to get system resource', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get router identity (name).
     *
     * @return string|false
     */
    public function getIdentity(): string|false
    {
        try {
            $client = $this->getClient();
            $identity = $client->query('/system/identity/print')->read();

            if (empty($identity) || !is_array($identity)) {
                return false;
            }

            return $identity[0]['name'] ?? false;
        } catch (Exception $e) {
            Log::error('Failed to get router identity', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Set router identity (name).
     *
     * @param string $name
     * @return bool
     */
    public function setIdentity(string $name): bool
    {
        try {
            $client = $this->getClient();
            $query = (new \RouterOS\Query('/system/identity/set'))
                ->equal('name', $name);

            $client->query($query)->read();
            return true;
        } catch (Exception $e) {
            Log::error('Failed to set router identity', [
                'router_id' => $this->mikrotik->id,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get router interfaces.
     *
     * @return array
     */
    public function getInterfaces(): array
    {
        try {
            $client = $this->getClient();
            $interfaces = $client->query('/interface/print')->read();

            return is_array($interfaces) ? $interfaces : [];
        } catch (Exception $e) {
            Log::error('Failed to get interfaces', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get all IP addresses configured on the router.
     *
     * @return array
     */
    public function getIpAddresses(): array
    {
        try {
            $client = $this->getClient();
            $addresses = $client->query('/ip/address/print')->read();

            return is_array($addresses) ? $addresses : [];
        } catch (Exception $e) {
            Log::error('Failed to get IP addresses', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get ARP table entries.
     *
     * @return array
     */
    public function getArpTable(): array
    {
        try {
            $client = $this->getClient();
            $arp = $client->query('/ip/arp/print')->read();

            return is_array($arp) ? $arp : [];
        } catch (Exception $e) {
            Log::error('Failed to get ARP table', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get active hotspot sessions count.
     *
     * @return int
     */
    public function getHotspotActive(): int
    {
        try {
            $client = $this->getClient();
            $active = $client->query('/ip/hotspot/active/print')->read();

            return is_array($active) ? count($active) : 0;
        } catch (Exception $e) {
            Log::error('Failed to get hotspot active sessions', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get active PPPoE sessions count.
     *
     * @return int
     */
    public function getPppoeActive(): int
    {
        try {
            $client = $this->getClient();
            $active = $client->query('/ppp/active/print')->read();

            return is_array($active) ? count($active) : 0;
        } catch (Exception $e) {
            Log::error('Failed to get PPPoE active sessions', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get active hotspot users details.
     *
     * @return array
     */
    public function getHotspotActiveUsers(): array
    {
        try {
            $client = $this->getClient();
            $active = $client->query('/ip/hotspot/active/print')->read();

            return is_array($active) ? $active : [];
        } catch (Exception $e) {
            Log::error('Failed to get hotspot active users', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get active PPPoE users details.
     *
     * @return array
     */
    public function getPppoeActiveUsers(): array
    {
        try {
            $client = $this->getClient();
            $active = $client->query('/ppp/active/print')->read();

            return is_array($active) ? $active : [];
        } catch (Exception $e) {
            Log::error('Failed to get PPPoE active users', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get active DHCP leases (Static/Dynamic).
     *
     * @return array
     */
    public function getDhcpLeases(): array
    {
        try {
            $client = $this->getClient();
            // Fetch all leases, we can filter for active ones if needed, but usually all leases in print are relevant
            // We might want to filter by status=bound if we only want currently active ones
            $leases = $client->query('/ip/dhcp-server/lease/print')->read();

            return is_array($leases) ? $leases : [];
        } catch (Exception $e) {
            Log::error('Failed to get DHCP leases', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get WireGuard peers.
     *
     * @return array
     */
    public function getWireGuardPeers(): array
    {
        try {
            $client = $this->getClient();
            // Try to get peers (RouterOS v7+)
            $peers = $client->query('/interface/wireguard/peers/print')->read();

            return is_array($peers) ? $peers : [];
        } catch (Exception $e) {
            Log::error('Failed to get WireGuard peers', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get recent logs.
     *
     * @param int $limit
     * @return array
     */
    public function getLogs(int $limit = 50): array
    {
        try {
            $client = $this->getClient();
            // Get logs, sorted by time desc (though API returns in order, we might need to sort)
            // RouterOS API doesn't support 'limit' directly in print usually, but we can try
            // Or just get all and slice. Logs buffer can be large, so be careful.
            // Better to use /log/print without follow
            $logs = $client->query('/log/print')->read();

            if (!is_array($logs)) {
                return [];
            }

            // Reverse to get newest first
            $logs = array_reverse($logs);

            return array_slice($logs, 0, $limit);
        } catch (Exception $e) {
            Log::error('Failed to get logs', [
                'router_id' => $this->mikrotik->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * API-based ping test.
     * Uses RouterOS API to test connectivity instead of ICMP.
     *
     * @return array ['online' => bool, 'latency' => float|null]
     */
    public function apiPing(): array
    {
        $startTime = microtime(true);

        try {
            $client = $this->getClient();
            // Simple query to test connectivity
            $client->query('/system/resource/print')->read();

            $latency = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            return [
                'online' => true,
                'latency' => round($latency, 2),
            ];
        } catch (Exception $e) {
            Log::warning('RouterOS API ping failed', [
                'router_id' => $this->mikrotik->id,
                'vpn_ip' => $this->mikrotik->wireguard_address,
                'error' => $e->getMessage(),
            ]);

            return [
                'online' => false,
                'latency' => null,
            ];
        }
    }

    /**
     * Reboot the router.
     *
     * @return array
     */
    public function reboot()
    {
        $client = $this->getClient();
        $query = new \RouterOS\Query('/system/reboot');
        return $client->query($query)->read();
    }

    /**
     * Get RouterOS client instance.
     * 
     * @return \RouterOS\Client
     * @throws Exception
     */
    protected function getClient()
    {
        if (!$this->client) {
            // Get VPN IP from wireguard_address (standardized VPN IP storage)
            $vpnIp = $this->mikrotik->wireguard_address;

            // Legacy fallback: if wireguard_address not set, check if ip_address is in VPN subnet
            if (!$vpnIp && $this->mikrotik->ip_address) {
                $ip = $this->mikrotik->ip_address;
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipLong = ip2long($ip);
                    $networkLong = ip2long('10.100.0.0');
                    $mask = (-1 << (32 - 16)) & 0xFFFFFFFF;
                    if (($ipLong & $mask) === ($networkLong & $mask)) {
                        $vpnIp = $ip;
                    }
                }
            }

            if (!$vpnIp) {
                throw new Exception('Router VPN IP address is not set. Please ensure WireGuard tunnel is established.');
            }

            if (!filter_var($vpnIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new Exception('Router VPN IP address is not a valid IPv4.');
            }

            // Prioritize API credentials (zisp_user) over admin credentials
            $username = $this->mikrotik->api_username ?? $this->mikrotik->router_username;
            $password = $this->mikrotik->api_password ?? $this->mikrotik->router_password;

            if (!$username) {
                throw new Exception('Router username is not set.');
            }

            if (!$password) {
                throw new Exception('Router password is not set.');
            }

            // Connect using VPN IP only (10.100.0.0/16 subnet)
            $this->connection = [
                'host' => $vpnIp,
                'user' => $username,
                'pass' => $password,
                'port' => $this->mikrotik->api_port ?? 8728,
                'ssl' => $this->mikrotik->use_ssl ?? false,
                'timeout' => 3, // 3 second timeout as per requirements
                'attempts' => 1,
            ];

            try {
                $this->client = new \RouterOS\Client($this->connection);
            } catch (Exception $e) {
                Log::error('Failed to create RouterOS client via VPN tunnel', [
                    'connection' => [
                        'host' => $this->connection['host'],
                        'port' => $this->connection['port'],
                        'user' => $this->connection['user'],
                        'ssl' => $this->connection['ssl'] ?? false,
                    ],
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $this->client;
    }

    /**
     * Disconnect a specific user from the router.
     *
     * @param string $username
     * @param string $type 'hotspot' or 'pppoe'
     * @return bool
     */
    public function disconnectUser(string $username, string $type): bool
    {
        Log::debug("RouterApiService: Attempting to disconnect user $username (type: $type) from router {$this->mikrotik->name}");
        try {
            $client = $this->getClient();

            if ($type === 'hotspot') {
                // Disconnect hotspot user - fetch all and filter in PHP
                $activeUsers = $client->query('/ip/hotspot/active/print')->read();

                foreach ($activeUsers as $session) {
                    if (isset($session['user']) && $session['user'] === $username) {
                        $query = (new \RouterOS\Query('/ip/hotspot/active/remove'))
                            ->equal('.id', $session['.id']);
                        $client->query($query)->read();
                    }
                }
            } elseif ($type === 'pppoe') {
                // Disconnect PPPoE user - fetch all and filter in PHP
                $activeUsers = $client->query('/ppp/active/print')->read();

                foreach ($activeUsers as $session) {
                    if (isset($session['name']) && $session['name'] === $username) {
                        $query = (new \RouterOS\Query('/ppp/active/remove'))
                            ->equal('.id', $session['.id']);
                        $client->query($query)->read();
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to disconnect user', [
                'username' => $username,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

