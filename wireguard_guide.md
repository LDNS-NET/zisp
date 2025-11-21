cat << 'EOF' > /root/WIREGUARD_MIKROTIK_GUIDE.txt
===========================================
ZiSP WireGuard + MikroTik Integration Guide
===========================================

This document explains, step by step, how to:
- Understand the server-side WireGuard + RADIUS setup
- Configure a MikroTik (RouterOS v7+) to connect to the VPN
- Make RADIUS on the MikroTik side talk through WireGuard
- Test and troubleshoot the setup

All examples assume this environment (already configured on your server):
- WireGuard server public IP: 207.154.232.10
- WireGuard interface on server: wg0
- Server WireGuard IP: 10.8.0.1/24
- WireGuard port: 51820/UDP
- Server WireGuard keys:
  - Private: mLCcQcXclcp4rXVVWdetz2Z2iIWT4b0mMX8ZpbVTQ3w=
  - Public:  yth38MY4uQ9plOsK5hTSJBhU0aIjl4dN8dvtQj21r2g=
- FreeRADIUS is running and listening on 10.8.0.1:1812/1813
- RADIUS secret for MikroTiks: testing123

You can adapt these instructions for any MikroTik as long as you adjust the IPs and keys.

===========================================
1. SERVER-SIDE OVERVIEW (ALREADY DONE)
===========================================

1.1 WireGuard interface configuration
-------------------------------------
File: /etc/wireguard/wg0.conf

[Interface]
PrivateKey = mLCcQcXclcp4rXVVWdetz2Z2iIWT4b0mMX8ZpbVTQ3w=
Address    = 10.8.0.1/24
ListenPort = 51820
PostUp   = iptables -A FORWARD -i wg0 -j ACCEPT; \
           iptables -A FORWARD -o wg0 -j ACCEPT; \
           iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; \
           iptables -D FORWARD -o wg0 -j ACCEPT; \
           iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE

Systemd service: wg-quick@wg0.service is enabled and running.

1.2 Firewall and routing
------------------------
- UFW allows 51820/udp from anywhere (v4 and v6).
- Kernel IPv4 forwarding is enabled (net.ipv4.ip_forward=1).
- NAT is configured to masquerade traffic from wg0 out via eth0.

1.3 FreeRADIUS basics
---------------------
- Service: freeradius.service (running)
- Config root: /etc/freeradius/3.0
- SQL module is configured to use MySQL db "radius".
- NAS table is used to recognize MikroTik devices as RADIUS clients.

For a single MikroTik at 10.8.0.2, you typically have an entry in NAS like:
  nasname = 10.8.0.2
  secret  = testing123
  shortname = mikrotik-1
  type      = other or mikrotik

===========================================
2. BASELINE MIKROTIK WIREGUARD CONFIG (ROUTEROS v7+)
===========================================

This section shows how to configure a MikroTik by hand. Later sections explain how to automate it.

Assume this first MikroTik will use:
- WireGuard interface name:   wg-radius
- MikroTik WireGuard IP:      10.8.0.2/24
- MikroTik WireGuard private: +OwcCgbPTyYqVX46uGr/pzrw7oK3Ng+KPPBv4ssoE1c=
- MikroTik WireGuard public:  +kq6pGYmkeao9SWJ8GCumu/uHGuO47bUE4wL2bwxSWg=

2.1 Create WireGuard interface
------------------------------
On the MikroTik (CLI / WinBox terminal):

/interface/wireguard/add \
    name=wg-radius \
    listen-port=51820 \
    private-key="+OwcCgbPTyYqVX46uGr/pzrw7oK3Ng+KPPBv4ssoE1c="

Notes:
- listen-port on the client is not strictly important for an outbound tunnel,
  but using 51820 keeps things consistent.
- If you want RouterOS to generate keys, omit private-key and then read them with
  /interface/wireguard/print detail.

2.2 Assign IP address to WireGuard interface
-------------------------------------------

/ip/address/add interface=wg-radius address=10.8.0.2/24

2.3 Add server as WireGuard peer
--------------------------------

/interface/wireguard/peers/add \
    interface=wg-radius \
    public-key="yth38MY4uQ9plOsK5hTSJBhU0aIjl4dN8dvtQj21r2g=" \
    allowed-address=10.8.0.1/32 \
    endpoint=207.154.232.10:51820 \
    persistent-keepalive=25s

Explanation:
- public-key: server's WireGuard public key.
- allowed-address: what remote IPs are reachable via this peer.
  For a simple point-to-point tunnel, use the server's WG IP.
- endpoint: public IP and port of the WireGuard server.
- persistent-keepalive: recommended for NAT traversal; 25s is common.

2.4 Add route (if needed)
-------------------------

If you only need to reach the server's WireGuard IP:

/ip/route/add dst-address=10.8.0.1/32 gateway=wg-radius

If you want to route additional networks (e.g., 10.8.0.0/24 or LANs behind the server),
you can add routes accordingly, for example:

/ip/route/add dst-address=10.8.0.0/24 gateway=wg-radius

===========================================
3. CONFIGURING RADIUS TO USE THE WIREGUARD TUNNEL
===========================================

