# ============================================================
# ZISP Mikrotik Automated Onboarding Script - Sample
# ============================================================
# This is a sample of what gets generated for each device.
# Each real script will have unique tokens and device configuration.
#
# Usage:
# 1. Download the actual script from ZISP dashboard
# 2. Copy and paste into Mikrotik terminal, or
# 3. Upload to Mikrotik and execute: /import file-name=script.rsc
# 
# The script will automatically:
# - Collect device information
# - Register with ZISP system
# - Set up periodic status reporting
# ============================================================

# Auto-generated Mikrotik Onboarding Script
# This script will self-onboard your Mikrotik device with the ZISP system
# Generated: 2025-11-13 12:34:56
# Device: Main Router
# Documentation: https://your-system.com/docs/mikrotik

:local syncToken "YOUR_UNIQUE_SYNC_TOKEN_HERE_64_CHARACTERS"
:local systemUrl "https://your-system.com"
:local mikrotikId 1
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
:local schedulerCommand "/tool fetch url=(\"https://your-system.com/mikrotiks/1/sync?token=YOUR_UNIQUE_SYNC_TOKEN_HERE_64_CHARACTERS&status=periodic\") method=post dst-path=\"/tmp/zisp_periodic.txt\" timeout=10s"

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

# ============================================================
# Script Complete
# ============================================================
# Your device should now appear in the ZISP dashboard.
# Status will update automatically every 5 minutes.
# Check the Logs section in ZISP for any errors.
