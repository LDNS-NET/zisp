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
        $existing = $this->api->getClient()
            ->query('/ip/hotspot/user/print')
            ->where('name', $data['username'])
            ->read();

        if (!empty($existing)) {
            return; // already exists → idempotent
        }

        $query = (new Query('/ip/hotspot/user/add'))
            ->equal('name', $data['username'])
            ->equal('password', $data['password'])
            ->equal('profile', $data['profile']);

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
            ->read();

        foreach ($users as $user) {
            if (($user['name'] ?? null) === $username) {
                $query = (new Query('/ip/hotspot/user/remove'))
                    ->equal('.id', $user['.id']);
                $this->api->getClient()->query($query)->read();
            }
        }
    }
}
