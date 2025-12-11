# Remote Winbox Management System

## 📌 Overview

A complete end-to-end Remote Winbox Management System that allows admins to remotely access MikroTik routers through an existing WireGuard VPN network without exposing ports to the public internet.

## 🏗️ Architecture

```
Admin (WireGuard Connected)
    ↓
winbox://10.100.x.x:8291
    ↓
WireGuard VPN Tunnel (wg0)
    ↓
MikroTik Router (wg-remote-mgmt)
    ↓
Winbox Service (Port 8291)
```

## ✨ Features

- **Secure Access**: Winbox only accessible through WireGuard VPN tunnel
- **Real-time Monitoring**: Online/offline status with 4-second polling
- **One-Click Enablement**: Push Winbox configuration via API
- **Direct Launch**: Open Winbox with `winbox://` URI
- **Admin VPN Info**: Display WireGuard setup instructions for admins
- **Multi-tenant Support**: Full tenant isolation

## 📂 File Structure

```
app/
├── Services/Mikrotik/
│   └── WinboxConfigService.php        # RouterOS config generation & API operations
├── Http/Controllers/Tenants/
│   └── WinboxController.php            # REST API endpoints
└── Models/Tenants/
    └── TenantMikrotik.php              # (uses existing wireguard_address column)

resources/js/Pages/
├── Tenants/Mikrotiks/Components/
│   ├── RemoteWinboxPanel.vue           # Main Winbox management UI
│   └── AdminWireGuardInfo.vue          # Admin VPN setup info
└── Mikrotiks/
    └── Show.vue                         # Updated to include Winbox panels

routes/
└── web.php                              # Winbox routes

docs/server/
└── iptables-winbox.sh                   # Server firewall configuration
```

## 🚀 Installation

### 1. Backend Setup

The backend files are already in place. No migration needed - uses existing `wireguard_address` column.

### 2. Server IPTables Configuration

On your production server, run:

```bash
# Make script executable
chmod +x docs/server/iptables-winbox.sh

# Run as root or with sudo
sudo ./docs/server/iptables-winbox.sh
```

This will:
- Allow TCP port 8291 between WireGuard peers
- Restrict access to wg0 interface only
- Make rules persistent

### 3. Frontend Assets

Compile assets:

```bash
npm run build
# or for development
npm run dev
```

## 📡 API Endpoints

### GET `/mikrotiks/{id}/winbox/config`
Get RouterOS configuration script for enabling Winbox.

**Response:**
```json
{
  "success": true,
  "script": "# RouterOS commands...",
  "router": {
    "id": 3,
    "name": "Router-01",
    "wg_ip": "10.100.0.5",
    "winbox_url": "winbox://10.100.0.5:8291"
  }
}
```

### POST `/mikrotiks/{id}/winbox/enable`
Push Winbox configuration to router via API.

**Response:**
```json
{
  "success": true,
  "message": "Remote Winbox enabled successfully",
  "winbox_url": "winbox://10.100.0.5:8291"
}
```

### GET `/mikrotiks/{id}/winbox/ping`
Check if router is online.

**Response:**
```json
{
  "success": true,
  "router_id": 3,
  "router_name": "Router-01",
  "online": true,
  "wg_ip": "10.100.0.5",
  "message": "Router is online",
  "winbox_url": "winbox://10.100.0.5:8291"
}
```

## 🎯 Usage

### Admin Workflow

1. **Connect to WireGuard VPN**
   - Admin must be connected to the server's WireGuard VPN
   - Get VPN config from system administrator

2. **Navigate to Router Details**
   - Go to Mikrotiks → Select Router → Show

3. **Enable Remote Winbox**
   - Click "Enable Remote Winbox" button
   - System pushes config to router via API
   - Firewall rules configured automatically

4. **Open Winbox**
   - Click "Open Winbox" button
   - Winbox launches with `winbox://10.100.x.x:8291`
   - Login with router credentials

### RouterOS Configuration Generated

The system generates and applies:

```rsc
# Enable Winbox service
/ip service set winbox disabled=no port=8291

# Allow Winbox ONLY on WireGuard interface
/ip firewall filter add chain=input action=accept protocol=tcp dst-port=8291 in-interface=wg-remote-mgmt comment="Winbox over WireGuard"

# Block Winbox on all other interfaces (safety)
/ip firewall filter add chain=input action=drop protocol=tcp dst-port=8291 in-interface=!wg-remote-mgmt comment="Block Winbox from WAN"
```

## 🔒 Security

### Multi-Layer Protection

1. **VPN-Only Access**
   - Winbox only accessible through WireGuard VPN tunnel
   - No public internet exposure

2. **Firewall Rules (Router)**
   - Winbox allowed ONLY on WireGuard interface
   - Blocked on all other interfaces (WAN, LAN)

3. **Firewall Rules (Server)**
   - IPTables rules restrict traffic to wg0 interface
   - Only authenticated WireGuard clients can access

