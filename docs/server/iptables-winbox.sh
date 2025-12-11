#!/bin/bash

###########################################
# Remote Winbox over WireGuard - IPTables Configuration
# For Ubuntu/Debian servers running WireGuard VPN
###########################################

set -e

echo "========================================="
echo "Remote Winbox IPTables Configuration"
echo "========================================="

# Configuration
WG_INTERFACE="wg0"
WINBOX_PORT="8291"

echo "➤ Checking if WireGuard interface exists..."
if ! ip link show "$WG_INTERFACE" &> /dev/null; then
    echo "❌ Error: WireGuard interface $WG_INTERFACE not found!"
    echo "Please ensure WireGuard is installed and configured."
    exit 1
fi

echo "✓ WireGuard interface $WG_INTERFACE found"

# Allow forwarding between WireGuard peers for Winbox
echo "➤ Adding IPTables rules for Remote Winbox..."

# Remove existing rules (if any)
iptables -D FORWARD -i "$WG_INTERFACE" -o "$WG_INTERFACE" -p tcp --dport "$WINBOX_PORT" -j ACCEPT 2>/dev/null || true
iptables -D INPUT -i "$WG_INTERFACE" -p tcp --dport "$WINBOX_PORT" -j ACCEPT 2>/dev/null || true

# Allow Winbox traffic between WireGuard peers
iptables -A FORWARD -i "$WG_INTERFACE" -o "$WG_INTERFACE" -p tcp --dport "$WINBOX_PORT" -j ACCEPT

# Allow Winbox traffic to the server itself (optional, if server needs to connect)
iptables -A INPUT -i "$WG_INTERFACE" -p tcp --dport "$WINBOX_PORT" -j ACCEPT

echo "✓ IPTables rules added successfully"

# Make rules persistent
echo "➤ Making IPTables rules persistent..."

if command -v netfilter-persistent &> /dev/null; then
    netfilter-persistent save
    echo "✓ Rules saved using netfilter-persistent"
elif command -v iptables-save &> /dev/null; then
    iptables-save > /etc/iptables/rules.v4
    echo "✓ Rules saved to /etc/iptables/rules.v4"
else
    echo "⚠ Warning: Could not auto-save rules. Please manually save iptables configuration."
fi

echo ""
echo "========================================="
echo "✓ Remote Winbox IPTables configuration complete!"
echo "========================================="
echo ""
echo "Current FORWARD rules for Winbox:"
iptables -L FORWARD -n -v | grep "$WINBOX_PORT" || echo "  (No matching rules found)"
echo ""
echo "Current INPUT rules for Winbox:"
iptables -L INPUT -n -v | grep "$WINBOX_PORT" || echo "  (No matching rules found)"
echo ""
echo "Notes:"
echo "  - Winbox port $WINBOX_PORT is now allowed between WireGuard peers"
echo "  - Only authenticated WireGuard clients can access Winbox"
echo "  - No public internet exposure"
echo ""
