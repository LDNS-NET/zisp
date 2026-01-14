<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Tenants\TenantMikrotik;

/**
 * RouterOS API Service
 * 
 * All router operations must use VPN tunnel IP (wireguard_address) only.
 * Public IP communication is deprecated.
 * 
 * This service connects to routers via VPN tunnel (10.100.0.0/16 subnet).
 * The VPN IP is retrieved from the router's wireguard_address field.
 */
class MikrotikService
{
    // WireGuard Subnet
    const WG_SUBNET = '10.100.0.0/16';
    const WG_GATEWAY = '10.100.0.1';

    protected $mikrotik;
    protected $client;
    protected $connection;
    protected $host;
    protected $username;
    protected $password;
    protected $port;

    /**
     * Create a new MikroTik service instance.
     *
     * @param TenantMikrotik|null $mikrotik
     */
    public function __construct(TenantMikrotik $mikrotik = null)
    {
        $this->mikrotik = $mikrotik;
    }

    /**
     * Create a new MikroTik service instance for a specific router.
     *
     * @param TenantMikrotik $mikrotik
     * @return self
     */
    public static function forMikrotik(TenantMikrotik $mikrotik): self
    {
        return new static($mikrotik);
    }

    /**
     * Set up the connection to the MikroTik router
     */
    public function setConnection($host, $username, $password, $port = 8728, $useSsl = false)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;

        $this->connection = [
            'host' => $host,
            'user' => $username,
            'pass' => $password,
            'port' => $port,
            'ssl' => $useSsl,
        ];

        // Reset the client to force reconnection with new settings
        $this->client = null;

