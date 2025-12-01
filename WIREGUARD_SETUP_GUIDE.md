# WireGuard Server Setup Guide

Complete step-by-step guide for setting up WireGuard peer management on Ubuntu server.

---

## Prerequisites

- Ubuntu 20.04+ server
- Root or sudo access
- Laravel application installed at `/var/www/zisp`
- WireGuard already installed (`sudo apt install wireguard`)

---

## Step 1: Install WireGuard

```bash
sudo apt update
sudo apt install wireguard -y
```

---

## Step 2: Generate Server Keys

```bash
# Generate private key
wg genkey | sudo tee /etc/wireguard/server_private.key
sudo chmod 600 /etc/wireguard/server_private.key

# Generate public key from private key
sudo cat /etc/wireguard/server_private.key | wg pubkey | sudo tee /etc/wireguard/server_public.key
```

**Save these keys - you'll need them for the .env file!**

```bash
# Display the keys
echo "Private Key:"
sudo cat /etc/wireguard/server_private.key
echo ""
echo "Public Key:"
sudo cat /etc/wireguard/server_public.key
```

---

## Step 3: Create WireGuard Configuration File

```bash
sudo nano /etc/wireguard/wg0.conf
```

Paste this configuration (replace `YOUR_PRIVATE_KEY_HERE` with the private key from Step 2):

```ini
[Interface]
Address = 10.100.0.1/16
ListenPort = 51820
PrivateKey = YOUR_PRIVATE_KEY_HERE

PostUp   = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE
```

**Note:** If your network interface is not `eth0`, change it to your actual interface name (check with `ip a`).

Save and exit (Ctrl+X, Y, Enter).

---

## Step 4: Enable IP Forwarding

```bash
# Enable IP forwarding
sudo sysctl -w net.ipv4.ip_forward=1

# Make it persistent across reboots
echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.conf
```

---

## Step 5: Configure Sudoers Permissions

Create a sudoers file for Laravel to manage WireGuard:

```bash
sudo nano /etc/sudoers.d/wireguard-laravel
```

Paste this content:

```bash
# WireGuard management for Laravel www-data user
Defaults:www-data !requiretty

www-data ALL=(ALL) NOPASSWD: /usr/bin/wg set wg0 peer *
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg syncconf wg0 *
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg-quick strip wg0
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg-quick up *
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg-quick down *
www-data ALL=(ALL) NOPASSWD: /usr/bin/tee /etc/wireguard/wg0.conf.tmp
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg show wg0
www-data ALL=(ALL) NOPASSWD: /usr/bin/wg show
www-data ALL=(ALL) NOPASSWD: /bin/cat /etc/wireguard/wg0.conf
www-data ALL=(ALL) NOPASSWD: /bin/cp /etc/wireguard/wg0.conf.tmp /etc/wireguard/wg0.conf
www-data ALL=(ALL) NOPASSWD: /bin/mv /etc/wireguard/wg0.conf.tmp /etc/wireguard/wg0.conf
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /etc/wireguard/backups
www-data ALL=(ALL) NOPASSWD: /usr/bin/find /etc/wireguard/backups -name wg0.conf.backup.*
www-data ALL=(ALL) NOPASSWD: /bin/rm /etc/wireguard/backups/wg0.conf.backup.*
```

Set correct permissions:

```bash
sudo chmod 0440 /etc/sudoers.d/wireguard-laravel
sudo visudo -c  # Validate syntax
```

---

## Step 6: Create Backup Directory

```bash
sudo mkdir -p /etc/wireguard/backups
sudo chown root:www-data /etc/wireguard/backups
sudo chmod 750 /etc/wireguard/backups
```

---

## Step 7: Configure Laravel Environment Variables

Edit your `.env` file:

```bash
cd /var/www/zisp
nano .env
```

Add these WireGuard configuration variables (replace with your actual values):

```bash
# WireGuard Configuration
WG_INTERFACE=wg0
WG_SERVER_ENDPOINT=your-server-domain.com  # Your server's public domain or IP
WG_SERVER_PORT=51820
WG_SERVER_PUBLIC_KEY=YOUR_PUBLIC_KEY_FROM_STEP_2
WG_SERVER_PRIVATE_KEY=YOUR_PRIVATE_KEY_FROM_STEP_2
WG_SUBNET=10.100.0.0/16
WG_SERVER_ADDRESS=10.100.0.1/16
WG_CONFIG_PATH=/etc/wireguard/wg0.conf
WG_BACKUP_DIR=/etc/wireguard/backups
WG_BINARY=/usr/bin/wg
WG_QUICK_BINARY=/usr/bin/wg-quick
WG_BACKUP_RETENTION_DAYS=30
WG_AUTO_SYNC=true
```

---

## Step 8: Clear and Cache Laravel Configuration

```bash
cd /var/www/zisp
php artisan config:clear
php artisan config:cache
```

---

## Step 9: Start WireGuard Interface

```bash
sudo wg-quick up wg0

# Enable auto-start on boot
sudo systemctl enable wg-quick@wg0
```

Verify it's running:

```bash
sudo wg show wg0
```

You should see your interface with the server's public key.

---

## Step 10: Set Up Queue Worker (Systemd Service)

