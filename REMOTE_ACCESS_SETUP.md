# Remote Winbox Access Setup Guide

To support Remote Winbox Access via the ZISP Server Proxy, we use `iptables` to forward traffic from the server's public IP to the router's VPN IP.

## 1. Prerequisites

The `www-data` user (which Laravel runs as) needs permission to run `iptables` commands for NAT management.

## 2. Configure Sudoers

Create a sudoers file for iptables management:

```bash
sudo nano /etc/sudoers.d/zisp-iptables
```

Paste the following content:

```bash
# Allow www-data to manage iptables for Winbox proxying
www-data ALL=(ALL) NOPASSWD: /usr/sbin/iptables -t nat *
www-data ALL=(ALL) NOPASSWD: /usr/sbin/iptables -t filter *
# If using a wrapper script (recommended for better security), use that instead
# www-data ALL=(ALL) NOPASSWD: /var/www/zisp/bin/winbox-proxy.sh
```

**Note:** The `WinboxPortService` currently attempts to run `sudo iptables` directly. Ensure the path is correct (`/usr/sbin/iptables` vs `/sbin/iptables`). Check with `which iptables`.

Set permissions:

```bash
sudo chmod 0440 /etc/sudoers.d/zisp-iptables
```

## 3. Server Configuration

Ensure your server allows traffic on the proxy port range (50000-60000).

### UFW (Ubuntu Firewall)

```bash
sudo ufw allow 50000:60000/tcp
sudo ufw reload
```

### DigitalOcean / AWS Firewall

If your server is behind a cloud firewall, ensure you open TCP ports `50000-60000` in the cloud console.

## 4. Winbox Port Service Logic

The system allocates a unique port between 50000-60000 for each router.

**Flow:**
1. **Incoming**: User connects to `Server_Public_IP:5xxxx`.
2. **DNAT**: Server forwards packet to `Router_VPN_IP:8291`.
3. **SNAT**: Server modifies source IP to `10.100.0.1` (Server VPN IP) so the router replies back through the tunnel.
4. **Return**: Router replies to Server, Server replies to User.

## 5. Troubleshooting

- **Check Rules**: `sudo iptables -t nat -L -n -v`
- **Check Logs**: `tail -f storage/logs/laravel.log`
- **Verify Forwarding**: Ensure `net.ipv4.ip_forward=1` is set in `/etc/sysctl.conf`.
