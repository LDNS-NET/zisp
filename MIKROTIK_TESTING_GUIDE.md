# Mikrotik Automated Onboarding - Testing Guide

## Pre-Testing Checklist

- [ ] Migration ran successfully: `php artisan migrate`
- [ ] `.env` has correct `APP_URL`
- [ ] Laravel scheduler is set up (cron/task scheduler)
- [ ] System is accessible from network where Mikrotik will connect
- [ ] HTTPS is working (or use HTTP for local testing)
- [ ] Mikrotik device(s) available for testing

---

## Test 1: Database Setup

### Verify Migration

```bash
# Check if table was created
php artisan tinker
> \DB::table('tenant_mikrotiks')->count()
// Should return: 0 (no devices yet)

> \DB::table('tenant_mikrotiks')->getConnection()->getSchemaBuilder()->getColumnListing('tenant_mikrotiks')
// Should list all our fields
```

### Expected Fields
```
✓ id
✓ name
✓ hostname
✓ ip_address
✓ api_port
✓ api_username
✓ api_password
✓ sync_token
✓ onboarding_token
✓ status
✓ onboarding_status
✓ onboarding_completed_at
✓ last_connected_at
✓ last_seen_at
✓ onboarding_script_url
✓ onboarding_script_content
✓ device_id
✓ board_name
✓ system_version
✓ interface_count
✓ last_error
✓ sync_attempts
✓ connection_failures
✓ created_by
✓ created_at
✓ updated_at
```

---

## Test 2: Model & Services

### Test TenantMikrotik Model

```bash
php artisan tinker

# Create a test device
> $device = \App\Models\Tenants\TenantMikrotik::create([
    'name' => 'Test Device',
    'status' => 'pending',
]);

# Check tokens were generated
> $device->sync_token
// Should output: 64-character random string

> $device->onboarding_token
// Should output: 64-character random string

# Test methods
> $device->isOnline()
// Should return: false (no activity yet)

> $device->markConnected()
> $device->isOnline()
// Should now return: true

# Check status updated
> $device->status
// Should be: connected

> $device->last_seen_at
// Should show current timestamp
```

### Test Script Generator

```bash
php artisan tinker

> $generator = app(\App\Services\MikrotikScriptGenerator::class);
> $device = \App\Models\Tenants\TenantMikrotik::first();

# Generate script
> $script = $generator->generateScript($device, 'http://localhost:8000');
> strlen($script)
// Should be large (2000+ characters)

# Check script contains required elements
> strpos($script, $device->sync_token) !== false
// Should return: true

> strpos($script, 'localhost:8000') !== false
// Should return: true

> strpos($script, ':local syncToken') !== false
// Should return: true
```

---

## Test 3: Controller & Routes

### Test Route Registration

```bash
php artisan route:list | grep mikrotik

# Should show:
# GET|HEAD    /mikrotiks                  mikrotiks.index
# POST       /mikrotiks                   mikrotiks.store
# GET|HEAD    /mikrotiks/create           mikrotiks.create
# GET|HEAD    /mikrotiks/{id}             mikrotiks.show
# GET|HEAD    /mikrotiks/{id}/edit        mikrotiks.edit
# PUT|PATCH   /mikrotiks/{id}             mikrotiks.update
# DELETE      /mikrotiks/{id}             mikrotiks.destroy
# GET         /mikrotiks/{id}/download-script   mikrotiks.download-script
# ... and others
```

### Test Dashboard Access

```bash
# Via curl (replace token with actual auth)
curl -H "Authorization: Bearer {YOUR_TOKEN}" \
     http://localhost:8000/mikrotiks

# Via browser
http://localhost:8000/mikrotiks
```

Expected: Index page loads with no devices

---

## Test 4: Create & Download Script

### Test Device Creation

```bash
# Via Laravel Tinker
php artisan tinker

> $device = \App\Models\Tenants\TenantMikrotik::create([
    'name' => 'Test Router',
]);

> $generator = app(\App\Services\MikrotikScriptGenerator::class);
> $generator->storeScript($device, 'http://localhost:8000');
> $device->refresh();

# Verify script stored
> strlen($device->onboarding_script_content)
// Should be > 2000

# Check script is unique per device
> $device->onboarding_script_content
// Should contain device-specific token
```

