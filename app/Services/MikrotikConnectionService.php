<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;
use RouterOS\Query;
use RouterOS\Client;
use RouterOS\Config;
use Exception;

class MikrotikConnectionService
{
    /**
     * Connect to a Mikrotik device via API
     *
     * @param TenantMikrotik $mikrotik
     * @return Client|null
     */
    public function connect(TenantMikrotik $mikrotik): ?Client
    {
        try {
            if (!$mikrotik->ip_address || !$mikrotik->api_username) {
                throw new Exception('Missing IP address or username');
            }

            $config = new Config([
                'host' => $mikrotik->ip_address,
                'port' => $mikrotik->api_port ?? 8728,
                'user' => $mikrotik->api_username,
                'pass' => decrypt($mikrotik->api_password),
                'timeout' => 10,
            ]);

            $client = new Client($config);
            $mikrotik->markConnected();
            
            return $client;
        } catch (Exception $e) {
            $mikrotik->update([
                'last_error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Test connection to a Mikrotik device
     *
     * @param TenantMikrotik $mikrotik
     * @return bool
     */
    public function testConnection(TenantMikrotik $mikrotik): bool
    {
        try {
            $client = $this->connect($mikrotik);
            if (!$client) {
                return false;
            }

            // Try to get identity to verify connection
            $query = new Query('/system/identity/print');
            $client->query($query)->read();
            
            $mikrotik->markConnected();
            return true;
        } catch (Exception $e) {
            $mikrotik->failOnboarding($e->getMessage());
            return false;
        }
    }

    /**
     * Get device information from Mikrotik
     *
     * @param TenantMikrotik $mikrotik
     * @return array|null Device info or null on failure
     */
    public function getDeviceInfo(TenantMikrotik $mikrotik): ?array
    {
        try {
            $client = $this->connect($mikrotik);
            if (!$client) {
                return null;
            }

            $info = [];

            // Get system identity
            $query = new Query('/system/identity/print');
            $response = $client->query($query)->read();
            if (!empty($response)) {
                $info['identity'] = $response[0]['name'] ?? '';
            }

            // Get board name
            $query = new Query('/system/routerboard/print');
            $response = $client->query($query)->read();
            if (!empty($response)) {
                $info['board_name'] = $response[0]['board-name'] ?? '';
                $info['model'] = $response[0]['model'] ?? '';
            }

            // Get system package
            $query = new Query('/system/package/print');
            $response = $client->query($query)->read();
            if (!empty($response)) {
                foreach ($response as $package) {
                    if ($package['name'] === 'system') {
                        $info['system_version'] = $package['version'] ?? '';
                    }
                }
            }

            // Get resource info
            $query = new Query('/system/resource/print');
            $response = $client->query($query)->read();
            if (!empty($response)) {
                $res = $response[0];
                $info['uptime'] = $res['uptime'] ?? '';
                $info['total_memory'] = $res['total-memory'] ?? 0;
                $info['free_memory'] = $res['free-memory'] ?? 0;
                $info['cpu_load'] = $res['cpu-load'] ?? 0;
                $info['cpu_count'] = $res['cpu-count'] ?? 0;
            }

            // Get interface count
            $query = new Query('/interface/print');
            $response = $client->query($query)->read();
            $info['interface_count'] = count($response);

            return $info;
        } catch (Exception $e) {
            $mikrotik->failOnboarding($e->getMessage());
            return null;
        }
    }

    /**
     * Get online status from Mikrotik interfaces
     *
     * @param TenantMikrotik $mikrotik
     * @return array Array of interfaces with running status
     */
    public function getInterfaceStatus(TenantMikrotik $mikrotik): array
    {
        try {
            $client = $this->connect($mikrotik);
            if (!$client) {
                return [];
            }

            $query = new Query('/interface/print');
            $interfaces = $client->query($query)->read();
            
            $result = [];
            foreach ($interfaces as $interface) {
                $result[] = [
                    'name' => $interface['name'] ?? '',
                    'running' => ($interface['running'] ?? 'false') === 'true',
                    'type' => $interface['type'] ?? 'unknown',
                    'mac_address' => $interface['mac-address'] ?? '',
                ];
            }

            return $result;
        } catch (Exception $e) {
            \Log::warning("Failed to get interface status for mikrotik {$mikrotik->id}: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Get IP addresses configured on the device
     *
     * @param TenantMikrotik $mikrotik
     * @return array Array of IP configurations
     */
    public function getIPAddresses(TenantMikrotik $mikrotik): array
    {
        try {
            $client = $this->connect($mikrotik);
            if (!$client) {
                return [];
            }

            $query = new Query('/ip/address/print');
            $addresses = $client->query($query)->read();
            
            $result = [];
            foreach ($addresses as $address) {
                $result[] = [
                    'interface' => $address['interface'] ?? '',
                    'address' => $address['address'] ?? '',
                    'disabled' => ($address['disabled'] ?? 'false') === 'true',
                ];
            }

            return $result;
        } catch (Exception $e) {
            \Log::warning("Failed to get IP addresses for mikrotik {$mikrotik->id}: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Check if router has wireless capability
     *
     * @param TenantMikrotik $mikrotik
     * @return bool
     */
    public function hasWireless(TenantMikrotik $mikrotik): bool
    {
        try {
            $client = $this->connect($mikrotik);
            if (!$client) {
                return false;
            }

            $query = new Query('/interface/wireless/print');
            $wireless = $client->query($query)->read();
            
            return !empty($wireless);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Enable/Disable an interface
     *
     * @param TenantMikrotik $mikrotik
     * @param string $interfaceName
     * @param bool $enabled
     * @return bool
     */
    public function setInterfaceStatus(TenantMikrotik $mikrotik, string $interfaceName, bool $enabled): bool
    {
        try {
            $client = $this->connect($mikrotik);
            if (!$client) {
                return false;
            }

            $query = new Query('/interface/print');
            $query->where('name', $interfaceName);
            $interfaces = $client->query($query)->read();

            if (empty($interfaces)) {
                throw new Exception("Interface {$interfaceName} not found");
            }

            $interfaceId = $interfaces[0]['.id'];
            
            $query = new Query('/interface/set');
            $query->equal('.id', $interfaceId);
            $query->equal('disabled', !$enabled ? 'true' : 'false');
            $client->query($query)->read();

            return true;
        } catch (Exception $e) {
            \Log::error("Failed to set interface status: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Disconnect from router
     *
     * @param Client $client
     * @return void
     */
    public function disconnect(Client $client): void
    {
        try {
            $client->disconnect();
        } catch (Exception $e) {
            \Log::warning("Error disconnecting from Mikrotik: {$e->getMessage()}");
        }
    }
}
