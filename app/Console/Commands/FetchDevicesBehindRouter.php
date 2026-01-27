<?php

namespace App\Console\Commands;

use App\Models\Tenants\TenantMikrotik;
use App\Models\Tenants\TenantDevice;
use App\Services\GenieACSService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchDevicesBehindRouter extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tr069:scan-behind {router_id}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch TR-069 devices reporting via a specific MikroTik\'s public IP';

    /**
     * Execute the console command.
     */
    public function handle(GenieACSService $acs)
    {
        $routerId = $this->argument('router_id');
        $router = TenantMikrotik::withoutGlobalScopes()->findOrFail($routerId);
        
        $ips = array_filter([
            $router->detected_public_ip,
            $router->public_ip,
            $router->wireguard_address
        ]);

        if (empty($ips)) {
            $this->error("Router {$router->name} has no IPs configured.");
            return Command::FAILURE;
        }

        $this->info("Scanning GenieACS for devices behind IPs: " . implode(', ', $ips));

        // Get MikroTik's local networks (Subnets)
        $mikrotikNetworks = [];
        try {
            $api = new \App\Services\Mikrotik\RouterApiService($router);
            $addresses = $api->getIpAddresses();
            foreach ($addresses as $addr) {
                if (isset($addr['address']) && strpos($addr['address'], '/') !== false) {
                    $mikrotikNetworks[] = $addr['address'];
                }
            }
            if (!empty($mikrotikNetworks)) {
                $this->info("Detected local networks on MikroTik: " . implode(', ', $mikrotikNetworks));
            }
        } catch (\Exception $e) {
            $this->warn("Could not fetch local networks from MikroTik: " . $e->getMessage());
        }

        // Fetch all devices from GenieACS
        $allRemoteDevices = $acs->getDevices();
        $matchedCount = 0;
        $discoveredFromMikrotik = 0;

        $this->info("Checking GenieACS for devices reporting via this site...");
        foreach ($allRemoteDevices as $remote) {
            $serial = $remote['_id'] ?? null;
            if (!$serial) continue;

            $deviceIp = $remote['_ip'] ?? null;
            $root = isset($remote['InternetGatewayDevice']) ? 'InternetGatewayDevice' : 'Device';
            
            if (!$deviceIp) {
                $connUrl = $remote[$root]['ManagementServer']['ConnectionRequestURL']['_value'] ?? null;
                if ($connUrl && preg_match('/:\/\/([0-9\.]+)/', $connUrl, $matches)) {
                    $deviceIp = $matches[1];
                }
            }

            if (!$deviceIp) {
                $deviceIp = $remote[$root]['WANDevice']['1']['WANConnectionDevice']['1']['WANIPConnection']['1']['ExternalIPAddress']['_value'] ?? null;
            }

            if (!$deviceIp) continue;

            $isMatch = in_array($deviceIp, $ips); 
            
            if (!$isMatch && !empty($mikrotikNetworks)) {
                foreach ($mikrotikNetworks as $cidr) {
                    if ($this->ipInNetwork($deviceIp, $cidr)) {
                        $isMatch = true;
                        break;
                    }
                }
            }

            if ($isMatch) {
                $this->info("Found matching TR-069 device: {$serial} (IP: {$deviceIp})");
                $device = TenantDevice::where('serial_number', $serial)->first();
                if (!$device) {
                    $device = TenantDevice::create([
                        'serial_number' => $serial,
                        'tenant_id' => $router->tenant_id,
                        'online' => true,
                        'wan_ip' => $deviceIp,
                        'last_contact_at' => now(),
                    ]);
                } else {
                    $device->update(['wan_ip' => $deviceIp, 'online' => true]);
                }
                $acs->syncDevice($device, $remote);
                $matchedCount++;
            }
        }

        // 2. Discover devices directly from MikroTik (Local Network)
        $this->info("Fetching connected devices from MikroTik (Leases/ARP)...");
        try {
            $api = new \App\Services\Mikrotik\RouterApiService($router);
            $leases = $api->getDhcpLeases();
            $arp = $api->getArpTable();

            $discoveryList = []; // IP => MAC
            foreach ($leases as $l) {
                if (isset($l['address'], $l['mac-address'])) {
                    $discoveryList[$l['address']] = $l['mac-address'];
                }
            }
            foreach ($arp as $a) {
                if (isset($a['address'], $a['mac-address']) && !isset($discoveryList[$a['address']])) {
                    $discoveryList[$a['address']] = $a['mac-address'];
                }
            }

            foreach ($discoveryList as $ip => $mac) {
                // Ignore the router itself or common gateways if needed
                if (in_array($ip, $ips)) continue;

                // Create/Update "discovered" device
                $serial = "DISC-" . str_replace(':', '', $mac);
                $device = TenantDevice::where('mac_address', $mac)->orWhere('serial_number', $serial)->first();
                
                if (!$device) {
                    $device = TenantDevice::create([
                        'serial_number' => $serial,
                        'mac_address' => $mac,
                        'lan_ip' => $ip,
                        'wan_ip' => $router->detected_public_ip ?: $router->public_ip,
                        'tenant_id' => $router->tenant_id,
                        'online' => false, // Discovered but not informing TR-069 yet
                        'last_contact_at' => now(),
                        'manufacturer' => 'Discovered',
                        'model' => 'Network Device',
                    ]);
                    $discoveredFromMikrotik++;
                } else {
                    $device->update(['lan_ip' => $ip, 'wan_ip' => $router->detected_public_ip ?: $router->public_ip]);
                }
            }
        } catch (\Exception $e) {
            $this->warn("MikroTik Discovery failed: " . $e->getMessage());
        }

        $this->info("Scan complete. Found {$matchedCount} TR-069 devices and discovered {$discoveredFromMikrotik} new local devices.");
        return Command::SUCCESS;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    protected function ipInNetwork($ip, $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        [$net, $mask] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $netLong = ip2long($net);
        $maskLong = ~((1 << (32 - (int)$mask)) - 1);

        if ($ipLong === false || $netLong === false) {
            return false;
        }

        return ($ipLong & $maskLong) === ($netLong & $maskLong);
    }
}
