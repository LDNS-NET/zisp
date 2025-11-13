# Mikrotik Onboarding Script - Troubleshooting Guide

## Script Import Error: "expected end of command"

### Root Cause
RouterOS strictly validates script syntax during import. Common causes:
1. **Nested quotes in single-line commands** - RouterOS cannot parse complex nested quotes in one command
2. **Line length limits** - Some RouterOS versions have line length restrictions
3. **Encoding issues** - Special characters or wrong encoding
4. **Version incompatibility** - Script syntax differs between RouterOS v6 and v7+

### Solution Applied
The onboarding script has been refactored to:
- Avoid nested quotes in the scheduler command
- Use simpler variable assignments for scheduler scripts
- Use proper escaping with backslashes
- Test-compatible with RouterOS 6.48+ and 7.x+

## Testing the Script

### Option 1: Validate Script Before Running (Recommended)
```bash
php artisan mikrotik:validate-script --device-id=1
```

This will:
- Check for syntax issues
- Verify balanced quotes
- Report script statistics
- Confirm it's ready to import

### Option 2: Manual Testing on Mikrotik

**Via SSH (recommended):**
```bash
ssh admin@192.168.1.1
# Then paste the script content and press Enter

# Or import if uploaded as a file:
/import file-name=zisp_onboarding_device_1.rsc
```

**Via Winbox:**
1. Connect to Mikrotik via Winbox
2. Go to: System > Scripts
3. Create new script
4. Paste the entire script content
5. Click "Run"
6. Check the Logs tab for output

**Via WebFig:**
1. Navigate to System > Scripts
2. Add New
3. Paste script
4. Run and watch the Logs section

## Common Error Messages

### "expected end of command (line 54 column 90)"
**Old issue (now fixed)** - The scheduler command had too many nested quotes.

**If you still see this:**
1. Regenerate the script: Go to Mikrotiks dashboard → Device → Regenerate Script
2. Download the new script
3. Clear the old one: `/system scheduler remove [find comment="ZISP-Device-Status"]`
4. Try importing the new script

### "no such command (line X)"
**Cause:** Command not available on your RouterOS version.

**Solutions:**
- Upgrade to RouterOS 6.48+
- Check available commands: `/help` in terminal
- Contact support if command is missing

### "timeout" or "connection timeout"
**Cause:** Device cannot reach your ZISP system URL.

**Solutions:**
1. Verify your system URL is reachable from the network:
   ```bash
   /tool fetch url=https://your-system-url/ping
   ```

2. Check DNS resolution:
   ```bash
   /ip dns print
   /ip dns set servers=8.8.8.8,8.8.4.4
   ```

3. Verify firewall allows outbound HTTPS:
   ```bash
   /ip firewall print
   ```

4. Test from your computer:
   ```bash
   curl -v https://your-system-url/mikrotiks/1/sync?token=yourtoken
   ```

### "connection refused"
**Cause:** System URL is correct but HTTPS port is blocked or not listening.

**Solutions:**
1. Verify HTTPS is enabled on your server
2. Check if port 443 is listening: `netstat -tulpn | grep 443`
3. Try with HTTP (dev only): Update script to use `http://`
4. Check firewall: `ufw status` (Linux) or Windows Firewall

## Script Behavior & Debugging

### Check Script Execution Logs

On your Mikrotik, view the logs to see if script ran:

```bash
/log print
# Or filter for ZISP entries:
/log print where message~"ZISP"
```

Expected log output:
```
0 admin 2025-11-13 10:05:32 info ZISP Onboarding: Initiating device sync...
1 admin 2025-11-13 10:05:35 info ZISP Onboarding: Device information sent to system
2 admin 2025-11-13 10:05:35 info ZISP Onboarding: Setting up periodic sync scheduler...
3 admin 2025-11-13 10:05:35 info ZISP Onboarding: Setup complete! Device will report status every 5 minutes.
```

### Verify Scheduler Is Running

```bash
/system scheduler print
# Should show "zisp-device-status" with interval=5m
/system scheduler print stats
```

### Test Manual Sync

```bash
/tool fetch url="https://your-system/mikrotiks/1/sync?token=YOUR_TOKEN" method=post dst-path="/tmp/test.txt"
# Check response:
/file contents /tmp/test.txt
```

## Step-by-Step Troubleshooting

### Step 1: Validate Script Syntax (Optional)
```bash
php artisan mikrotik:validate-script --device-id=1
```

### Step 2: Import Script on Mikrotik
```bash
# Via terminal:
/import file-name=zisp_onboarding_device_1.rsc
# Wait for completion message
```

### Step 3: Verify Scheduler Created
```bash
/system scheduler print where name="zisp-device-status"
# Should show: name, interval=5m, comment="ZISP-Device-Status"
```

### Step 4: Check System Logs
```bash
/log print where message~"ZISP"
```

### Step 5: Test Network Connectivity
```bash
/tool fetch url="https://your-system-url/mikrotiks/1/sync?token=TOKEN" method=post dst-path="/tmp/response.txt"
/file contents /tmp/response.txt
```

### Step 6: Verify Dashboard
- Log in to your ZISP dashboard
- Check Mikrotiks section
- Device should show "Connected" status
- Last seen timestamp should update

## Advanced: Manual Script Repair

If you get persistent errors, try this minimal test script:

```bash
/log info "Test: Starting ZISP onboarding..."
:local testUrl "https://your-system.com"
:log info ("Test: URL is " . $testUrl)
/tool fetch url=$testUrl method=get dst-path="/tmp/test_response.txt"
:log info "Test: Fetch completed, check /tmp/test_response.txt"
```

Save this as `test_script.rsc` and import to isolate issues.

## Still Having Issues?

### Collect Debug Information
```bash
# Export device info
/system identity print
/system resource print
/system package print
/interface print
/ip address print
/system scheduler print
/log print
```

### Check Server Logs
```bash
# On your ZISP server
tail -f storage/logs/laravel.log | grep -i mikrotik
```

### Script Statistics
- **File size:** Typically 2-3 KB (watch for bloated versions)
- **Line count:** Typically 40-60 lines
- **Execution time:** ~2-5 seconds per run

## Prevention: Best Practices

1. **Always validate before importing:**
   ```bash
   php artisan mikrotik:validate-script --device-id=YOUR_ID
   ```

2. **Keep logs clean:**
   ```bash
   /log remove numbers=0,1,2...  # Clean old logs
   ```

3. **Test on a spare device first** - Don't deploy to production without testing

4. **Version check your RouterOS:**
   ```bash
   /system identity print
   /system package print
   # Minimum: RouterOS 6.48.x or 7.x
   ```

5. **Monitor dashboard regularly:**
   - Check device status every hour
   - Set up alerts for offline devices (future feature)

## Quick Reference: Command Locations

| Command | Purpose |
|---------|---------|
| `/system identity get name` | Get device hostname |
| `/system package print` | Get RouterOS version |
| `/system resource print` | Get device resources |
| `/system scheduler print` | View scheduled tasks |
| `/log print` | View system logs |
| `/tool fetch url=...` | Make HTTP requests |
| `/import file-name=...` | Import script file |

## Support

If problems persist:
1. **Regenerate the script** from dashboard and re-import
2. **Clear the scheduler**: `/system scheduler remove [find comment="ZISP-Device-Status"]`
3. **Try a fresh device** to isolate device-specific issues
4. **Check app logs**: `tail -f storage/logs/laravel.log`
5. **Verify network**: Ensure firewall allows outbound HTTPS
