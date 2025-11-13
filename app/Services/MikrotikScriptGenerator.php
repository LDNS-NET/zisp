<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;

class MikrotikScriptGenerator
{
    /**
     * Generate a one-time onboarding script for a Mikrotik device
     *
     * @param TenantMikrotik $mikrotik
     * @param string $systemUrl Base URL of the system (e.g., https://example.com)
     * @return string The RouterOS script
     */
    public function generateScript(TenantMikrotik $mikrotik, string $systemUrl): string
    {
        // Ensure URLs have no trailing slash
        $systemUrl = rtrim($systemUrl, '/');
        
        $syncToken = $mikrotik->sync_token;
        $mikrotikId = $mikrotik->id;
        $syncUrl = "{$systemUrl}/mikrotiks/{$mikrotikId}/sync?token={$syncToken}";

        // RouterOS 7+ compatible script
        $script = <<<'RSC'
# Auto-generated Mikrotik Onboarding Script
# This script will self-onboard your Mikrotik device with the ZISP system
# Generated: {{TIMESTAMP}}
# Device: {{DEVICE_NAME}}

:local syncToken "{{SYNC_TOKEN}}"
:local systemUrl "{{SYSTEM_URL}}"
:local mikrotikId {{MIKROTIK_ID}}

# Collect device information
:local deviceId [/system identity get name]
:local boardName [/system routerboard get board-name]
:local systemVersion [/system package get installed]
:local interfaceCount [/interface ethernet print count-only]
:local cpuLoad [/system resource get cpu-load]
:local uptime [/system resource get uptime]
:local totalMemory [/system resource get total-memory]
:local freeMemory [/system resource get free-memory]
:local usedMemory ($totalMemory - $freeMemory)
:local memoryUsagePercent (($usedMemory * 100) / $totalMemory)

# Collect MAC addresses of first 3 interfaces
:local macAddresses ""
:local interfaceCount [/interface print count-only]
:if ($interfaceCount > 0) do={
    :foreach i in=[/interface find limit=3] do={
        :local mac [/interface get $i mac-address]
        :set macAddresses ($macAddresses . $mac . ";")
    }
}

# Collect DNS information
:local dnsServers ""
:foreach dns in=[/ip dns get servers] do={
    :set dnsServers ($dnsServers . $dns . ";")
}

# Build the sync payload as URL-encoded form data
:local payload ""
:set payload ($payload . "device_id=" . $deviceId)
:set payload ($payload . "&board_name=" . $boardName)
:set payload ($payload . "&interface_count=" . $interfaceCount)
:set payload ($payload . "&cpu_load=" . $cpuLoad)
:set payload ($payload . "&uptime=" . $uptime)
:set payload ($payload . "&total_memory=" . $totalMemory)
:set payload ($payload . "&used_memory=" . $usedMemory)
:set payload ($payload . "&memory_usage_percent=" . $memoryUsagePercent)
:set payload ($payload . "&mac_addresses=" . $macAddresses)
:set payload ($payload . "&dns_servers=" . $dnsServers)
:set payload ($payload . "&timestamp=" . [/system clock get date])

# Send sync data to system
:log info "ZISP Onboarding: Initiating device sync..."
/tool fetch url=($systemUrl . "/mikrotiks/" . $mikrotikId . "/sync?token=" . $syncToken) method=post http-header-field=("Content-Type: application/x-www-form-urlencoded") body=$payload dst-path="/tmp/zisp_response.txt"

:log info "ZISP Onboarding: Device information sent to system"

# Create a scheduler to periodically report status (every 5 minutes)
:log info "ZISP Onboarding: Setting up periodic sync scheduler..."
/system scheduler remove [find comment="ZISP-Device-Status"]

# Store the fetch command in a script file for the scheduler
:local schedulerScript ""
:set schedulerScript "/tool fetch url=(\"{{SYSTEM_URL}}/mikrotiks/{{MIKROTIK_ID}}/sync?token={{SYNC_TOKEN}}\") method=post dst-path=\"/tmp/zisp_status.txt\"; :log info \"ZISP: Device sync report sent.\""

/system scheduler add name="zisp-device-status" on-event=$schedulerScript interval=5m comment="ZISP-Device-Status"

:log info "ZISP Onboarding: Setup complete! Device will report status every 5 minutes."
:log info "ZISP Onboarding: Check your ZISP dashboard for device status updates."
RSC;

        // Replace placeholders with actual values
        $script = str_replace('{{TIMESTAMP}}', now()->format('Y-m-d H:i:s'), $script);
        $script = str_replace('{{DEVICE_NAME}}', $mikrotik->name, $script);
        $script = str_replace('{{SYNC_TOKEN}}', $syncToken, $script);
        $script = str_replace('{{SYSTEM_URL}}', $systemUrl, $script);
        $script = str_replace('{{MIKROTIK_ID}}', $mikrotikId, $script);

        return $script;
    }

