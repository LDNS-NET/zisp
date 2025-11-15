# Phone-Home Scheduler Fix

## Issues Fixed

The phone-home scheduler was not working due to several issues:

### 1. **Missing API Policy Permission**
   - **Problem**: The script policy didn't include `api` permission, which is required for `/tool fetch` command
   - **Fix**: Added `api` to the script policy: `policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive,api`

### 2. **Scheduler Start Time**
   - **Problem**: Using `start-time=startup` only runs on router boot, not immediately
   - **Fix**: Changed to `start-time=start` so scheduler runs immediately and continues every 3 minutes

### 3. **Missing Error Handling**
   - **Problem**: Script failures were silent, making debugging impossible
   - **Fix**: Added comprehensive error handling with `on-error` blocks and logging

### 4. **Variable Scope**
   - **Problem**: `$syncUrl` variable might not be accessible inside the script block
   - **Fix**: Added local `syncUrl` variable inside the script using the placeholder `{{sync_url}}`

### 5. **No Immediate Testing**
   - **Problem**: No way to verify the script works after creation
   - **Fix**: Added immediate test execution after script creation

## Changes Made

### Updated Files:
1. **`resources/scripts/onboarding.rsc.stub`**
   - Fixed script policy to include `api`
   - Changed scheduler start-time from `startup` to `start`
   - Added error handling throughout
   - Added immediate script testing
   - Improved logging messages

2. **`app/Console/Commands/FixMikrotikPhoneHome.php`** (NEW)
   - Diagnostic command to check phone-home status
   - Automatic fix for existing devices
   - Can check or fix all devices or specific device

## How to Fix Existing Devices

### Option 1: Regenerate Scripts (Recommended for New Devices)
```bash
# Regenerate script for a specific device
php artisan mikrotik:regenerate-scripts --device-id=1

# Regenerate scripts for all devices
php artisan mikrotik:regenerate-scripts --all
```
Then re-run the script on the Mikrotik device.

### Option 2: Fix via API (For Already Connected Devices)
```bash
# Check phone-home status on all devices
php artisan mikrotik:fix-phone-home --all --check-only

# Fix phone-home on all devices
php artisan mikrotik:fix-phone-home --all

# Fix specific device
php artisan mikrotik:fix-phone-home --device-id=1
```

## Verification

### Check on Router (via Winbox/Terminal):
```
/system scheduler print where name="zisp-phone-home"
/system script print where name="zisp-phone-home"
/system script run zisp-phone-home
```

### Check in Laravel:
```bash
# Check if devices are reporting
php artisan mikrotik:check-status

# View device status
# Check last_seen_at timestamp in database - should update every 3 minutes
```

### Check Logs:
```bash
# Watch for phone-home requests
tail -f storage/logs/laravel.log | grep "Mikrotik sync"
```

## Expected Behavior

After the fix:
1. **Script Creation**: Phone-home script is created with proper permissions
2. **Scheduler Creation**: Scheduler runs immediately and every 3 minutes
3. **Immediate Test**: Script runs once immediately after creation
4. **Regular Reporting**: Device sends `last_seen_at` update every 3 minutes
5. **Error Logging**: Any failures are logged in RouterOS log

## Troubleshooting

### If phone-home still doesn't work:

1. **Check RouterOS Version**
   - Script requires RouterOS v7+
   - Older versions may need syntax adjustments

2. **Check Network Connectivity**
   - Router must be able to reach your `APP_URL`
   - Test: `/ping your-app-url.com`

3. **Check Firewall Rules**
   - Router firewall might be blocking outbound HTTP/HTTPS
   - Test: `/tool fetch url="https://google.com"`

4. **Check Script Permissions**
   - Verify script has `api` policy: `/system script print where name="zisp-phone-home"`
   - Should show: `policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive,api`

5. **Check Scheduler Status**
   - Verify scheduler is enabled: `/system scheduler print where name="zisp-phone-home"`
   - `disabled` should be `false`
   - `interval` should be `3m`

6. **Manual Test**
   - Run script manually: `/system script run zisp-phone-home`
   - Check for errors in output

7. **Check Sync Endpoint**
   - Verify sync URL is accessible: `https://your-app.com/mikrotiks/{id}/sync?token={token}`
   - Should return JSON response

## Testing

### Test Phone-Home Manually:
```bash
# On the router terminal:
/system script run zisp-phone-home

# Should see output like:
# Phone-home successful: 192.168.1.1
```

### Test Sync Endpoint:
```bash
# From your server:
curl -X POST "https://your-app.com/mikrotiks/1/sync?token=YOUR_TOKEN" \
  -d "ip_address=192.168.1.1"
```

## Next Steps

1. **For New Devices**: Scripts will automatically have the fix
2. **For Existing Devices**: Run `php artisan mikrotik:fix-phone-home --all`
3. **Monitor**: Check `last_seen_at` timestamps update every 3 minutes
4. **Verify**: Use diagnostic command to verify all devices are working

## Notes

- Phone-home runs every 3 minutes
- System scheduler also checks device status every 3 minutes
- Devices are marked offline if no activity for 4+ minutes
- Phone-home failures are logged but don't break the system
- Script can be manually run anytime for testing

