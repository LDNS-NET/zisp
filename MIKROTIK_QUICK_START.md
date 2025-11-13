# 🚀 ZISP Mikrotik Automated Onboarding - Quick Start Guide

## ⚡ 5-Minute Setup

### Step 1: Run Migration (30 seconds)
```bash
php artisan migrate
```

### Step 2: Restart Scheduler (1 minute)
Ensure your Laravel scheduler is running (every 1 minute):

**Linux/Mac:**
```bash
crontab -e
# Add this line:
* * * * * cd /path/to/zisp && php artisan schedule:run >> /dev/null 2>&1
```

**Windows:**
- Open Task Scheduler
- Create task to run: `php artisan schedule:run`
- Repeat every 1 minute
- Or see SCHEDULER_SETUP.md for PowerShell setup

### Step 3: Visit Dashboard
Navigate to: `http://your-system/mikrotiks`

---

## 👥 User Workflow (Step-by-Step)

### 1️⃣ **Add Device** (10 seconds)
- Click "+ Add Device"
- Enter device name: "Main Router"
- Click "Create Device"

### 2️⃣ **Download Script** (5 seconds)
- Click "📥 Download Onboarding Script"
- File saves as `zisp_onboarding_Main_Router_1.rsc`
- **Important:** This script is unique to your device

### 3️⃣ **Run on Mikrotik** (2 minutes)

#### Option A: Copy-Paste in Terminal (Recommended)
```bash
# SSH to your Mikrotik
ssh admin@192.168.1.1

# Then paste the ENTIRE script content and press Enter
# Watch for success messages
```

#### Option B: Import Script File
```bash
# After uploading the .rsc file via SFTP/SCP:
/import file-name=zisp_onboarding_Main_Router_1.rsc

# Wait for "Script file imported successfully" message
```

#### Option C: Winbox GUI
1. Open Winbox, connect to router
2. System > Scripts > Create New
3. Paste the script content
4. Click "Run"
5. Watch Logs tab for output

#### Option D: WebFig (Browser)
1. Open `http://192.168.1.1/webfig`
2. System > Scripts > Add New
3. Paste script
4. Save & Run

### 4️⃣ **Verify Success** (30 seconds)

Check Mikrotik logs:
```bash
/log print where message~"ZISP"
```

You should see:
```
ZISP Onboarding: Initiating device sync...
ZISP Onboarding: Device information sent to system
ZISP Onboarding: Setup complete!
```

Check scheduler is running:
```bash
/system scheduler print
# Should show: zisp-device-status (interval=5m)
```

### 5️⃣ **Monitor Dashboard** (Automatic)

1. Go back to dashboard
2. Refresh page
3. Device now shows:
   - 🟢 Connected
   - Onboarding Status: Completed
   - Device info: board name, interfaces, version, etc.

**Option B: Upload & Execute**
```
FTP upload the .rsc file to Mikrotik

In terminal:
/import file-name=zisp_onboarding_Main_Router_1.rsc

Press Enter and watch the script execute
```

### 4️⃣ **Watch Dashboard** (1 minute)
Return to `/mikrotiks` and watch the device status change:
- 🟡 **Pending** → Device registered
- 🔵 **In Progress** → Script running
- 🟢 **Connected** → Setup complete!

---

## What the Script Does

```
1. Collects device info:
   ✓ Device ID/Hostname
   ✓ Board name (e.g., RB4011)
   ✓ RouterOS version
   ✓ Interface count
   ✓ System resources
   ✓ MAC addresses
   ✓ DNS settings

2. Sends to ZISP system:
   ✓ POST /mikrotiks/{id}/sync?token={token}
   ✓ Uses unique authentication token
   ✓ Encrypted connection

3. Sets up auto-reporting:
   ✓ Creates scheduler
   ✓ Reports status every 5 minutes
   ✓ Auto-reconnects if needed
```

---

## Dashboard Features

### Device List
Shows all your registered devices with:
- **Status badge**: Connected/Disconnected/Pending
- **Onboarding status**: Not Started/In Progress/Completed
- **Last seen**: Timestamp of last communication
- **Interfaces**: Number of network interfaces

### Device Details
Click device to see:
- 📊 Connection status
- 🔧 Hardware details
- 🎫 Authentication tokens
- 📜 Onboarding script
- 🔗 Test connection button
- 🔄 Regenerate script option

### Actions
- **View Details**: Full device information
- **Download Script**: Get the latest onboarding script
- **Test Connection**: Verify direct API connectivity
- **Regenerate Script**: Force re-onboarding
- **Delete**: Remove device from system

---

## Monitoring

### Check Status (Manual)
```bash
php artisan mikrotik:check-status
```

Output shows:
```
🔍 Checking Mikrotik device statuses...
✅ Status check complete!
📊 Summary:
   Total devices: 5
   Connected: 4
   Disconnected: 1
   Marked stale (no activity): 0
```

### View Logs
```bash
# Watch live logs
tail -f storage/logs/laravel.log | grep -i mikrotik

# Count sync messages
grep "Mikrotik sync" storage/logs/laravel.log | wc -l

# Find errors
grep "sync failed\|last_error" storage/logs/laravel.log
```

---

## Troubleshooting

### ❌ Device Not Appearing

**Problem:** You downloaded and ran the script, but device doesn't show in dashboard