### Test Download Endpoint

```bash
# Get the download URL
curl -o test_script.rsc \
     -H "Authorization: Bearer {TOKEN}" \
     http://localhost:8000/mikrotiks/1/download-script

# Check file was downloaded
file test_script.rsc
// Should be: ASCII text

# Verify content
cat test_script.rsc | head -20
// Should show RouterOS script
```

---

## Test 5: Sync Endpoint (Simulated)

### Simulate Device Sync

```bash
php artisan tinker

# Get a device
> $device = \App\Models\Tenants\TenantMikrotik::first();
> $token = $device->sync_token;

# Now test the sync endpoint via HTTP POST
# (In another terminal or via Postman)
```

```bash
curl -X POST \
  "http://localhost:8000/mikrotiks/1/sync?token=YOUR_SYNC_TOKEN" \
  -d "device_id=MikroTik" \
  -d "board_name=RB4011" \
  -d "interface_count=5" \
  -d "system_version=7.11" \
  -H "Content-Type: application/x-www-form-urlencoded"
```

Expected Response:
```json
{
    "success": true,
    "message": "Device synced successfully",
    "device_id": 1
}
```

### Verify Device Updated

```bash
php artisan tinker

> $device = \App\Models\Tenants\TenantMikrotik::find(1);
> $device->device_id
// Should be: MikroTik

> $device->board_name
// Should be: RB4011

> $device->interface_count
// Should be: 5

> $device->status
// Should be: connected

> $device->onboarding_status
// Should be: in_progress or completed

> $device->last_seen_at
// Should be recent timestamp
```

---

## Test 6: Scheduler Command

### Test Status Check Command

```bash
# Run manually
php artisan mikrotik:check-status

# Expected output:
# 🔍 Checking Mikrotik device statuses...
# ✅ Status check complete!
# 📊 Summary:
#    Total devices: 1
#    Connected: 1
#    Disconnected: 0
```

### Schedule Test

```bash
# Check scheduler
php artisan schedule:list | grep mikrotik

# Should show:
# mikrotik:check-status Every 3 Minutes
```

---

## Test 7: Vue Components

### Test Index Page

1. Navigate to: `http://localhost:8000/mikrotiks`
2. Verify elements visible:
   - ✓ "+ Add Device" button
   - ✓ Getting Started section
   - ✓ Device list (or "no devices" message)

### Test Create Device

1. Click "+ Add Device"
2. Enter name: "Test Device"
3. Click "Create Device"
4. Verify:
   - ✓ Redirects to show page
   - ✓ Success message appears
   - ✓ Device name displayed

### Test Show Device

1. On device detail page, verify visible:
   - ✓ Device name and status badges
   - ✓ Device information section
   - ✓ Authentication tokens (sync & onboarding)
   - ✓ Onboarding script section
   - ✓ Download button
   - ✓ Action buttons

### Test Edit Device

1. Click "✏️ Edit Device"
2. Change name: "Modified Test Device"
3. Click "Save Changes"
4. Verify:
   - ✓ Name updated
   - ✓ Redirects back to show page
   - ✓ Success message

---

## Test 8: Real Mikrotik Integration (Optional)

### Prerequisites
- Actual Mikrotik device with RouterOS 6.48+
- Network access to device (SSH, API, or WebFig)
- Device can reach your system's domain/IP

### Test Flow

1. **Create device in ZISP**
   ```
   Navigate to /mikrotiks
   Click "+ Add Device"
   Enter: "Production Router"
   Create
   ```

2. **Download script**
   ```
   Click "📥 Download Onboarding Script"
   Save file
   ```

3. **Connect to device**
   ```bash
   ssh admin@192.168.1.1
   # Enter password
   ```

4. **Paste script**
   ```
   [admin@MikroTik] > 
   # Paste entire script content and press Enter
   ```

5. **Watch execution**
   ```
   Device will execute script
   Should see: "ZISP Onboarding:" messages in logs
   ```

6. **Check dashboard**
   ```
   Refresh browser
   Device should show as "Connected"
   Status: connected
   Onboarding: completed
   ```

