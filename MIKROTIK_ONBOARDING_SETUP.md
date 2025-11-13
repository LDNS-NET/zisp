# ZISP Mikrotik Automated Onboarding System

## Overview

This system enables automated self-onboarding of Mikrotik devices with zero manual configuration. End users receive a one-time script to run on their Mikrotik terminal, which automatically:

- Collects device information (ID, board name, system version, interfaces, etc.)
- Sends device data back to your ZISP system
- Establishes a persistent monitoring connection
- Reports status every 5 minutes

## Features

✅ **One-Click Onboarding** - Users run a single script on their Mikrotik device  
✅ **Automatic Device Registration** - Device automatically appears in the dashboard  
✅ **Real-time Status Monitoring** - See device online/offline status in real-time  
✅ **Token-Based Authentication** - Secure device-to-system communication  
✅ **API Integration Ready** - Connect to devices via API for advanced management  
✅ **Error Tracking** - Detailed logging of connection issues  
✅ **Auto-Recovery** - Devices auto-report status every 5 minutes  

## System Architecture

```
User Dashboard (Vue)
    ↓
    ├→ Add Device
    ├→ Download Script
    ├→ Monitor Status
    └→ Manage Settings
    
Mikrotik Device
    ↓ (Downloads script)
    ↓ (Executes via terminal)
    ↓
API Sync Endpoint (Token Auth)
    ↓
TenantMikrotik Model (DB)
    ↓
Status Check Job (Every 3 min)
    ↓
Dashboard Updated in Real-Time
```

## Database Schema

The `tenant_mikrotiks` table includes:

- **Identification**: `id`, `name`, `hostname`, `device_id`
- **Connection**: `ip_address`, `api_port`, `api_username`, `api_password`
- **Authentication**: `sync_token`, `onboarding_token`
- **Status**: `status` (pending/onboarding/connected/disconnected/error)
- **Onboarding**: `onboarding_status` (not_started/in_progress/completed/failed)
- **Tracking**: `last_seen_at`, `last_connected_at`, `onboarding_completed_at`
- **Device Info**: `board_name`, `system_version`, `interface_count`
- **Error Handling**: `last_error`, `sync_attempts`, `connection_failures`

## Getting Started

### 1. Run Database Migration

```bash
php artisan migrate
```

This creates/updates the `tenant_mikrotiks` table with all necessary fields.

### 2. Verify Environment Configuration

Ensure your `.env` file contains:

```env
APP_URL=https://your-system-domain.com
```

The system uses this to generate correct URLs in the onboarding script.

### 3. Access the Mikrotiks Module

Navigate to `/mikrotiks` in your ZISP dashboard.

### 4. Add a New Device

1. Click "+ Add Device"
2. Enter a device name (e.g., "Main Router")
3. Click "Create Device"
4. You'll be shown the device details page

### 5. Download the Onboarding Script

1. On the device details page, click "📥 Download Onboarding Script"
2. A file named `zisp_onboarding_[device_name]_[id].rsc` will download
3. This script is unique to your device and includes authentication tokens

### 6. Execute the Script on Your Mikrotik Device

Connect to your Mikrotik device via terminal (SSH, WebFig, or Winbox terminal):

**Via SSH:**
```bash
ssh admin@192.168.1.1
```

**Then paste the downloaded script:**
```bash
# Copy and paste the entire contents of the .rsc file
# The script will:
# 1. Collect device information
# 2. Send data to your ZISP system
# 3. Set up automatic status reporting
```

**Or upload and execute the script:**
```bash
# If your script is in a file, you can execute it as:
/file print
# Then go to Files > script.rsc > Right-click > Run Script
```

### 7. Monitor Device Status

1. Return to your ZISP dashboard
2. The device will appear as "Connected" within 1 minute
3. You'll see device details: board name, interfaces, system version, etc.
4. Status updates every 3 minutes via the scheduler

## API Endpoints

### Public Endpoints (Token-Based Auth)

#### Sync Device Data
```
POST /mikrotiks/{id}/sync?token={sync_token}

Request Body (form-data):
- device_id: Device name/identity
- board_name: Router board model
- interface_count: Number of interfaces
- system_version: RouterOS version
- mac_addresses: Semicolon-separated MAC addresses
- dns_servers: Semicolon-separated DNS servers
- timestamp: Device timestamp

Response:
{
    "success": true,
    "message": "Device synced successfully",
    "device_id": 123
}
```

### Protected Endpoints (Auth Required)

#### Get Device Status
```
GET /mikrotiks/{id}/status

Response:
{
    "status": "connected|disconnected",
    "is_online": true|false,
    "last_seen_at": "2025-11-13T10:00:00Z",
    "interfaces": [...],
    "ip_addresses": [...],
    "has_wireless": true|false
}
```

#### Test Device Connection
```
POST /mikrotiks/{id}/test-connection

Response:
{
    "success": true,
    "device_info": {
        "identity": "MikroTik",
        "board_name": "RB4011",
        "system_version": "7.11",
        ...
    }
}
```

#### Regenerate Script
```
POST /mikrotiks/{id}/regenerate-script

Response:
Redirects back with success message
```

#### Mark Device Offline (Admin)
```
POST /mikrotiks/{id}/mark-offline
```

#### Mark Device Online (Admin)
```
POST /mikrotiks/{id}/mark-online
```

## Onboarding Script Details

The generated RouterOS script performs:

1. **Device Information Collection**
   - Device ID (hostname)
   - Board name and model
   - System package version
   - Interface count
   - CPU load and memory usage
   - MAC addresses and DNS servers