        return $this;
    }

    /**
     * Remove a user from the MikroTik
     */
    public function removeUser($username)
    {
        try {
            $client = $this->getClient();
            $query = (new \RouterOS\Query('/ip/hotspot/user/remove'))
                ->equal('.id', $username);

            $response = $client->query($query)->read();

            Log::info('User removed from MikroTik', [
                'username' => $username,
                'mikrotik' => $this->connection['host'] ?? null
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to remove user from MikroTik', [
                'username' => $username,
                'mikrotik' => $this->connection['host'] ?? null,
                'error' => $e->getMessage()
            ]);

            // If the user doesn't exist, we can consider it a success
            if (str_contains($e->getMessage(), 'no such item')) {
                return true;
            }

            throw $e;
        }
    }

    /**
     * Update a user on the MikroTik
     */
    /* public function updateUser($username, $userData)
     {
         try {
             $client = $this->getClient();
             $query = (new \RouterOS\Query('/ip/hotspot/user/set'))
                 ->equal('.id', $username);

             foreach ($userData as $key => $value) {
                 if ($value !== null) {
                     $query->equal($key, $value);
                 }
             }

             $response = $client->query($query)->read();

             Log::info('User updated on MikroTik', [
                 'username' => $username,
                 'mikrotik' => $this->connection['host'] ?? null,
                 'updates' => array_keys($userData)
             ]);

             return $response;
         } catch (\Exception $e) {
             Log::error('Failed to update user on MikroTik', [
                 'username' => $username,
                 'mikrotik' => $this->connection['host'] ?? null,
                 'error' => $e->getMessage()
             ]);
             throw $e;
         }
     }*/

    /**
     * Add a user to the MikroTik
     */
    public function addUser($userData)
    {
        try {
            $client = $this->getClient();
            $query = new \RouterOS\Query('/ip/hotspot/user/add');

            foreach ($userData as $key => $value) {
                if ($value !== null) {
                    $query->equal($key, $value);
                }
            }

            $response = $client->query($query)->read();

            Log::info('User added to MikroTik', [
                'username' => $userData['name'] ?? null,
                'mikrotik' => $this->connection['host'] ?? null,
                'response' => $response
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to add user to MikroTik', [
                'username' => $userData['name'] ?? null,
                'mikrotik' => $this->connection['host'] ?? null,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get the RouterOS client instance.
     * 
     * All router communication must use VPN tunnel IP only (10.100.0.0/16).
     * This method retrieves the VPN IP from wireguard_address field.
     *
     * This is public so that higher-level services (e.g. TenantHotspotService)
     * can run advanced RouterOS commands while still reusing the connection
     * handling and error logging in this service.
     */
    public function getClient()
    {
        if (!$this->client) {
            if (!$this->connection) {
                if (!$this->mikrotik) {
                    throw new Exception('No Mikrotik model or connection configured.');
                }

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

                // Basic IPv4 validation
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
                // Use shorter timeout for scheduler (2-3 seconds max to avoid blocking)
                $this->connection = [
                    'host' => $vpnIp,
                    'user' => $username,
                    'pass' => $password,
                    'port' => $this->mikrotik->api_port ?? 8728,
                    'ssl' => $this->mikrotik->use_ssl ?? false,
                    'timeout' => 3, // 3 second timeout for scheduler (was 10)
                    'attempts' => 1, // Single attempt for faster failure (was 2)
                ];
            }

            try {
                $this->client = new \RouterOS\Client($this->connection);
            } catch (\Exception $e) {
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
     * Test Mikrotik connection (onboarding).
     *
     * @return array|false Router resources if successful, false otherwise
     */
    public function testConnection(): array|false
    {
        try {
            $client = $this->getClient();

            // Test connection with a simple query that works on all RouterOS versions
            $resources = $client->query('/system/resource/print')->read();

            // Validate response
            if (empty($resources) || !is_array($resources)) {
                Log::warning('Mikrotik testConnection: Empty or invalid response', [
                    'host' => $this->connection['host'] ?? null,
                    'port' => $this->connection['port'] ?? null,
                ]);
                return false;
            }

            Log::debug('Mikrotik connection test successful', [
                'host' => $this->connection['host'] ?? null,
                'port' => $this->connection['port'] ?? null,
            ]);

            return $resources;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('Mikrotik testConnection error', [
                'mikrotik_id' => $this->mikrotik->id ?? null,
                'host' => $this->connection['host'] ?? $this->mikrotik->ip_address ?? null,
                'port' => $this->connection['port'] ?? $this->mikrotik->api_port ?? 8728,
                'username' => $this->connection['user'] ?? $this->mikrotik->router_username ?? null,
                'error' => $errorMessage,
                'error_class' => get_class($e),
            ]);
            return false;
        }
    }

    /**
     * Create a user on Mikrotik (PPPoE or Hotspot).
     *
     * @param string $type 'pppoe' or 'hotspot'
     * @param array $data ['username' => ..., 'password' => ..., 'profile' => ...]
     * @return string|false Mikrotik internal ID or false on failure
     */
    public function createUser(string $type, array $data): bool
    {
        try {
            $client = $this->getClient();
            $response = null;
            if ($type === 'pppoe') {
                $response = $client->query('/ppp/secret/add', [
                    'name' => $data['username'],
                    'password' => $data['password'],
                    'profile' => $data['profile'] ?? 'default',
                ])->read();
            } elseif ($type === 'hotspot') {
                $response = $client->query('/ip/hotspot/user/add', [
                    'name' => $data['username'],
                    'password' => $data['password'],
                    'profile' => $data['profile'] ?? 'default',
                ])->read();
            } else {
                throw new Exception('Unsupported user type: ' . $type);
            }
            // Mikrotik returns an array with the new user's internal ID as ".id"
            if (is_array($response) && isset($response[0]['.id'])) {
                return $response[0]['.id'];
            }
            return false;
        } catch (Exception $e) {
            Log::error('Mikrotik createUser error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a user on Mikrotik (PPPoE or Hotspot).
     *
     * @param string $type 'pppoe' or 'hotspot'
     * @param array $data ['id' => ..., 'password' => ..., 'profile' => ...]
     * @return bool True on success, false on failure
     */
    public function updateUser(string $type, array $data): bool
    {
        try {
            $client = $this->getClient();
            if ($type === 'pppoe') {
                $client->query('/ppp/secret/set', [
                    '.id' => $data['id'], // Mikrotik internal ID
                    'password' => $data['password'],
                    'profile' => $data['profile'] ?? 'default',
                ])->read();
            } elseif ($type === 'hotspot') {
                $client->query('/ip/hotspot/user/set', [
                    '.id' => $data['id'],
                    'password' => $data['password'],
                    'profile' => $data['profile'] ?? 'default',
                ])->read();
            } else {
                throw new Exception('Unsupported user type: ' . $type);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik updateUser error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a user from Mikrotik (PPPoE or Hotspot).
     *
     * @param string $type 'pppoe' or 'hotspot'
     * @param string $id Mikrotik internal ID
     * @return bool True on success, false on failure
     */
    public function deleteUser(string $type, string $id): bool
    {
        try {
            $client = $this->getClient();
            if ($type === 'pppoe') {
                $client->query('/ppp/secret/remove', ['.id' => $id])->read();
            } elseif ($type === 'hotspot') {
                $client->query('/ip/hotspot/user/remove', ['.id' => $id])->read();
            } else {
                throw new Exception('Unsupported user type: ' . $type);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik deleteUser error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Assign a package/profile/queue to a user (PPPoE, Hotspot, Static).
     *
     * @param string $type 'pppoe', 'hotspot', or 'static'
     * @param array $data ['id' => ..., 'profile' => ..., 'queue' => ...]
     * @return bool True on success, false on failure
     */
    public function assignPackage(string $type, array $data): bool
    {
        try {
            $client = $this->getClient();
            if ($type === 'pppoe') {
                $client->query('/ppp/secret/set', [
                    '.id' => $data['id'],
                    'profile' => $data['profile'],
                ])->read();
            } elseif ($type === 'hotspot') {
                $client->query('/ip/hotspot/user/set', [
                    '.id' => $data['id'],
                    'profile' => $data['profile'],
                ])->read();
            } elseif ($type === 'static') {
                $client->query('/queue/simple/set', [
                    '.id' => $data['id'],
                    'max-limit' => $data['queue'],
                ])->read();
            } else {
                throw new Exception('Unsupported type: ' . $type);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik assignPackage error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update profile or queue for a user (PPPoE, Hotspot, Static).
     *
     * @param string $type 'pppoe', 'hotspot', or 'static'
     * @param array $data ['id' => ..., 'profile' => ..., 'queue' => ...]
     * @return bool True on success, false on failure
     */
    public function updateProfileOrQueue(string $type, array $data): bool
    {
        try {
            $client = $this->getClient();
            if ($type === 'pppoe') {
                $client->query('/ppp/secret/set', [
                    '.id' => $data['id'],
                    'profile' => $data['profile'],
                ])->read();
            } elseif ($type === 'hotspot') {
                $client->query('/ip/hotspot/user/set', [
                    '.id' => $data['id'],
                    'profile' => $data['profile'],
                ])->read();
            } elseif ($type === 'static') {
                $client->query('/queue/simple/set', [
                    '.id' => $data['id'],
                    'max-limit' => $data['queue'],
                ])->read();
            } else {
                throw new Exception('Unsupported type: ' . $type);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik updateProfileOrQueue error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get online users from Mikrotik (hotspot, pppoe, static).
     *
     * @return array Array of users with type
     */
    public function getOnlineUsers(): array
    {
        try {
            $client = $this->getClient();
            $users = [];
            // Hotspot users
            $hotspot = $client->query('/ip/hotspot/active/print')->read();
            foreach ($hotspot as $row) {
                $users[] = [
                    'username' => $row['user'] ?? null,
                    'mac' => $row['mac-address'] ?? null,
                    'ip' => $row['address'] ?? null,
                    'user_type' => 'hotspot',
                    'session_start' => $row['login-by'] ?? null,
                    'session_end' => null,
                ];
            }
            // PPPoE users
            $pppoe = $client->query('/ppp/active/print')->read();
            foreach ($pppoe as $row) {
                $users[] = [
                    'username' => $row['name'] ?? null,
                    'mac' => null,
                    'ip' => $row['address'] ?? null,
                    'user_type' => 'pppoe',
                    'session_start' => $row['uptime'] ?? null,
                    'session_end' => null,
                ];
            }
            // Static DHCP leases (optional)
            $static = $client->query('/ip/dhcp-server/lease/print')->read();
            foreach ($static as $row) {
                if (($row['status'] ?? '') === 'bound' && ($row['dynamic'] ?? 'true') === 'false') {
                    $users[] = [
                        'username' => $row['host-name'] ?? null,
                        'mac' => $row['mac-address'] ?? null,
                        'ip' => $row['address'] ?? null,
                        'user_type' => 'static',
                        'session_start' => null,
                        'session_end' => null,
                    ];
                }
            }
            return $users;
        } catch (Exception $e) {
            Log::error('Mikrotik getOnlineUsers error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Disconnect a user session (PPPoE or Hotspot).
     *
     * @param string $type 'pppoe' or 'hotspot'
     * @param string $id Mikrotik internal ID of active session
     * @return bool True on success, false on failure
     */
    public function disconnectUser(string $type, string $id): bool
    {
        try {
            $client = $this->getClient();
            if ($type === 'pppoe') {
                $client->query('/ppp/active/remove', ['.id' => $id])->read();
            } elseif ($type === 'hotspot') {
                $client->query('/ip/hotspot/active/remove', ['.id' => $id])->read();
            } else {
                throw new Exception('Unsupported type: ' . $type);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik disconnectUser error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Suspend a user (move to suspended/disabled profile)
     * $type: 'pppoe' or 'hotspot'
     * $id: Mikrotik internal ID
     * $suspendedProfile: profile name for suspension
     */
    public function suspendUser(string $type, string $id, string $suspendedProfile = 'suspended'): bool
    {
        try {
            $client = $this->getClient();
            if ($type === 'pppoe') {
                $client->query('/ppp/secret/set', [
                    '.id' => $id,
                    'profile' => $suspendedProfile,
                ])->read();
            } elseif ($type === 'hotspot') {
                $client->query('/ip/hotspot/user/set', [
                    '.id' => $id,
                    'profile' => $suspendedProfile,
                ])->read();
            } else {
                throw new Exception('Unsupported type: ' . $type);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik suspendUser error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsuspend a user (move to normal/active profile)
     * $type: 'pppoe' or 'hotspot'
     * $id: Mikrotik internal ID
     * $activeProfile: profile name for normal use
     */
    public function unsuspendUser(string $type, string $id, string $activeProfile = 'default'): bool
    {
        try {
            $client = $this->getClient();

            // Resolve ID if it's likely a name (IDs start with *)
            if (substr($id, 0, 1) !== '*') {
                $path = ($type === 'pppoe') ? '/ppp/secret' : '/ip/hotspot/user';
                
                $resQuery = (new \RouterOS\Query($path . '/print'))
                    ->equal('name', $id);
                
                $existing = $client->query($resQuery)->read();

                if (is_array($existing) && !empty($existing) && isset($existing[0]['.id'])) {
                    $id = $existing[0]['.id'];
                } else {
                    Log::info('Mikrotik: Skipping unsuspend, username not found locally', ['username' => $id, 'type' => $type]);
                    // For hotspot users in a RADIUS setup, they don't always exist locally
                    return true;
                }
            }

            if ($type === 'pppoe') {
                $setQuery = (new \RouterOS\Query('/ppp/secret/set'))
                    ->equal('.id', $id)
                    ->equal('profile', $activeProfile);
                $client->query($setQuery)->read();
            } elseif ($type === 'hotspot') {
                $setQuery = (new \RouterOS\Query('/ip/hotspot/user/set'))
                    ->equal('.id', $id)
                    ->equal('profile', $activeProfile);
                $client->query($setQuery)->read();
            } else {
                throw new Exception('Unsupported type: ' . $type);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik unsuspendUser error: ' . $e->getMessage(), [
                'type' => $type,
                'id' => $id,
                'profile' => $activeProfile
            ]);
            return false;
        }
    }

    /**
     * Create a system user on the MikroTik router.
     * This is used to create the dedicated API user (zisp_user).
     *
     * @param string $username Username to create
     * @param string $password Password for the user
     * @param string $group Group/permissions (default: 'full')
     * @return bool True on success, false on failure
     */
    public function createSystemUser(string $username, string $password, string $group = 'full'): bool
    {
        try {
            $client = $this->getClient();

            // Check if user already exists
            $existingUsers = $client->query('/user/print')->read();
            $userExists = false;
            $userId = null;

            foreach ($existingUsers as $user) {
                if (isset($user['name']) && $user['name'] === $username) {
                    $userExists = true;
                    $userId = $user['.id'] ?? null;
                    break;
                }
            }

            if ($userExists && $userId) {
                // User exists, update password
                $client->query('/user/set', [
                    '.id' => $userId,
                    'password' => $password,
                    'group' => $group,
                ])->read();

                Log::info('MikroTik system user updated', [
                    'mikrotik_id' => $this->mikrotik->id ?? null,
                    'username' => $username,
                    'group' => $group,
                ]);
            } else {
                // User doesn't exist, create it
                $client->query('/user/add', [
                    'name' => $username,
                    'password' => $password,
                    'group' => $group,
                ])->read();

                Log::info('MikroTik system user created', [
                    'mikrotik_id' => $this->mikrotik->id ?? null,
                    'username' => $username,
                    'group' => $group,
                ]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to create/update MikroTik system user', [
                'mikrotik_id' => $this->mikrotik->id ?? null,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update a system user's password on the MikroTik router.
     *
     * @param string $username Username to update
     * @param string $password New password
     * @return bool True on success, false on failure
     */
    public function updateSystemUser(string $username, string $password): bool
    {
        try {
            $client = $this->getClient();

            // Find the user
            $users = $client->query('/user/print')->read();
            $userId = null;

            foreach ($users as $user) {
                if (isset($user['name']) && $user['name'] === $username) {
                    $userId = $user['.id'] ?? null;
                    break;
                }
            }

            if (!$userId) {
                Log::warning('Cannot update system user: user not found', [
                    'mikrotik_id' => $this->mikrotik->id ?? null,
                    'username' => $username,
                ]);
                return false;
            }

            // Update password
            $client->query('/user/set', [
                '.id' => $userId,
                'password' => $password,
            ])->read();

            Log::info('MikroTik system user password updated', [
                'mikrotik_id' => $this->mikrotik->id ?? null,
                'username' => $username,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to update MikroTik system user password', [
                'mikrotik_id' => $this->mikrotik->id ?? null,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Authenticate a hotspot user directly via API.
     * This is much faster than kicking and waiting for re-association.
     */
    public function loginHotspotUserByMac(string $mac, string $username, string $password): bool
    {
        try {
            $client = $this->getClient();
            $mac = strtoupper($mac); // Normalize MAC
            
            // 1. Find the host entry to get the IP address
            Log::debug('Mikrotik: Searching for host IP', ['mac' => $mac]);
            
            // We'll get all hosts and filter in PHP for case-insensitivity and better error tracing
            $hosts = $client->query('/ip/hotspot/host/print')->read();
            
            $ipAddress = null;
            $server = 'all';

            if (is_array($hosts)) {
                foreach ($hosts as $h) {
                    if (isset($h['mac-address']) && strtoupper($h['mac-address']) === $mac && isset($h['address'])) {
                        $ipAddress = $h['address'];
                        $server = $h['server'] ?? 'all';
                        Log::debug('Mikrotik: Found host IP in host table', ['mac' => $mac, 'ip' => $ipAddress]);
                        break;
                    }
                }
            }

            // 2. Fallback to ARP table if not in hotspot host table
            if (!$ipAddress) {
                Log::debug('Mikrotik: Host not found in hotspot table, checking ARP table', ['mac' => $mac]);
                $arpEntries = $client->query('/ip/arp/print')->read();
                if (is_array($arpEntries)) {
                    foreach ($arpEntries as $entry) {
                        if (isset($entry['mac-address']) && strtoupper($entry['mac-address']) === $mac && isset($entry['address'])) {
                            $ipAddress = $entry['address'];
                            Log::debug('Mikrotik: Found host IP in ARP table', ['mac' => $mac, 'ip' => $ipAddress]);
                            break;
                        }
                    }
                }
            }

            if (!$ipAddress) {
                Log::warning('Mikrotik: No valid host entry or ARP entry with IP found', ['mac' => $mac]);
                return false;
            }

            // 3. Perform the login
            $loginQuery = (new \RouterOS\Query('/ip/hotspot/active/login'))
                ->equal('user', $username)
                ->equal('password', $password)
                ->equal('mac-address', $mac)
                ->equal('ip', $ipAddress)
                ->equal('server', $server);
                
            $result = $client->query($loginQuery)->read();

            Log::info('Mikrotik: Direct login attempt completed', [
                'mac' => $mac,
                'ip' => $ipAddress,
                'user' => $username,
                'result' => $result
            ]);

            // Check for errors in result (e.g. !trap or message)
            if (is_array($result)) {
                foreach ($result as $item) {
                    if (isset($item['!trap']) || isset($item['message'])) {
                        Log::warning('Mikrotik: Direct login failed with error', ['error' => $item]);
                        return false;
                    }
                    if (isset($item['after']) && isset($item['after']['message'])) {
                         Log::warning('Mikrotik: Direct login failed with after-message', ['error' => $item['after']['message']]);
                         return false;
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik loginHotspotUserByMac error: ' . $e->getMessage(), [
                'mac' => $mac,
                'user' => $username
            ]);
            return false;
        }
    }

    /**
     * Kick a hotspot user by MAC address.
     * Finds the active session and removes it.
     */
    public function kickHotspotUserByMac(string $mac): bool
    {
        try {
            $client = $this->getClient();
            $activeSessions = $client->query('/ip/hotspot/active/print')->read();
            
            $found = false;
            foreach ($activeSessions as $session) {
                if (isset($session['mac-address']) && strtolower($session['mac-address']) === strtolower($mac)) {
                    $removeQuery = (new \RouterOS\Query('/ip/hotspot/active/remove'))
                        ->equal('.id', $session['.id']);
                    $client->query($removeQuery)->read();
                    $found = true;
                    Log::info('Hotspot user kicked by MAC', ['mac' => $mac, 'session_id' => $session['.id']]);
                }
            }
            
            return $found;
        } catch (Exception $e) {
            Log::error('Mikrotik kickHotspotUserByMac error: ' . $e->getMessage());
            return false;
        }
    }

    // ...other methods to be implemented...

    /**
     * Find and assign the next available WireGuard IP address.
     * 
     * Scans for first available IP in 10.100.0.0/16 range.
     * Ignores global scopes to ensure unique IP across all tenants.
     */
    public static function assignNextAvailableWireguardIp(TenantMikrotik $router)
    {
        // Get all used IPs across system (ignore tenant scope)
        $usedIps = TenantMikrotik::withoutGlobalScopes()
            ->whereNotNull('wireguard_address')
            ->pluck('wireguard_address')
            ->toArray();

        // Start from 10.100.0.2 (.1 is gateway)
        $startLong = ip2long('10.100.0.2');
        $endLong = ip2long('10.100.255.254');

        for ($i = $startLong; $i <= $endLong; $i++) {
            $currentIp = long2ip($i);
            if (!in_array($currentIp, $usedIps)) {
                $router->wireguard_address = $currentIp;
                $router->save();
                Log::info("Assigned WireGuard IP {$currentIp} to router {$router->id}");
                return $currentIp;
            }
        }

        throw new \Exception("No available WireGuard IPs in subnet " . self::WG_SUBNET);
    }
}
