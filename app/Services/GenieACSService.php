<?php

namespace App\Services;

use App\Models\Tenants\TenantDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenieACSService
{
    protected string $baseUrl;
    protected ?string $username;
    protected ?string $password;

    public function __construct()
    {
        $this->baseUrl = config('services.genieacs.nbi_url');
        $this->username = config('services.genieacs.username');
        $this->password = config('services.genieacs.password');
    }

    /**
     * Get a list of devices from GenieACS.
     */
    public function getDevices(array $query = [])
    {
        try {
            // Fetch projections to minimize data transfer
            $projections = [
                '_id', '_lastInform', '_ip',
                'InternetGatewayDevice.DeviceInfo.Manufacturer', 
                'InternetGatewayDevice.DeviceInfo.ModelName',
                'Device.DeviceInfo.Manufacturer',
                'Device.DeviceInfo.ModelName'
            ];
            
            $response = $this->client()->get('/devices', [
                'query' => json_encode($query),
                'projection' => implode(',', $projections)
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('GenieACS NBI: Failed to fetch devices', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('GenieACS NBI: Exception fetching devices', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Get a single device by ID (Serial or GenieACS _id).
     */
    public function getDevice(string $deviceId)
    {
        try {
            $response = $this->client()->get("/devices/" . urlencode($deviceId));

            if ($response->successful()) {
                return $response->json()[0] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('GenieACS NBI: Exception fetching device', [
                'deviceId' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Trigger a reboot task.
     */
    public function reboot(string $deviceId): bool
    {
        return $this->createTask($deviceId, 'reboot');
    }

    /**
     * Trigger a factory reset task.
     */
    public function factoryReset(string $deviceId): bool
    {
        return $this->createTask($deviceId, 'factoryReset');
    }

    /**
     * Update device parameters.
     */
    public function setParameterValues(string $deviceId, array $parameters): bool
    {
        return $this->createTask($deviceId, 'setParameterValues', [
            'parameterValues' => $parameters
        ]);
    }

    /**
     * Generic method to create a task in GenieACS.
     */
    protected function createTask(string $deviceId, string $name, array $properties = []): bool
    {
        try {
            $response = $this->client()->post("/devices/" . urlencode($deviceId) . "/tasks", [
                'name' => $name,
                ...$properties
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error("GenieACS NBI: Failed to trigger task {$name}", [
                'deviceId' => $deviceId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error("GenieACS NBI: Exception triggering task {$name}", [
                'deviceId' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    /**
     * Synchronize a local TenantDevice with GenieACS data.
     */
    public function syncDevice(TenantDevice $device, ?array $remoteData = null): bool
    {
        if (!$remoteData) {
            $remoteData = $this->getDevice($device->serial_number);
        }

        if (!$remoteData) {
            $device->update(['online' => false]);
            return false;
        }

        // Map GenieACS data to local model
        $root = isset($remoteData['InternetGatewayDevice']) ? 'InternetGatewayDevice' : 'Device';
        
        $device->update([
            'manufacturer' => $this->getValue($remoteData, "{$root}.DeviceInfo.Manufacturer") ?? $device->manufacturer,
            'model' => $this->getValue($remoteData, "{$root}.DeviceInfo.ModelName") ?? $device->model,
            'software_version' => $this->getValue($remoteData, "{$root}.DeviceInfo.SoftwareVersion") ?? $device->software_version,
            'mac_address' => $this->getValue($remoteData, "{$root}.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress") 
                          ?? $this->getValue($remoteData, "{$root}.Ethernet.Interface.1.MACAddress") 
                          ?? $device->mac_address,
            'wan_ip' => $this->getValue($remoteData, "{$root}.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress") 
                     ?? $this->getValue($remoteData, "{$root}.IP.Interface.1.IPv4Address.1.IPAddress") 
                     ?? $device->wan_ip,
            'lan_ip' => $this->getValue($remoteData, "{$root}.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress") 
                     ?? $this->getValue($remoteData, "{$root}.IP.Interface.2.IPv4Address.1.IPAddress") 
                     ?? $device->lan_ip,
            'last_contact_at' => isset($remoteData['_lastInform']) ? \Carbon\Carbon::parse($remoteData['_lastInform']) : now(),
            'online' => isset($remoteData['_lastInform']) && now()->diffInMinutes(\Carbon\Carbon::parse($remoteData['_lastInform'])) < 10,
        ]);

        return true;
    }

    /**
     * Discover devices in GenieACS that are not yet in our database.
     */
    public function discoverNewDevices(): int
    {
        $remoteDevices = $this->getDevices();
        $count = 0;

        foreach ($remoteDevices as $remote) {
            $serial = $remote['_id'] ?? null;
            if (!$serial) continue;

            if (!TenantDevice::where('serial_number', $serial)->exists()) {
                $device = new TenantDevice(['serial_number' => $serial]);
                if ($this->syncDevice($device, $remote)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    protected function getValue(array $data, string $path)
    {
        // Recursively or directly find the value in the nested array
        return $data[$path]['_value'] ?? null;
    }

    protected function client()
    {
        $client = Http::baseUrl($this->baseUrl);

        if ($this->username && $this->password) {
            $client->withBasicAuth($this->username, $this->password);
        }

        return $client;
    }
}
