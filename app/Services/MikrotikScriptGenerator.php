<?php

namespace App\Services;

class MikrotikScriptGenerator
{
    /**
     * Generate a full, system-ready onboarding script for Mikrotik routers.
     *
     * @param array $options
     *   - name: Desired router name
     *   - username: API/system username
     *   - router_password: API/system password
     *   - router_id: Router database ID
     *   - tenant_id: Tenant ID (optional)
     *   - ca_url: OpenVPN CA certificate URL (optional)
     *   - radius_ip: RADIUS server IP (optional)
     *   - radius_secret: RADIUS shared secret (optional)
     *   - sync_token: Router sync token (optional)
     *   - trusted_ip: Trusted IP for API/SSH/Winbox access (optional)
     *   - snmp_community: SNMP community name (optional)
     *   - snmp_location: SNMP location (optional)
     * @return string
     */
    public function generate(array $options): string
    {
        $name = $options['name'] ?? 'ISP-Managed';
        $username = $options['username'] ?? 'apiuser';
        $router_password = $options['router_password'] ?? 'apipassword';
        $router_id = $options['router_id'] ?? 'ROUTER_ID';
        $tenant_id = $options['tenant_id'] ?? 'TENANT_ID';
        $ca_url = $options['ca_url'] ?? null;

        if (!$ca_url && !empty($router_id)) {
            $ca_url = route('mikrotiks.downloadCACert', ['mikrotik' => $router_id]);
        }
        if (!$ca_url) {
            $ca_url = "https://api.example.com/tenant/$tenant_id/ca.crt";
        }

        $radius_ip = $options['radius_ip'] ?? '207.154.204.144';
        $radius_secret = $options['radius_secret'] ?? 'ZyraafSecret123';
        $api_port = $options['api_port'] ?? '8728';
        $sync_token = $options['sync_token'] ?? null;
        $sync_url = $options['sync_url'] ?? null;

        // SNMP defaults
        $snmp_community = $options['snmp_community'] ?? 'public';
        $snmp_location  = $options['snmp_location'] ?? 'ZiSP Network';

        // Build sync_url if not provided
        if (!$sync_url && !empty($router_id)) {
            try {
                $sync_url = url(route('mikrotiks.sync', ['mikrotik' => $router_id], false));
                if ($sync_token) {
                    $sync_url .= "?token=$sync_token";
                }
            } catch (\Exception $e) {
                $baseUrl = config('app.url') ?? (request()->scheme() . '://' . request()->getHttpHost());
                $sync_url = rtrim($baseUrl, '/') . "/mikrotiks/{$router_id}/sync";
                if ($sync_token) {
                    $sync_url .= "?token=$sync_token";
                }
            }
        }

        // Build wg_register_url if not provided
        $wg_register_url = $options['wg_register_url'] ?? null;
        if (!$wg_register_url && !empty($router_id)) {
            try {
                $wg_register_url = url(route('mikrotiks.registerWireguard', ['mikrotik' => $router_id], false));
                if ($sync_token) {
                    $wg_register_url .= "?token=$sync_token";
                }
            } catch (\Exception $e) {
                $baseUrl = config('app.url') ?? (request()->scheme() . '://' . request()->getHttpHost());
                $wg_register_url = rtrim($baseUrl, '/') . "/mikrotiks/{$router_id}/register-wireguard";
                if ($sync_token) {
                    $wg_register_url .= "?token=$sync_token";
                }
            }
        }

        $trusted_ip = $options['trusted_ip'] ?? (request()->server('SERVER_ADDR') ?: '207.154.204.144');
        if (is_string($trusted_ip) && strpos($trusted_ip, '/') === false && filter_var($trusted_ip, FILTER_VALIDATE_IP)) {
            $trusted_ip .= '/32';
        }

        $wg_server_endpoint = $options['wg_server_endpoint'] ?? config('wireguard.server_endpoint') ?? env('WG_SERVER_ENDPOINT', '');
        $wg_server_pubkey  = $options['wg_server_pubkey'] ?? config('wireguard.server_public_key') ?? env('WG_SERVER_PUBLIC_KEY', '');
        $wg_subnet         = $options['wg_subnet'] ?? config('wireguard.subnet') ?? env('WG_SUBNET', '10.254.0.0/16');
        $wg_port           = $options['wg_port'] ?? config('wireguard.server_port') ?? env('WG_SERVER_PORT', 51820);
        $wg_client_ip      = $options['wg_client_ip'] ?? '';

        // If client IP not provided, deterministically derive one from subnet + router_id for automation
        if (empty($wg_client_ip) && !empty($router_id) && is_numeric($router_id)) {
            $wg_client_ip = $this->deriveClientIpFromSubnet($wg_subnet, (int)$router_id);
        }

        // Load stub template
        $templatePath = resource_path('scripts/mikrotik_onboarding.rsc.stub');
        $template = file_exists($templatePath) ? file_get_contents($templatePath) : '';
        if (!$template) return '';

        // Replace placeholders in the template
        $replacements = [
            'name' => $name,
            'username' => $username,
            'router_password' => $router_password,
            'router_id' => $router_id,
            'radius_ip' => $radius_ip,
            'radius_secret' => $radius_secret,
            'snmp_community' => $snmp_community,
            'snmp_location' => $snmp_location,
            'api_port' => $api_port,
            'sync_url' => $sync_url ?? '',
            'trusted_ip' => $trusted_ip,
            'wg_server_endpoint' => $wg_server_endpoint,
            'wg_server_pubkey' => $wg_server_pubkey,
            'wg_subnet' => $wg_subnet,
            'wg_port' => $wg_port,
            'wg_client_ip' => $wg_client_ip,
            'wg_register_url' => $wg_register_url ?? '',
        ];

        foreach ($replacements as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }

    /**
     * Generate advanced configuration script for Mikrotik routers.
     */
    public function generateAdvancedConfig(array $options): string
    {
        $name = $options['name'] ?? 'ISP-Managed';
        $router_id = $options['router_id'] ?? 'ROUTER_ID';
        $radius_ip = $options['radius_ip'] ?? '207.154.204.144';
        $radius_secret = $options['radius_secret'] ?? 'ZyraafSecret123';
        $snmp_community = $options['snmp_community'] ?? 'public';
        $snmp_location  = $options['snmp_location'] ?? 'ZiSP Network';
        $api_port = $options['api_port'] ?? '8728';
        $username = $options['username'] ?? 'apiuser';
        $router_password = $options['router_password'] ?? 'apipassword';
        $trusted_ip = $options['trusted_ip'] ?? '0.0.0.0/0';

        $templatePath = resource_path('scripts/mikrotik_advanced_config.rsc.stub');
        $template = file_exists($templatePath) ? file_get_contents($templatePath) : '';
        if (!$template) return '';

        $replacements = [
            'name' => $name,
            'router_id' => $router_id,
            'radius_ip' => $radius_ip,
            'radius_secret' => $radius_secret,
            'snmp_community' => $snmp_community,
            'snmp_location' => $snmp_location,
            'api_port' => $api_port,
            'username' => $username,
            'router_password' => $router_password,
            'trusted_ip' => $trusted_ip,
        ];

        foreach ($replacements as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }

    /**
     * Derive a deterministic client IP inside the given CIDR by offsetting from the network address.
     * We skip the first 1 address (typically reserved for server) and add router_id to avoid collisions.
     * Supports IPv4 CIDRs. Falls back to empty string on failure.
     */
    protected function deriveClientIpFromSubnet(string $cidr, int $routerId): string
    {
        if (strpos($cidr, '/') === false) return '';
        [$network, $prefix] = explode('/', $cidr, 2);
        $prefix = (int)$prefix;
        if ($prefix < 0 || $prefix > 32) return '';
        $netLong = ip2long($network);
        if ($netLong === false) return '';
        $hostBits = 32 - $prefix;
        if ($hostBits <= 0) return '';
        $maxHosts = (1 << $hostBits) - 2; // exclude network and broadcast
        if ($maxHosts < 2) return '';

        // Offset: reserve +1 for server, then +routerId. Wrap within available host range.
        $offset = 1 + ($routerId % $maxHosts) + 1; // +1 reserved, +1 avoid .0
        $candidate = $netLong + $offset;
        $ip = long2ip($candidate);
        return $ip ? ($ip . '/32') : '';
    }
}