7. **Verify auto-reporting**
   ```
   Wait 5 minutes
   Run: php artisan mikrotik:check-status
   Device should still be connected
   last_seen_at should be recent
   ```

---

## Test 9: Error Handling

### Test Invalid Token

```bash
curl -X POST \
  "http://localhost:8000/mikrotiks/1/sync?token=INVALID_TOKEN" \
  -d "device_id=Test"
```

Expected Response:
```json
{
    "error": "Invalid token"
}
```
Status: 401

### Test Missing Data

```bash
php artisan tinker
> $device = \App\Models\Tenants\TenantMikrotik::create(['name' => 'Test']);
> $device->device_id
// Should be: null

# Try to complete onboarding with no device_id
> $device->completeOnboarding()
// Should fail or store partial data gracefully
```

### Test Stale Device Detection

```bash
php artisan tinker

> $device = \App\Models\Tenants\TenantMikrotik::first();
> $device->last_seen_at = now()->subMinutes(5);
> $device->save();

# Now run status check
# (In terminal)
php artisan mikrotik:check-status

# Verify device marked as disconnected
> $device->refresh();
> $device->status
// Should be: disconnected

> $device->connection_failures
// Should be incremented
```

---

## Test 10: Data Validation

### Test Token Generation

```bash
php artisan tinker

# Create 5 devices
> for ($i = 0; $i < 5; $i++) { 
    $d = \App\Models\Tenants\TenantMikrotik::create(['name' => "Device $i"]);
    echo $d->sync_token . "\n";
  }

# All tokens should be unique and different
```

### Test Encryption

```bash
php artisan tinker

> $device = \App\Models\Tenants\TenantMikrotik::first();
> $device->api_password = 'test123';
> $device->save();

# Password should be encrypted in DB
> \DB::table('tenant_mikrotiks')->where('id', $device->id)->first()->api_password
// Should be: eyJ... (encrypted string, not plain text)

# But retrievable via model
> $device->api_password
// Should be: test123
```

---

## Quick Test Checklist

```
Database:
  [ ] Migration completed
  [ ] All fields present
  [ ] Token generation working

Model:
  [ ] TenantMikrotik created
  [ ] Methods work (isOnline, markConnected, etc)
  [ ] Relationships work

Services:
  [ ] MikrotikScriptGenerator creates scripts
  [ ] Scripts contain unique tokens
  [ ] Scripts are device-specific

Controller:
  [ ] All routes registered
  [ ] CRUD operations work
  [ ] Script download works
  [ ] Sync endpoint works (with valid token)
  [ ] Sync endpoint rejects (with invalid token)

Commands:
  [ ] Status check command runs
  [ ] Scheduler shows in schedule:list

Vue:
  [ ] Index page loads
  [ ] Create device works
  [ ] Show page displays all info
  [ ] Edit device works
  [ ] Download button works

Integration:
  [ ] Device can call sync endpoint
  [ ] Status updates on dashboard
  [ ] Auto-reporting works
  [ ] Stale device detection works
```

---

## Performance Testing

### Sync Performance

```bash
# Measure sync endpoint response time
time curl -X POST \
  "http://localhost:8000/mikrotiks/1/sync?token=TOKEN" \
  -d "device_id=Test" \
  -H "Content-Type: application/x-www-form-urlencoded"

# Should be: < 100ms
```

### Database Query Performance

```bash
php artisan tinker

# Measure device list query
> Illuminate\Support\Facades\DB::enableQueryLog();
> $devices = \App\Models\Tenants\TenantMikrotik::with('creator')->get();
> count(Illuminate\Support\Facades\DB::getQueryLog());
// Should be: 2 queries (users table join)

# Check query time
> foreach (Illuminate\Support\Facades\DB::getQueryLog() as $q) {
    echo $q['time'] . "ms\n";
  }
// Should be: < 10ms each
```

---

## Cleanup

```bash
# Delete test data
php artisan tinker

> \App\Models\Tenants\TenantMikrotik::truncate()

# Or specific device
> \App\Models\Tenants\TenantMikrotik::find(1)->delete()
```

---

**All tests passed? 🎉 Your system is ready for production!**
