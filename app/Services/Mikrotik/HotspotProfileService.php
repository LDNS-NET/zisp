<?php

namespace App\Services\Mikrotik;

use App\Models\Package;
use RouterOS\Query;
use Exception;

class HotspotProfileService
{
    public function __construct(protected RouterApiService $api) {}

    /**
     * Create a new hotspot profile on MikroTik
     */
    public function createProfile(Package $package): void
    {
        $this->syncFromPackage($package);
    }

    /**
     * Ensure hotspot profile exists & is updated from package
     */
    public function syncFromPackage(Package $package): void
    {
        if ($package->type !== 'hotspot') {
            throw new Exception('Package is not a hotspot package');
        }

        if (!$package->mikrotik_profile) {
            throw new Exception('Package has no mikrotik_profile set');
        }

        $rateLimit = "{$package->upload_speed}M/{$package->download_speed}M";
        $sessionTimeout = $this->toSeconds(
            $package->duration_value,
            $package->duration_unit
        );

        $client = $this->api->connect()
            ? $this->api
            : throw new Exception('Router is offline');

        // Check if profile exists
        $profiles = $client->getClient()
            ->query('/ip/hotspot/user/profile/print')
            ->read();

        $existing = collect($profiles)
            ->firstWhere('name', $package->mikrotik_profile);

        if ($existing) {
            // Update profile
            $query = (new Query('/ip/hotspot/user/profile/set'))
                ->equal('.id', $existing['.id'])
                ->equal('rate-limit', $rateLimit)
                ->equal('shared-users', (string)$package->device_limit)
                ->equal('session-timeout', $sessionTimeout);

            $client->getClient()->query($query)->read();
        } else {
            // Create profile
            $query = (new Query('/ip/hotspot/user/profile/add'))
                ->equal('name', $package->mikrotik_profile)
                ->equal('rate-limit', $rateLimit)
                ->equal('shared-users', (string)$package->device_limit)
                ->equal('session-timeout', $sessionTimeout);

            $client->getClient()->query($query)->read();
        }
    }

    /**
     * Delete a hotspot profile by name
     */
    public function deleteProfile(string $profileName): void
    {
        $client = $this->api->connect()
            ? $this->api
            : throw new Exception('Router is offline');

        $profiles = $client->getClient()
            ->query('/ip/hotspot/user/profile/print')
            ->read();

        $existing = collect($profiles)
            ->firstWhere('name', $profileName);

        if ($existing) {
            $query = (new Query('/ip/hotspot/user/profile/remove'))
                ->equal('.id', $existing['.id']);

            $client->getClient()->query($query)->read();
        }
    }

    protected function toSeconds(int $value, string $unit): int
    {
        return match ($unit) {
            'minutes' => $value * 60,
            'hours'   => $value * 3600,
            'days'    => $value * 86400,
            default   => throw new Exception("Invalid duration unit: {$unit}")
        };
    }
}
