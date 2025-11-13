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

:local syncToken "YOUR_UNIQUE_SYNC_TOKEN_HERE_64_CHARACTERS"
:local systemUrl "https://your-system.com"
:local mikrotikId 1

# ============================================================
# Step 1: Collect device information
# ============================================================

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

# ============================================================
# Step 2: Build and send initial sync
# ============================================================

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

:log info "ZISP Onboarding: Initiating device sync..."
/tool fetch url=($systemUrl . "/mikrotiks/" . $mikrotikId . "/sync?token=" . $syncToken) method=post http-header-field=("Content-Type: application/x-www-form-urlencoded") body=$payload dst-path="/tmp/zisp_response.txt"

:log info "ZISP Onboarding: Device information sent to system"

# ============================================================
# Step 3: Set up periodic status reporting
# ============================================================

:log info "ZISP Onboarding: Setting up periodic sync scheduler..."
/system scheduler remove [find comment="ZISP-Device-Status"]

# Store the fetch command in a script file for the scheduler
:local schedulerScript ""
:set schedulerScript "/tool fetch url=(\"https://your-system.com/mikrotiks/1/sync?token=YOUR_UNIQUE_SYNC_TOKEN_HERE_64_CHARACTERS\") method=post dst-path=\"/tmp/zisp_status.txt\"; :log info \"ZISP: Device sync report sent.\""

/system scheduler add name="zisp-device-status" on-event=$schedulerScript interval=5m comment="ZISP-Device-Status"

:log info "ZISP Onboarding: Setup complete! Device will report status every 5 minutes."
:log info "ZISP Onboarding: Check your ZISP dashboard for device status updates."

# ============================================================
# Script Complete
# ============================================================
# Your device should now appear in the ZISP dashboard.
# Status will update automatically every 5 minutes.
# Check the Logs section in ZISP for any errors.
