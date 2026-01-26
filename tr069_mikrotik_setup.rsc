# ZISP TR-069 Auto-Provisioning Script for MikroTik
# Replace the variables below with your actual ACS server details

:local acsUrl "http://213.199.41.117:7547"
:local acsUser "admin"
:local acsPass "Niceone2025"
:local informInterval "300"

:put "======= Configuring TR-069 Client ======="
:do {
    /tr069 client set enabled=yes url=$acsUrl username=$acsUser password=$acsPass periodic-inform-interval=$informInterval periodic-inform-enabled=yes
    :log info "ZISP: TR-069 Configuration applied. Reporting to $acsUrl"
    :put "Success: TR-069 enabled and reporting to $acsUrl"
} on-error={
    :put "Error: Could not configure TR-069. Ensure the tr069 package is installed."
    :log error "ZISP: Failed to configure TR-069 client"
}
