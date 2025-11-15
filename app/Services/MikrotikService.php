<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Tenants\TenantMikrotik;

class MikrotikService
{
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
     * Get the RouterOS client instance
     */
    protected function getClient()
    {
        if (!$this->client) {
            if (!$this->connection) {
                if (!$this->mikrotik) {
                    throw new Exception('No Mikrotik model or connection configured.');
                }
                
                // Ensure we have all required connection parameters
                if (!$this->mikrotik->ip_address) {
                    throw new Exception('Router IP address is not set.');
                }
                
                if (!$this->mikrotik->router_username) {
                    throw new Exception('Router username is not set.');
                }
                
                if (!$this->mikrotik->router_password) {
                    throw new Exception('Router password is not set.');
                }
                
                $this->connection = [
                    'host' => $this->mikrotik->ip_address,
                    'user' => $this->mikrotik->router_username,
                    'pass' => $this->mikrotik->router_password,
                    'port' => $this->mikrotik->api_port ?? 8728,
                    'ssl' => $this->mikrotik->use_ssl ?? false,
                    'timeout' => 10, // Increased timeout for better reliability
                    'attempts' => 2, // Try twice before failing
                ];
            }
            
            try {
                $this->client = new \RouterOS\Client($this->connection);
            } catch (\Exception $e) {
                Log::error('Failed to create RouterOS client', [
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
            $hotspot = $client->query('/ip/hotspot/active')->read();
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
            $pppoe = $client->query('/ppp/active')->read();
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
            $static = $client->query('/ip/dhcp-server/lease')->read();
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
            if ($type === 'pppoe') {
                $client->query('/ppp/secret/set', [
                    '.id' => $id,
                    'profile' => $activeProfile,
                ])->read();
            } elseif ($type === 'hotspot') {
                $client->query('/ip/hotspot/user/set', [
                    '.id' => $id,
                    'profile' => $activeProfile,
                ])->read();
            } else {
                throw new Exception('Unsupported type: ' . $type);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Mikrotik unsuspendUser error: ' . $e->getMessage());
            return false;
        }
    }

    // ...other methods to be implemented...
}
