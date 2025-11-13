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

        // RouterOS 6.48+ and 7.x compatible script
        $script = <<<'RSC'
# Auto-generated Mikrotik Onboarding Script
# This script will self-onboard your Mikrotik device with the ZISP system
# Generated: {{TIMESTAMP}}
# Device: {{DEVICE_NAME}}
# Documentation: https://your-system.com/docs/mikrotik

:local syncToken "{{SYNC_TOKEN}}"
:local systemUrl "{{SYSTEM_URL}}"
:local mikrotikId {{MIKROTIK_ID}}
:local timestamp [/system clock get date-time]

# ============================================================
# Step 1: Collect Device Information
# ============================================================

:local deviceId [/system identity get name]
:local boardName ""
:local systemVersion ""
:local cpuLoad ""
:local uptime ""
:local totalMemory ""
:local freeMemory ""
:local usedMemory ""

# Safely collect system information with error handling
:do {
    :set boardName [/system routerboard get board-name]
} on-error={
    :set boardName "Unknown"
}

:do {
    :set systemVersion [/system resource get version]
} on-error={
    :set systemVersion "Unknown"
}

:do {
    :set cpuLoad [/system resource get cpu-load]
} on-error={
    :set cpuLoad 0
}

:do {
    :set uptime [/system resource get uptime]
} on-error={
    :set uptime "Unknown"
}

:do {
    :set totalMemory [/system resource get total-memory]
} on-error={
    :set totalMemory 0
}

:do {
    :set freeMemory [/system resource get free-memory]
} on-error={
    :set freeMemory 0
}

:set usedMemory ($totalMemory - $freeMemory)

# Calculate memory percentage
:local memoryUsagePercent 0
:if ($totalMemory > 0) do={
    :set memoryUsagePercent (($usedMemory * 100) / $totalMemory)
}

# Collect interface count
:local interfaceCount [/interface print count-only]

# Collect MAC addresses (first 5 interfaces)
:local macAddresses ""
:local interfaceList [/interface find]
:local maxMacs 5
:local macCount 0

:foreach interfaceId in=$interfaceList do={
    :if ($macCount < $maxMacs) do={
        :local mac [/interface get $interfaceId mac-address]
        :if ($macAddresses = "") do={
            :set macAddresses $mac
        } else={
            :set macAddresses ($macAddresses . "," . $mac)
        }
        :set macCount ($macCount + 1)
    }
}

# Collect DNS servers
:local dnsServers ""
:local dnsConfig [/ip dns get]
:local primaryDns [/ip dns get primary-dns]
:local secondaryDns [/ip dns get secondary-dns]

:if ($primaryDns != "0.0.0.0") do={
    :set dnsServers $primaryDns
}

:if ($secondaryDns != "0.0.0.0") do={
    :if ($dnsServers != "") do={
        :set dnsServers ($dnsServers . "," . $secondaryDns)
    } else={
        :set dnsServers $secondaryDns
    }
}

# ============================================================
# Step 2: Prepare and Send Initial Sync
# ============================================================

:log info "ZISP Onboarding: Initiating device sync..."

# Build query string (simpler than form data)
:local queryString ""
:set queryString ("device_id=" . $deviceId . "&board_name=" . $boardName . "&system_version=" . $systemVersion)
:set queryString ($queryString . "&interface_count=" . $interfaceCount . "&cpu_load=" . $cpuLoad . "&uptime=" . $uptime)
:set queryString ($queryString . "&total_memory=" . $totalMemory . "&used_memory=" . $usedMemory . "&memory_usage_percent=" . $memoryUsagePercent)
:set queryString ($queryString . "&mac_addresses=" . $macAddresses . "&dns_servers=" . $dnsServers . "&timestamp=" . $timestamp)

:local syncUrl ($systemUrl . "/mikrotiks/" . $mikrotikId . "/sync?token=" . $syncToken . "&" . $queryString)

# Send initial sync
:do {
    /tool fetch url=$syncUrl method=post dst-path="/tmp/zisp_sync.txt" timeout=10s
    :log info "ZISP Onboarding: Device information sent to system"
} on-error={
    :log error "ZISP Onboarding: Failed to send initial sync - check network connectivity"
}

# ============================================================
# Step 3: Set Up Periodic Status Reporting
# ============================================================

:log info "ZISP Onboarding: Setting up periodic sync scheduler..."

# Remove existing scheduler if present
:do {
    /system scheduler remove [find name="zisp-device-status"]
} on-error={}

# Create a simple scheduler command (stored in a variable to avoid syntax issues)
:local schedulerCommand "/tool fetch url=(\"{{SYSTEM_URL}}/mikrotiks/{{MIKROTIK_ID}}/sync?token={{SYNC_TOKEN}}&status=periodic\") method=post dst-path=\"/tmp/zisp_periodic.txt\" timeout=10s"

# Add the scheduler
:do {
    /system scheduler add \
        name="zisp-device-status" \
        on-event=$schedulerCommand \
        interval=5m \
        start-time=startup \
        comment="ZISP-Device-Status-Reporter"
    :log info "ZISP Onboarding: Scheduler created successfully"
} on-error={
    :log error "ZISP Onboarding: Failed to create scheduler"
}

# ============================================================
# Step 4: Completion
# ============================================================

:log info "ZISP Onboarding: Setup complete!"
:log info "ZISP Onboarding: Device will report status every 5 minutes"
:log info "ZISP Onboarding: Monitor logs for 'ZISP' entries for troubleshooting"
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