Create the systemd service file:

```bash
sudo nano /etc/systemd/system/laravel-wireguard-worker.service
```

Paste this content:

```ini
[Unit]
Description=Laravel WireGuard Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/zisp/artisan queue:work --queue=wireguard --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-wireguard-worker
sudo systemctl start laravel-wireguard-worker
sudo systemctl status laravel-wireguard-worker
```

---

## Step 11: Configure Firewall

Allow WireGuard traffic:

```bash
sudo ufw allow 51820/udp
sudo ufw reload
```

---

## Step 12: Test the Setup

### Test 1: Manual Sync

```bash
cd /var/www/zisp
php artisan wireguard:sync-peers --all
```

You should see:
```
Running optimized batch sync...
Sync complete:
  - Added: 0
  - Updated: 0
  - Removed: 0
  - Failed: 0
```

### Test 2: Check Logs

```bash
tail -f storage/logs/wireguard-$(date +%Y-%m-%d).log
```

### Test 3: Verify Queue Worker

```bash
sudo journalctl -u laravel-wireguard-worker -f
```

### Test 4: Add a Test Peer

Use Laravel Tinker to simulate adding a router:

```bash
php artisan tinker
```

```php
$router = App\Models\Tenants\TenantMikrotik::first();
$router->wireguard_public_key = 'TEST_KEY_' . time();
$router->wireguard_address = '10.100.0.99';
$router->wireguard_allowed_ips = '10.100.0.99/32';
$router->save();
exit
```

Watch the logs - you should see the peer being added automatically!

---

## Troubleshooting

### Queue Worker Not Running

Check status:
```bash
sudo systemctl status laravel-wireguard-worker
```

View logs:
```bash
sudo journalctl -u laravel-wireguard-worker -n 50
```

Restart:
```bash
sudo systemctl restart laravel-wireguard-worker
```

### Permission Denied Errors

Verify sudoers file:
```bash
sudo visudo -c
sudo -u www-data sudo -l
```

### Config File Not Updating

Check file permissions:
```bash
ls -la /etc/wireguard/wg0.conf
ls -la /etc/wireguard/backups/
```

Check logs for verification:
```bash
grep "verification" storage/logs/wireguard-$(date +%Y-%m-%d).log
```

### WireGuard Interface Issues

Check interface status:
```bash
sudo wg show wg0
sudo systemctl status wg-quick@wg0
```

Restart interface:
```bash
sudo wg-quick down wg0
sudo wg-quick up wg0
```

---

## Maintenance

### View Active Peers

```bash
sudo wg show wg0
```

### View Configuration Backups

```bash
ls -lh /etc/wireguard/backups/
```

### Restore from Backup

```bash
sudo cp /etc/wireguard/backups/wg0.conf.backup.YYYY-MM-DD_HHmmss /etc/wireguard/wg0.conf
sudo wg-quick down wg0
sudo wg-quick up wg0
```

### Monitor Queue Worker

```bash
# Real-time logs
sudo journalctl -u laravel-wireguard-worker -f

# Last 100 lines
sudo journalctl -u laravel-wireguard-worker -n 100
```

### Clean Old Backups (Manual)

Backups older than 30 days are automatically cleaned, but you can manually clean:

```bash
sudo find /etc/wireguard/backups -name "wg0.conf.backup.*" -mtime +30 -delete
```

---

## Security Notes

✅ **Sudoers permissions** are restricted to specific WireGuard commands only  
✅ **Configuration backups** protect against accidental misconfiguration  
✅ **Validation** prevents invalid keys/IPs from being applied  
✅ **Dedicated log channel** separates WireGuard operations  
✅ **Atomic file updates** prevent partial writes  
✅ **Queue worker** runs as `www-data` with minimal privileges

---

## System Architecture

```
Database Change (Router Added/Updated)
    ↓
TenantMikrotikObserver (Detects Change)
    ↓
ApplyWireGuardPeer Job (Dispatched to Queue)
    ↓
Queue Worker (Processes Job)
    ↓
WireGuardService::syncAllPeers() (Batch Sync)
    ↓
1. Read current config
2. Fetch all routers from DB
3. Build new config in memory
4. Compare (Idempotency Check)
5. Write to temp file (if changes detected)
6. Move temp to wg0.conf
7. Verify file was updated
8. Restart interface (wg-quick down/up)
9. Update DB statuses
    ↓
✅ Peer Active & Reachable
```

---

## Success Criteria

After completing this guide, you should have:

- ✅ WireGuard interface running on `10.100.0.1/16`
- ✅ Queue worker running as systemd service
- ✅ Automatic peer synchronization working
- ✅ Configuration backups being created
- ✅ Logs showing successful operations
- ✅ Peers appearing in `wg0.conf` automatically

---

## Support

For issues or questions:
1. Check logs: `storage/logs/wireguard-YYYY-MM-DD.log`
2. Check queue worker: `sudo journalctl -u laravel-wireguard-worker`
3. Verify permissions: `sudo -u www-data sudo -l`
4. Test manually: `php artisan wireguard:sync-peers --all`

---

**Setup Complete!** 🎉

Your WireGuard server is now fully automated and production-ready.