    /**
     * Generate a bash script wrapper for Linux/Unix systems to execute on a Mikrotik via SSH
     *
     * @param TenantMikrotik $mikrotik
     * @param string $systemUrl Base URL of the system
     * @param string $routerUsername SSH username for the router
     * @param string $routerHost Router hostname or IP
     * @return string Bash script
     */
    public function generateSSHScript(TenantMikrotik $mikrotik, string $systemUrl, string $routerUsername, string $routerHost): string
    {
        $systemUrl = rtrim($systemUrl, '/');
        $syncToken = $mikrotik->sync_token;
        $mikrotikId = $mikrotik->id;

        return <<<BASH
#!/bin/bash

# ZISP Mikrotik Automated Onboarding Script
# This script connects to your Mikrotik device via SSH and executes the onboarding script
# Usage: ./onboard-mikrotik.sh <router-password>

set -e

ROUTER_HOST="{$routerHost}"
ROUTER_USER="{$routerUsername}"
ROUTER_PASSWORD="\${1:-}"
SYSTEM_URL="{$systemUrl}"
SYNC_TOKEN="{$syncToken}"
MIKROTIK_ID="{$mikrotikId}"

if [ -z "\$ROUTER_PASSWORD" ]; then
    echo "Usage: \$0 <router-password>"
    echo ""
    echo "This script will onboard your Mikrotik device to the ZISP system."
    echo ""
    echo "Arguments:"
    echo "  <router-password>  The admin password for your Mikrotik router"
    exit 1
fi

echo "================================"
echo "ZISP Mikrotik Onboarding Script"
echo "================================"
echo ""
echo "Router: \$ROUTER_HOST"
echo "User: \$ROUTER_USER"
echo "System URL: \$SYSTEM_URL"
echo ""
echo "Connecting to router..."
echo ""

# Generate and execute the RouterOS script via SSH
sshpass -p "\$ROUTER_PASSWORD" ssh -o StrictHostKeyChecking=no \$ROUTER_USER@\$ROUTER_HOST << 'EOF'
$(call_user_func([$this, 'generateScript'], $mikrotik, $systemUrl))
EOF

echo ""
echo "✓ Onboarding script executed successfully!"
echo "✓ Your device should now appear in the ZISP dashboard within 1 minute."
echo ""
echo "Next steps:"
echo "1. Log in to your ZISP dashboard"
echo "2. Navigate to the Mikrotiks section"
echo "3. Verify your device is connected"
echo "4. Configure your network users and packages"
echo ""

BASH;
    }

    /**
     * Get a formatted download filename for the script
     *
     * @param TenantMikrotik $mikrotik
     * @return string Filename
     */
    public function getScriptFilename(TenantMikrotik $mikrotik): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $mikrotik->name ?? 'mikrotik');
        return "zisp_onboarding_{$sanitized}_{$mikrotik->id}.rsc";
    }

    /**
     * Get a formatted download filename for the SSH script
     *
     * @param TenantMikrotik $mikrotik
     * @return string Filename
     */
    public function getSSHScriptFilename(TenantMikrotik $mikrotik): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $mikrotik->name ?? 'mikrotik');
        return "zisp_onboard_{$sanitized}_{$mikrotik->id}.sh";
    }

    /**
     * Store the generated script on the model
     *
     * @param TenantMikrotik $mikrotik
     * @param string $systemUrl
     * @return void
     */
    public function storeScript(TenantMikrotik $mikrotik, string $systemUrl): void
    {
        $script = $this->generateScript($mikrotik, $systemUrl);
        
        $mikrotik->update([
            'onboarding_script_content' => $script,
            'onboarding_status' => 'in_progress',
        ]);
    }
}