Once WireGuard is up, you usually want the MikroTik to talk to RADIUS
through the VPN (10.8.0.1) instead of a public IP.

3.1 Add RADIUS server on MikroTik
---------------------------------

If you are adding RADIUS fresh:

/radius/add \
    address=10.8.0.1 \
    secret=testing123 \
    authentication-port=1812 \
    accounting-port=1813 \
    service=ppp,login,hotspot

If RADIUS is already configured pointing to an old IP, update it:

/radius/set [find address=OLD_IP] address=10.8.0.1

3.2 Enable RADIUS for PPP, Hotspot, etc.
----------------------------------------

For PPP (PPPoE, L2TP, etc.):

/ppp/aaa set use-radius=yes interim-update=00:10:00

For Hotspot:

/ip/hotspot/profile set [find name="default"] use-radius=yes

(Or use your specific hotspot profile name.)

===========================================
4. END-TO-END TESTING
===========================================

4.1 Test WireGuard tunnel from MikroTik
---------------------------------------

Ping server WG IP:

/ping 10.8.0.1

Check WireGuard interface and peer:

/interface/wireguard/print
/interface/wireguard/peers/print

You should see:
- Interface wg-radius with IP 10.8.0.2/24
- Peer with endpoint 207.154.232.10:51820
- Latest handshake and increasing tx/rx bytes when pinging.

4.2 Test WireGuard status on server
-----------------------------------

On the server:

wg show

You should see a peer matching +kq6pGYmkeao9SWJ8GCumu/uHGuO47bUE4wL2bwxSWg=
with allowed IPs 10.8.0.2/32, and handshake / traffic stats.

4.3 Test RADIUS through WireGuard
---------------------------------

On the server, test a RADIUS auth pointing at 10.8.0.1 from a known user:

radtest USERNAME PASSWORD 10.8.0.1 0 testing123

Watch RADIUS logs:

tail -f /var/log/freeradius/radius.log

From MikroTik, try an actual PPPoE or Hotspot login that should authenticate via RADIUS.

===========================================
5. REUSING THE PATTERN FOR OTHER MIKROTIKS
===========================================

To onboard more MikroTiks, repeat the pattern with unique IPs and keys:

Example for a second MikroTik:
- MikroTik WG IP: 10.8.0.3/24
- Generate a new WireGuard keypair on the router:

/interface/wireguard/add name=wg-radius listen-port=51820 generate-key=yes
/interface/wireguard/print detail

Copy the public key and add a new [Peer] on the server:

On the server, edit /etc/wireguard/wg0.conf and append:

[Peer]
PublicKey = <MikroTik2_Public_Key>
AllowedIPs = 10.8.0.3/32

Then reload wg0:

wg-quick down wg0
wg-quick up wg0

Or:

wg syncconf wg0 <(wg-quick strip wg0)

On MikroTik2, configure similarly to section 2 but with 10.8.0.3/24 as address.

Also ensure the NAS table (or clients.conf) on FreeRADIUS knows about 10.8.0.3 as a client
with secret testing123.

===========================================
6. OPTIONAL: AUTOMATED ONBOARDING (EXEC SUMMARY)
===========================================

Your server already has scripts and database tables to automate MikroTik onboarding
(via /root/wireguard-auto-provision.sh and the mikrotik_wireguard table). The high-level
workflow is:

1) Register each MikroTik's WireGuard public key into the radius.mikrotik_wireguard table
   (via helper script or API).
2) When the MikroTik authenticates via RADIUS, FreeRADIUS post-auth calls the
   wireguard_provision exec module.
3) The script auto-assigns an IP, adds a [Peer] to wg0, and reloads WireGuard.

For day-to-day operations, however, the manual method in sections 2–5 is enough to
understand and verify the behavior.

===========================================
7. QUICK REFERENCE COMMANDS
===========================================

On MikroTik (RouterOS v7+):
---------------------------
Create WG interface (manual key):
  /interface/wireguard/add name=wg-radius listen-port=51820 private-key="<client_private_key>"

Or generate keypair automatically:
  /interface/wireguard/add name=wg-radius listen-port=51820
  /interface/wireguard/print detail

Assign IP:
  /ip/address/add interface=wg-radius address=10.8.0.X/24

Add server peer:
  /interface/wireguard/peers/add interface=wg-radius \
      public-key="yth38MY4uQ9plOsK5hTSJBhU0aIjl4dN8dvtQj21r2g=" \
      allowed-address=10.8.0.1/32 \
      endpoint=207.154.232.10:51820 \
      persistent-keepalive=25s

Add route (if needed):
  /ip/route/add dst-address=10.8.0.1/32 gateway=wg-radius

Configure RADIUS:
  /radius/add address=10.8.0.1 secret=testing123 authentication-port=1812 accounting-port=1813 service=ppp,login,hotspot
  /ppp/aaa set use-radius=yes interim-update=00:10:00

On Server:
----------
Check WireGuard:
  wg show

Restart WireGuard:
  wg-quick down wg0
  wg-quick up wg0

Check RADIUS logs:
  tail -f /var/log/freeradius/radius.log

Test RADIUS:
  radtest USERNAME PASSWORD 10.8.0.1 0 testing123

===========================================
END OF GUIDE
===========================================
EOF
