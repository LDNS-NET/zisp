# Router Status Sync Setup Guide

## Overview
The router status sync system automatically updates router online/offline status, CPU, memory, uptime, and active sessions every minute via Laravel scheduler.

## What Was Implemented

### 1. Automated Scheduler (app/Console/Kernel.php)
- `routers:sync` command runs every minute
- Uses `withoutOverlapping()` to prevent concurrent runs
- Runs in background for better performance

### 2. Improved Sync Command (app/Console/Commands/SyncRoutersCommand.php)
- Better error handling per router (one failure doesn't stop all)
- 3-second timeout to avoid blocking
- Detailed logging for failures (timeout, auth, etc.)
- Updates CPU, memory, uptime from RouterOS API

### 3. Backend Endpoints
- `GET /mikrotiks/status` - Bulk status for all routers (for frontend polling)
- `GET /mikrotiks/{id}/status` - Individual router status with:
  - online status
  - CPU, memory, uptime
  - identity (router name)
  - hotspot_users, pppoe_users (when online)
  - Auto-checks if data is stale (>30 seconds)

### 4. Frontend Real-Time Updates
- Polls `/mikrotiks/status` every 5 seconds
- Updates router status, CPU, memory, uptime without page refresh
- Shows "Offline" if last_seen_at > 2 minutes

## Server Setup

### Step 1: Add Cron Job

On your Linux server, add the Laravel scheduler to crontab:

```bash
crontab -e
```

Add this line (replace `/var/www/zisp` with your actual project path):

```bash
* * * * * cd /var/www/zisp && php artisan schedule:run >> /dev/null 2>&1
```

### Step 2: Verify Cron is Running

```bash
# Check if cron is active
crontab -l

# Test scheduler manually
cd /var/www/zisp && php artisan schedule:run

# Check scheduled tasks
php artisan schedule:list
```

### Step 3: Monitor Logs

```bash
# Watch for sync activity
tail -f storage/logs/laravel.log | grep "routers:sync"

# Watch for errors
tail -f storage/logs/laravel.log | grep "Router sync"
```

## Testing

### Test the Sync Command Manually
```bash
php artisan routers:sync
```

Expected output:
```
Starting router sync...
Router 1 (mikrotik1) synced successfully (0.5s)
Sync complete: 1 routers synced, 0 failed
```

### Test Frontend Polling
1. Open router list page
2. Open browser console (F12)
3. Should see status updates every 5 seconds
4. Router status should update automatically when router goes online/offline

## Troubleshooting

### Routers Not Updating
1. Check if cron is running: `crontab -l`
2. Test scheduler: `php artisan schedule:run`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Verify router has VPN IP configured

### Frontend Not Updating
1. Check browser console for errors
2. Verify route exists: `php artisan route:list | grep status`
3. Check network tab for `/mikrotiks/status` requests

### Timeout Issues
- Default timeout is 3 seconds per router
- If routers are slow, increase timeout in `app/Services/MikrotikService.php` (line 234)
- But keep it low (<5s) to avoid blocking scheduler

## API Endpoints

### Bulk Status (for frontend polling)
```
GET /mikrotiks/status
Response: {
  "success": true,
  "routers": [
    {
      "id": 1,
      "online": true,
      "status": "online",
      "cpu": 15.5,
      "memory": 45.2,
      "uptime": 86400,
      "uptime_formatted": "1d 0h 0m",
      "identity": "Router-1",
      "hotspot_users": 0,
      "pppoe_users": 0,
      "last_seen_at": "2025-11-23T19:30:00Z"
    }
  ]
}
```

### Individual Router Status
```
GET /mikrotiks/{id}/status
Response: {
  "success": true,
  "online": true,
  "status": "online",
  "cpu": 15.5,
  "memory": 45.2,
  "uptime": 86400,
  "uptime_formatted": "1d 0h 0m",
  "identity": "Router-1",
  "hotspot_users": 5,
  "pppoe_users": 3,
  "last_seen_at": "2025-11-23T19:30:00Z"
}
```

## Notes

- Scheduler runs every minute, frontend polls every 5 seconds
- Routers marked offline if last_seen_at > 2 minutes
- Individual router failures don't stop the entire sync
- All API calls use VPN IP (10.100.0.0/16 subnet)
- Timeout is 3 seconds to avoid blocking scheduler