**Solutions:**
1. Check device has internet: `ping 8.8.8.8` on Mikrotik
2. Check system URL is accessible: `curl your-system-url/mikrotiks`
3. Verify APP_URL in `.env` is correct
4. Check Mikrotik logs: `/log print` (look for ZISP entries)
5. Wait 1-2 minutes and refresh browser

**Still not working?**
- Regenerate script and try again
- Check firewall allows outbound HTTPS (port 443)

### ⚠️ Device Shows Offline

**Problem:** Device was online but now shows offline

**Causes:**
- Device lost internet connection
- Scheduler disabled on device
- Device offline longer than 4 minutes
- Network connectivity issue

**Solutions:**
- Check device is powered on
- Check internet connection on device
- Check Mikrotik scheduler: `/system scheduler print`
- Device will auto-reconnect every 5 minutes
- Manually run script again if needed

### 🔴 Script Execution Error

**Error: "Cannot resolve name"**
- Device can't reach your system URL
- Solution: Ensure DNS works on device

**Error: "Connection refused"**
- Firewall blocking port 443
- Wrong hostname/IP in script
- Solution: Check firewall rules

**Error: "Invalid token"**
- Token mismatch or expired
- Solution: Regenerate script

---

## Common Tasks

### Add Multiple Devices
1. Repeat "User Workflow" for each device
2. All devices sync to one dashboard
3. Easy to manage from central location

### Get Device Information
```bash
# Via API (requires credentials set in device edit)
php artisan tinker
> $device = \App\Models\Tenants\TenantMikrotik::find(1);
> app(\App\Services\MikrotikConnectionService::class)->getDeviceInfo($device);
```

### Monitor Device Health
```bash
# Check all devices status
php artisan mikrotik:check-status

# Watch continuous updates
watch -n 3 'php artisan mikrotik:check-status'
```

### Reset Device Registration
```bash
# Regenerate tokens and script
POST /mikrotiks/{id}/regenerate-script

# Device must run new script to reconnect
```

---

## API Endpoints (Developer Reference)

### Public Endpoints

**Sync Device Data** (called by Mikrotik script)
```
POST /mikrotiks/{id}/sync?token={sync_token}
```

### Protected Endpoints (Auth Required)

**List Devices**
```
GET /mikrotiks
Response: List of all user's devices
```

**Create Device**
```
POST /mikrotiks
Body: { name: "Device Name" }
```

**View Device**
```
GET /mikrotiks/{id}
```

**Update Device**
```
PATCH /mikrotiks/{id}
Body: { name, hostname, ip_address, api_port, api_username, api_password }
```

**Delete Device**
```
DELETE /mikrotiks/{id}
```

**Download Script**
```
GET /mikrotiks/{id}/download-script
Returns: RouterOS .rsc file
```

**Get Status**
```
GET /mikrotiks/{id}/status
Response: { status, is_online, interfaces, ip_addresses, has_wireless }
```

**Test Connection**
```
POST /mikrotiks/{id}/test-connection
Response: { success, device_info }
```

**Regenerate Script**
```
POST /mikrotiks/{id}/regenerate-script
```

---

## Best Practices

✅ **DO:**
- Test setup with one device first
- Keep scripts unique per device
- Monitor device logs regularly
- Regenerate script if token exposed
- Use HTTPS in production
- Set up scheduler on server

❌ **DON'T:**
- Share scripts between devices
- Use HTTP in production
- Disable the scheduler
- Modify script tokens manually
- Allow public access to sync endpoint
- Store passwords in plain text

---

## Example: Multi-Site Setup

```
Your ISP
   ↓
Your ZISP System (single dashboard)
   ↓
   ├── Site 1 Mikrotik (Main Office)
   │   └── Auto-reports every 5 min
   │
   ├── Site 2 Mikrotik (Branch Office)
   │   └── Auto-reports every 5 min
   │
   └── Site 3 Mikrotik (Backup)
       └── Auto-reports every 5 min

All managed from one dashboard!
```

Setup each:
1. Add device with site name
2. Download script
3. Run on site Mikrotik
4. Watch all in one dashboard

---

## Next Steps

1. ✅ Complete 5-minute setup above
2. ✅ Add your first device
3. ✅ Download and run the script
4. ✅ Verify device appears online
5. ✅ Set up scheduler on server
6. ✅ Add more devices as needed
7. ✅ Configure API credentials for direct device management (optional)
8. ✅ Set up email alerts (optional)

---

## Support Resources

- **Full Documentation**: `MIKROTIK_ONBOARDING_SETUP.md`
- **Setup Guide**: `SCHEDULER_SETUP.md`
- **Sample Script**: `MIKROTIK_SCRIPT_SAMPLE.rsc`
- **Router Logs**: Check `/log print` on Mikrotik device
- **System Logs**: Check `storage/logs/laravel.log`

---

## FAQ

**Q: How often does the device report?**
A: Every 5 minutes automatically. Status check runs every 3 minutes.

**Q: What if the device loses internet?**
A: Device will keep trying to reconnect. Shows as offline after 4 minutes of no activity.

**Q: Can I manage multiple sites?**
A: Yes! Add a device for each site. All managed from one dashboard.

**Q: Is my device password stored?**
A: API passwords are encrypted using Laravel's encryption.

**Q: What if I need to re-onboard a device?**
A: Click "Regenerate Script" and run the new script on the device.

**Q: Can I delete a device?**
A: Yes, click "Delete Device". The device will stop reporting.

---

**Happy onboarding! 🎉**