4. **Authentication**
   - Winbox still requires username/password
   - Standard MikroTik authentication applies

## 🧪 Testing

### Manual Testing Steps

1. **Create Test Router**
   ```bash
   # In your Laravel app
   php artisan tinker
   ```
   ```php
   $router = App\Models\Tenants\TenantMikrotik::create([
       'name' => 'Test-Router',
       'wireguard_address' => '10.100.0.10',
       'api_username' => 'admin',
       'api_password' => 'password',
       'status' => 'online',
   ]);
   ```

2. **Test Config Generation**
   ```bash
   curl http://your-app.com/mikrotiks/3/winbox/config
   ```

3. **Test Ping**
   ```bash
   curl http://your-app.com/mikrotiks/3/winbox/ping
   ```

4. **Test UI**
   - Navigate to router show page
   - Verify components render
   - Check polling (4-second intervals)

### Common Issues

**Issue**: "Router is offline"
- **Solution**: Ensure router is connected to WireGuard VPN
- Check `wireguard_address` is set correctly

**Issue**: "Failed to enable Remote Winbox"
- **Solution**: Verify RouterOS API is accessible
- Check API credentials are correct

**Issue**: "Connection refused" when opening Winbox
- **Solution**: Ensure you're connected to WireGuard VPN
- Check server iptables rules are applied

## 📊 Vue Components

### RemoteWinboxPanel

**Props:**
- `mikrotik` (Object) - Router object with wireguard_address

**Features:**
- Online/offline indicator
- Auto-polling every 4 seconds
- Enable Winbox button
- Open Winbox button (winbox:// URI)
- Loading states
- Toast notifications

### AdminWireGuardInfo

**Props:**
- `serverEndpoint` (String) - WireGuard server endpoint
- `adminVpnIp` (String|null) - Admin's VPN IP

**Features:**
- Server info display
- Download .conf file
- QR code for mobile (placeholder)
- Setup instructions

## 🔧 Configuration

### Environment Variables

No additional environment variables needed. Uses existing:
- `WG_SERVER_ENDPOINT`
- `WG_SERVER_PUBLIC_KEY`
- `WG_SUBNET` (default: 10.100.0.0/16)

### Customization

**Change Winbox Port:**
Edit `WinboxConfigService.php`:
```php
$winboxPort = 8299; // Change from 8291
```

**Change Polling Interval:**
Edit `RemoteWinboxPanel.vue`:
```javascript
pollingInterval.value = setInterval(() => {
    checkStatus();
}, 8000); // Change from 4000ms to 8000ms
```

## 📝 Example Responses

### Successful Enable
```json
{
  "success": true,
  "message": "Remote Winbox enabled successfully",
  "winbox_url": "winbox://10.100.0.5:8291"
}
```

### Failed Enable
```json
{
  "success": false,
  "message": "Failed to enable Remote Winbox: Router is not online. Cannot push configuration."
}
```

### Ping Response
```json
{
  "success": true,
  "router_id": 3,
  "router_name": "20Mbps",
  "online": true,
  "wg_ip": "10.100.0.5",
  "message": "Router is online",
  "winbox_url": "winbox://10.100.0.5:8291"
}
```

## 🎓 Advanced Usage

### Batch Enable Winbox

```php
use App\Models\Tenants\TenantMikrotik;
use App\Services\Mikrotik\WinboxConfigService;

$service = new WinboxConfigService();
$routers = TenantMikrotik::whereNotNull('wireguard_address')->get();

foreach ($routers as $router) {
    $result = $service->enableWinboxOverWireGuard($router);
    echo "{$router->name}: " . ($result['success'] ? '✓' : '✗') . "\n";
}
```

### Custom Firewall Rules

To add specific IP restrictions:

```php
// In WinboxConfigService.php
protected function configureFirewallRules(RouterApiService $apiService): void
{
    // Add custom allowed IPs
    $allowedIps = ['10.100.0.1', '10.100.0.2'];
    
    foreach ($allowedIps as $ip) {
        $allowQuery = (new \RouterOS\Query('/ip/firewall/filter/add'))
            ->equal('chain', 'input')
            ->equal('action', 'accept')
            ->equal('protocol', 'tcp')
            ->equal('dst-port', '8291')
            ->equal('src-address', $ip)
            ->equal('comment', 'Winbox from admin ' . $ip);
            
        $client->query($allowQuery)->read();
    }
}
```

## 🤝 Contributing

When extending this system:

1. **Add New Endpoints**: Update `WinboxController.php`
2. **Add UI Features**: Create new Vue components in `Components/`
3. **Update Documentation**: Keep this README current
4. **Test Thoroughly**: Especially firewall and security changes

## 📞 Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review RouterOS logs on the device
- Verify WireGuard connectivity: `wg show wg0`
- Check iptables: `sudo iptables -L -n -v | grep 8291`

---

**Version**: 1.0.0  
**Laravel**: 12.x  
**Vue**: 3.x  
**License**: Proprietary
