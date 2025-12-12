<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class WinboxPortService
{
    // Range for Winbox proxy ports
    const PORT_RANGE_START = 50000;
    const PORT_RANGE_END = 60000;
    
    // Server VPN Interface IP (Gateway)
    const SERVER_VPN_IP = '10.100.0.1';

    /**
     * Ensure the router has a valid Winbox mapping and apply firewall rules.
     */
    public function ensureMapping(TenantMikrotik $router)
    {
        // Assign port if missing
        if (!$router->winbox_port) {
            $this->assignPort($router);
        }
        
        // Ensure server IP is set (entry point)
        // We use the configured server IP from env
        $serverIp = env('WG_SERVER_PUBLIC_IP') ?? config('app.server_ip') ?? request()->server('SERVER_ADDR');
        if ($router->public_ip !== $serverIp) {
            $router->public_ip = $serverIp;
            $router->save();
        }

        if (!$router->wireguard_address) {
            Log::warning("WinboxPortService: Router {$router->id} has no VPN IP. Assigned port {$router->winbox_port} but skipping firewall rules.");
            return;
        }

        $this->applyFirewallRules($router);
    }

    /**
     * Remove mapping and firewall rules.
     */
    public function removeMapping(TenantMikrotik $router)
    {
        $this->removeFirewallRules($router);
        
        $router->winbox_port = null;
        $router->public_ip = null;
        $router->save();
    }

    /**
     * Assign a unique available port.
     */
    protected function assignPort(TenantMikrotik $router)
    {
        // Find used ports (ignore scopes to ensure global uniqueness)
        $usedPorts = TenantMikrotik::withoutGlobalScopes()->whereNotNull('winbox_port')->pluck('winbox_port')->toArray();
        
        // Find first available port
        $port = self::PORT_RANGE_START;
        while (in_array($port, $usedPorts) && $port <= self::PORT_RANGE_END) {
            $port++;
        }

        if ($port > self::PORT_RANGE_END) {
            throw new \Exception("No available Winbox proxy ports in range " . self::PORT_RANGE_START . "-" . self::PORT_RANGE_END);
        }

        $router->winbox_port = $port;
        $router->save();
        
        Log::info("WinboxPortService: Assigned port {$port} to router {$router->id}");
    }

    /**
     * Apply iptables rules for NAT forwarding.
     */
    public function applyFirewallRules(TenantMikrotik $router)
    {
        if (!$router->winbox_port || !$router->wireguard_address) return;

        $port = $router->winbox_port;
        $targetIp = $router->wireguard_address;
        
        // DNAT: Incoming custom port -> Router VPN IP : 8291
        // Using -I to insert at top to ensure precedence, or -A check logic
        // We will use specialized chains if possible, but for simplicity here we use PREROUTING/POSTROUTING directly
        // Ideally we should check if rule exists, but iptables -C can differ by version.
        // A safer idempotent approach: Delete then Add.

        $this->runIptables("nat", ["-D", "PREROUTING", "-p", "tcp", "--dport", $port, "-j", "DNAT", "--to-destination", "{$targetIp}:8291"]);
        $this->runIptables("nat", ["-I", "PREROUTING", "-p", "tcp", "--dport", $port, "-j", "DNAT", "--to-destination", "{$targetIp}:8291"]);

        // SNAT: Traffic bounding to router needs to appear as coming from Server VPN IP
        // so the router sends the reply back through the VPN tunnel
        $this->runIptables("nat", ["-D", "POSTROUTING", "-p", "tcp", "-d", $targetIp, "--dport", "8291", "-j", "SNAT", "--to-source", self::SERVER_VPN_IP]);
        $this->runIptables("nat", ["-I", "POSTROUTING", "-p", "tcp", "-d", $targetIp, "--dport", "8291", "-j", "SNAT", "--to-source", self::SERVER_VPN_IP]);
        
        // FORWARD: Allow traffic
        $this->runIptables("filter", ["-D", "FORWARD", "-p", "tcp", "-d", $targetIp, "--dport", "8291", "-j", "ACCEPT"]);
        $this->runIptables("filter", ["-I", "FORWARD", "-p", "tcp", "-d", $targetIp, "--dport", "8291", "-j", "ACCEPT"]);
        
        Log::info("WinboxPortService: Applied firewall rules for port {$port} -> {$targetIp}:8291");
    }

    /**
     * Remove iptables rules.
     */
    public function removeFirewallRules(TenantMikrotik $router)
    {
        if (!$router->winbox_port || !$router->wireguard_address) return;

        $port = $router->winbox_port;
        $targetIp = $router->wireguard_address;

        // Delete rules (suppress errors if they don't exist)
        $this->runIptables("nat", ["-D", "PREROUTING", "-p", "tcp", "--dport", $port, "-j", "DNAT", "--to-destination", "{$targetIp}:8291"]);
        $this->runIptables("nat", ["-D", "POSTROUTING", "-p", "tcp", "-d", $targetIp, "--dport", "8291", "-j", "SNAT", "--to-source", self::SERVER_VPN_IP]);
        $this->runIptables("filter", ["-D", "FORWARD", "-p", "tcp", "-d", $targetIp, "--dport", "8291", "-j", "ACCEPT"]);
        
        Log::info("WinboxPortService: Removed firewall rules for port {$port}");
    }

    /**
     * Helper to run iptables via sudo.
     */
    protected function runIptables($table, array $args)
    {
        // Current user (www-data) needs sudo NOPASSWD for iptables in /etc/sudoers
        $command = array_merge(['sudo', 'iptables', '-t', $table], $args);
        
        try {
            $result = Process::run($command);
            if ($result->failed()) {
                // Log error but generally continue - maybe rule didn't exist during delete
                if (!str_contains($result->errorOutput(), 'Bad rule') && !str_contains($result->errorOutput(), 'No chain/target/match')) {
                    Log::error("iptables error: " . $result->errorOutput());
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to run iptables: " . $e->getMessage());
        }
    }
    
    /**
     * Rebuild all rules (e.g. on server restart).
     */
    public function rebuildAllRules()
    {
        $routers = TenantMikrotik::whereNotNull('winbox_port')->whereNotNull('wireguard_address')->get();
        Log::info("WinboxPortService: Rebuilding rules for " . $routers->count() . " routers.");
        
        foreach ($routers as $router) {
            $this->applyFirewallRules($router);
        }
    }
}
