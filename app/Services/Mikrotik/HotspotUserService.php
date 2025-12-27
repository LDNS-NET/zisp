<?php

namespace App\Services\Mikrotik;

use RouterOS\Query;
use Exception;

class HotspotUserService
{
    public function __construct(
        protected RouterApiService $api
    ) {}

    /**
     * Create a hotspot user (voucher).
     */
    /**
     * Create a hotspot user (voucher).
     * @throws Exception
     */
    public function create(array $data): void
    {
        foreach (['username', 'password', 'profile'] as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing {$field}");
            }
        }

        if (!$this->api->connect()) {
            throw new Exception('Router is offline');
        }

        // Prevent duplicates
        if ($this->exists($data['username'])) {
            return; // already exists → idempotent
        }

        $query = (new Query('/ip/hotspot/user/add'))
            ->equal('name', $data['username'])
            ->equal('password', $data['password'])
            ->equal('profile', $data['profile']);

        if (isset($data['comment'])) {
            $query->equal('comment', $data['comment']);
        }
        
        if (isset($data['limit-uptime'])) {
             $query->equal('limit-uptime', $data['limit-uptime']);
        }

        $this->api->getClient()->query($query)->read();
    }

    /**
     * Remove a voucher user (on expiry / revoke).
     */
    public function remove(string $username): void
    {
        if (!$this->api->connect()) {
            return;
        }

        $users = $this->api->getClient()
            ->query('/ip/hotspot/user/print')
            ->where('name', $username)
            ->read();

        foreach ($users as $user) {
             $query = (new Query('/ip/hotspot/user/remove'))
                 ->equal('.id', $user['.id']);
             $this->api->getClient()->query($query)->read();
        }
    }

    /**
     * Check if a hotspot user exists.
     */
    public function exists(string $username): bool
    {
        if (!$this->api->connect()) {
            return false; // Assuming false if offline, strictly handling might require exception
        }

        $users = $this->api->getClient()
            ->query('/ip/hotspot/user/print')
            ->where('name', $username)
            ->read();

        return !empty($users);
    }
}