2. **Initial Sync**
   - Sends all collected data to your system's sync endpoint
   - Uses the unique `sync_token` for authentication
   - Stores response for verification

3. **Persistent Monitoring Setup**
   - Creates a scheduler that runs every 5 minutes
   - Reports device status back to the system
   - Uses `/tool fetch` to make HTTP POST requests

4. **Auto-Recovery**
   - If connection fails, scheduler continues trying
   - No manual intervention required
   - Logs all actions in RouterOS logs

## Scheduled Jobs

### Every 3 Minutes: CheckMikrotikStatus

```bash
php artisan mikrotik:check-status
```

This command:
- Checks all registered devices
- Tests API connectivity (if credentials provided)
- Updates device status based on `last_seen_at` timestamp
- Marks devices as stale if no activity for 4+ minutes
- Logs connection status changes

**To verify it's running:**
```bash
php artisan schedule:list
```

## Console Commands

### Check Mikrotik Status (Manual)
```bash
php artisan mikrotik:check-status
```

### Force Check All Devices
```bash
php artisan mikrotik:check-status --force
```

## Troubleshooting

### Device Not Appearing in Dashboard

**Possible causes:**
1. Script didn't execute (check Mikrotik logs)
2. Device can't reach your system URL (check firewall)
3. Token mismatch (regenerate script)
4. System URL is incorrect in `.env`

**Solutions:**
- Verify `APP_URL` is accessible from your network
- Check Mikrotik logs: `[admin@MikroTik] > /log print`
- Regenerate the script and try again
- Ensure HTTPS is working (not self-signed cert issues)

### Device Shows Offline

**Possible causes:**
1. No activity for 4+ minutes
2. Device disconnected or scheduler disabled
3. API unreachable

**Solutions:**
- Check device is powered on
- Verify internet connectivity on the device
- Regenerate script and run again
- Check device logs for errors

### Script Execution Errors

**Common errors:**

1. **"Cannot resolve name"** → Mikrotik can't reach your domain
   - Solution: Use IP address or ensure DNS works

2. **"Connection refused"** → Wrong port or firewall blocking
   - Solution: Check firewall allows port 80/443

3. **"Invalid token"** → Token mismatch
   - Solution: Regenerate and re-run script

## Security Considerations

1. **Tokens are Random**: Each device gets unique sync and onboarding tokens (64 random characters)

2. **HTTPS Only**: Ensure your system uses HTTPS in production

3. **One-Time Scripts**: Each script includes a unique token. If compromised, regenerate it.

4. **Token Storage**: Tokens are stored in database with your user association. Never share tokens.

5. **API Credentials**: API passwords are encrypted using Laravel's encryption

6. **Rate Limiting**: Consider adding rate limiting to sync endpoints in production

## Advanced Configuration

### Customizing Script Template

Edit `app/Services/MikrotikScriptGenerator.php` to modify the generated script.

Key variables you can customize:
- Scheduler interval (default: 5 minutes)
- Data to collect
- Error handling
- Logging behavior

### API Connection Setup

To enable direct API management of devices:

1. Set IP address, username, and password in device edit form
2. Use `MikrotikConnectionService` for API operations
3. Available operations:
   - Get device info
   - Get interface status
   - Get IP addresses
   - Check wireless capability
   - Enable/disable interfaces

### Extending the System

1. **Add More Device Info**: Modify `getDeviceInfo()` in `MikrotikConnectionService`
2. **Add API Commands**: Create new methods in controller and service
3. **Add Webhooks**: Extend `sync()` endpoint to trigger custom actions
4. **Add Monitoring**: Log device metrics over time for analytics

## API Service Installation

The `MikrotikConnectionService` requires the RouterOS API library:

```bash
composer require thecodeassassin/routeros-api
```

If you want to use direct API connections for device management.

## Common Use Cases

### 1. Automatic Device Registration
- User downloads script
- Runs it on device
- Device appears in dashboard automatically

### 2. Multi-Site Management
- Each site has its own Mikrotik
- All monitored from single ZISP dashboard
- Status updates every 3 minutes

### 3. User Account Management
- Track which admin created each device
- Filter by user
- Audit trail of modifications

### 4. Status Alerts (Future)
- Extend to send alerts when device goes offline
- Email/SMS notifications
- Automated recovery attempts

## File Structure

```
app/
├── Models/
│   └── Tenants/TenantMikrotik.php
├── Services/
│   ├── MikrotikScriptGenerator.php
│   └── MikrotikConnectionService.php
├── Http/Controllers/Tenants/
│   └── TenantMikrotikController.php
└── Console/Commands/
    └── CheckMikrotikStatus.php

resources/js/Pages/Mikrotiks/
├── Index.vue
├── Show.vue
├── Edit.vue
└── Create.vue

database/migrations/
└── 2025_11_13_000001_enhance_tenant_mikrotiks_table.php

routes/
└── web.php (mikrotiks routes)
```

## Next Steps

1. ✅ Run migration: `php artisan migrate`
2. ✅ Test the module: Visit `/mikrotiks`
3. ✅ Add a device and download the script
4. ✅ Execute script on a test Mikrotik device
5. ✅ Verify device appears in dashboard
6. ✅ Set up scheduler on server (cron/task scheduler)
7. ✅ Monitor logs: `tail -f storage/logs/laravel.log | grep -i mikrotik`

## Support & Issues

For issues or feature requests, check:
- Laravel logs: `storage/logs/laravel.log`
- Mikrotik logs: SSH into device and run `/log print`
- Router console: Check scheduler status with `/system scheduler print`
