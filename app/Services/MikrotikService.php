<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;
use RouterOS\Client;
use RouterOS\Config as RouterConfig;
use RouterOS\Query;

class MikrotikService
{
    protected ?Client $client = null;

    /**
     * Build service from either a TenantMikrotik model or raw credentials
     */
    public function __construct(
        TenantMikrotik|string $hostOrModel,
        ?string $username = null,
        ?string $password = null,
        ?int $port = null
    ) {
        if ($hostOrModel instanceof TenantMikrotik) {
            $this->connectUsingModel($hostOrModel);
        } else {
            $host = $hostOrModel;
            $this->connectUsingCreds(
                $host,
                $username ?? config('mikrotik.username'),
                $password ?? config('mikrotik.password'),
                $port ?? (int) config('mikrotik.port', 8728)
            );
        }
    }

    protected function connectUsingModel(TenantMikrotik $mikrotik): void
    {
        if (!$mikrotik->ip_address || !$mikrotik->api_username || !$mikrotik->api_password) {
            $this->client = null;
            return;
        }

        $cfg = new RouterConfig([
            'host' => $mikrotik->ip_address,
            'port' => $mikrotik->api_port ?? 8728,
            'user' => $mikrotik->api_username,
            'pass' => $mikrotik->api_password,
            'timeout' => 10,
        ]);
        $this->client = new Client($cfg);
    }

    protected function connectUsingCreds(string $host, string $username, string $password, int $port): void
    {
        $cfg = new RouterConfig([
            'host' => $host,
            'port' => $port,
            'user' => $username,
            'pass' => $password,
            'timeout' => 10,
        ]);
        $this->client = new Client($cfg);
    }

    public function isConnected(): bool
    {
        return $this->client !== null;
    }

    /**
     * Get online users from Hotspot and PPP active tables
     * @return array{hotspot: array<int, array>, ppp: array<int, array>}
     */
    public function getOnlineUsers(): array
    {
        if (!$this->client) return ['hotspot' => [], 'ppp' => []];

        $hotspot = [];
        $ppp = [];
        try {
            $q = new Query('/ip/hotspot/active/print');
            $hotspot = $this->client->query($q)->read();
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $q = new Query('/ppp/active/print');
            $ppp = $this->client->query($q)->read();
        } catch (\Throwable $e) {
            // ignore
        }
        return [
            'hotspot' => $hotspot ?? [],
            'ppp' => $ppp ?? [],
        ];
    }

    /**
     * Suspend a user in Hotspot or PPP (by internal .id or by name)
     */
    public function suspendUser(string $type, string $identifier): bool
    {
        return $this->setUserDisabled($type, $identifier, true);
    }

    /**
     * Unsuspend a user in Hotspot or PPP (by internal .id or by name)
     */
    public function unsuspendUser(string $type, string $identifier): bool
    {
        return $this->setUserDisabled($type, $identifier, false);
    }

    /**
     * Delete a user (Hotspot user or PPP secret)
     */
    public function deleteUser(string $type, string $identifier): bool
    {
        if (!$this->client) return false;
        try {
            if ($type === 'hotspot') {
                $id = $this->resolveId('/ip/hotspot/user/print', $identifier);
                if (!$id) return false;
                $q = new Query('/ip/hotspot/user/remove');
                $q->equal('.id', $id);
                $this->client->query($q)->read();
                return true;
            }
            // assume PPP
            $id = $this->resolveId('/ppp/secret/print', $identifier);
            if (!$id) return false;
            $q = new Query('/ppp/secret/remove');
            $q->equal('.id', $id);
            $this->client->query($q)->read();
            return true;
        } catch (\Throwable $e) {
            \Log::warning('Mikrotik deleteUser failed: '.$e->getMessage());
            return false;
        }
    }

    protected function setUserDisabled(string $type, string $identifier, bool $disabled): bool
    {
        if (!$this->client) return false;
        try {
            if ($type === 'hotspot') {
                $id = $this->resolveId('/ip/hotspot/user/print', $identifier);
                if (!$id) return false;
                $q = new Query('/ip/hotspot/user/set');
                $q->equal('.id', $id);
                $q->equal('disabled', $disabled ? 'yes' : 'no');
                $this->client->query($q)->read();
                return true;
            }
            // assume PPP
            $id = $this->resolveId('/ppp/secret/print', $identifier);
            if (!$id) return false;
            $q = new Query('/ppp/secret/set');
            $q->equal('.id', $id);
            $q->equal('disabled', $disabled ? 'yes' : 'no');
            $this->client->query($q)->read();
            return true;
        } catch (\Throwable $e) {
            \Log::warning('Mikrotik setUserDisabled failed: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Try resolving RouterOS internal .id by either .id or name
     */
    protected function resolveId(string $printPath, string $identifier): ?string
    {
        // If identifier looks like an internal .id (starts with *), try direct
        try {
            if (strlen($identifier) > 0 && $identifier[0] === '*') {
                return $identifier;
            }
            $q = new Query($printPath);
            $q->where('name', $identifier);
            $res = $this->client?->query($q)->read() ?? [];
            if (!empty($res)) {
                return $res[0]['.id'] ?? null;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }
}
