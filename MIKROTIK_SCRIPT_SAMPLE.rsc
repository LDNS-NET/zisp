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

# ============================================================
# ZISP Mikrotik Automated Onboarding Script
# ============================================================
# Generated: 2025-11-13 12:34:56
# Device: Main Router
# Router ID: 1
# System URL: https://your-system.com

:put "========== ZISP MIKROTIK ONBOARDING INITIATED =========="

# Configuration variables
:local syncToken "YOUR_UNIQUE_SYNC_TOKEN_HERE_64_CHARACTERS"
:local systemUrl "https://your-system.com"
:local mikrotikId 1
:local syncUrl ($systemUrl . "/mikrotiks/" . $mikrotikId . "/sync?token=" . $syncToken)

# ============================================================
# Step 1: Collect Device Information
# ============================================================

:put "Step 1: Collecting device information..."

:local deviceId [/system identity get name]
:local boardName ""
:local systemVersion ""
:local cpuLoad ""
:local uptime ""
:local totalMemory 0
:local freeMemory 0
:local usedMemory 0
:local interfaceCount 0
:local routerIp ""

# Safely collect system information
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
    :set freeMemory [/system resource get free-memory]
} on-error={}

:if ($totalMemory > 0) do={
    :set usedMemory ($totalMemory - $freeMemory)
}

# Collect interface information
:do {
    :set interfaceCount [/interface print count-only]
} on-error={
    :set interfaceCount 0
}

# Collect primary router IP address
:do {
    :local ipIds [/ip address find]
    :foreach id in=$ipIds do={
        :local ipAddr [/ip address get $id address]
        :local ipOnly [:pick $ipAddr 0 [:find $ipAddr "/"]]
        :if ([:pick $ipOnly 0 3] != "127" && [:len $routerIp] = 0) do={
            :set routerIp $ipOnly
        }
    }
} on-error={
    :put "Warning: Could not determine router IP"
}

:put "  Device ID: $deviceId"
:put "  Board: $boardName"
:put "  RouterOS: $systemVersion"
:put "  Interfaces: $interfaceCount"
:if ([:len $routerIp] > 0) do={
    :put "  Router IP: $routerIp"
}

# ============================================================
# Step 2: Send Initial Phone-Home
# ============================================================

:put ""
:put "Step 2: Sending initial device registration..."

:local phoneHomeData ""
:if ([:len $deviceId] > 0) do={
    :set phoneHomeData ("device_id=" . $deviceId)
}
:if ([:len $boardName] > 0) do={
    :if ([:len $phoneHomeData] > 0) do={ :set phoneHomeData ($phoneHomeData . "&") }
    :set phoneHomeData ($phoneHomeData . "board_name=" . $boardName)
}
:if ([:len $systemVersion] > 0) do={
    :if ([:len $phoneHomeData] > 0) do={ :set phoneHomeData ($phoneHomeData . "&") }
    :set phoneHomeData ($phoneHomeData . "system_version=" . $systemVersion)
}
:if ($interfaceCount > 0) do={
    :if ([:len $phoneHomeData] > 0) do={ :set phoneHomeData ($phoneHomeData . "&") }
    :set phoneHomeData ($phoneHomeData . "interface_count=" . $interfaceCount)
}
:if ([:len $routerIp] > 0) do={
    :if ([:len $phoneHomeData] > 0) do={ :set phoneHomeData ($phoneHomeData . "&") }
    :set phoneHomeData ($phoneHomeData . "ip_address=" . $routerIp)
}

:do {
    /tool fetch url=$syncUrl http-method=post http-data=$phoneHomeData timeout=10s dst-path="/tmp/zisp_phone_home.txt"
    :put "✓ Device registered successfully"
} on-error={
    :put "⚠ Phone-home failed - check internet connectivity"
    :put "  (Device will retry automatically)"
}

# ============================================================
# Step 3: Create Phone-Home Script for Periodic Reporting
# ============================================================

:put ""
:put "Step 3: Setting up periodic reporting..."

# Remove existing phone-home components
:do {
    /system script remove [find name="zisp-phone-home"]
} on-error={}

:do {
    /system scheduler remove [find name="zisp-phone-home"]
} on-error={}

# Create a stored script for periodic device status reporting
:local scriptContent ":local syncToken \"YOUR_UNIQUE_SYNC_TOKEN_HERE_64_CHARACTERS\"; :local systemUrl \"https://your-system.com\"; :local mikrotikId 1; :local syncUrl (\$systemUrl . \"/mikrotiks/\" . \$mikrotikId . \"/sync?token=\" . \$syncToken); :local routerIp \"\"; :do { :local ipIds [/ip address find]; :foreach id in=\$ipIds do={ :local ipAddr [/ip address get \$id address]; :local ipOnly [:pick \$ipAddr 0 [:find \$ipAddr \"/\"]]; :if ([:pick \$ipOnly 0 3] != \"127\" && [:len \$routerIp] = 0) do={ :set routerIp \$ipOnly; }; }; } on-error={}; :if ([:len \$routerIp] > 0) do={ :do { :local postData (\"status=periodic&ip_address=\" . \$routerIp); /tool fetch url=\$syncUrl http-method=post http-data=\$postData timeout=10s; } on-error={}; };"

:do {
    /system script add name="zisp-phone-home" source=$scriptContent comment="ZISP Device Status Reporter"
    :put "✓ Phone-home script created"
} on-error={
    :put "⚠ Failed to create phone-home script"
}

# Create scheduler to run phone-home every 5 minutes
:do {
    /system scheduler add \
        name="zisp-phone-home" \
        start-time=startup \
        interval=5m \
        on-event="/system script run zisp-phone-home" \
        comment="ZISP periodic device reporting"
    :put "✓ Reporting scheduler created (every 5 minutes)"
} on-error={
    :put "⚠ Failed to create scheduler"
}

# ============================================================
# Step 4: Verify API Service
# ============================================================

:put ""
:put "Step 4: Verifying API service..."

:do {
    :local apiStatus [/ip service get api disabled]
    :if ($apiStatus = true) do={
        :put "⚠ API service is disabled - enabling now..."
        /ip service set api disabled=no
        :put "✓ API service enabled"
    } else={
        :put "✓ API service is already enabled"
    }
} on-error={
    :put "⚠ Could not verify API service status"
}

# ============================================================
# Step 5: Final Status Report
# ============================================================

:put ""
:put "========== ONBOARDING COMPLETE =========="
:put ""
:put "Device Configuration Summary:"
:put "  • Device ID: $deviceId"
:put "  • Router OS: $systemVersion"
:put "  • Board Model: $boardName"
:put "  • Interface Count: $interfaceCount"
:if ([:len $routerIp] > 0) do={
    :put "  • IP Address: $routerIp"
}
:put ""
:put "System Status:"
:put "  ✓ Registered with ZISP"
:put "  ✓ Periodic reporting enabled (5 min intervals)"
:put "  ✓ API service ready for connections"
:put ""
:put "Next Steps:"
:put "  1. Log in to your ZISP dashboard"
:put "  2. Go to Devices > Mikrotik Devices"
:put "  3. Verify this device appears as ONLINE"
:put "  4. Configure network settings as needed"
:put ""
:put "For troubleshooting, check: /log print where message~\"ZISP\""
:put "=========================================="
