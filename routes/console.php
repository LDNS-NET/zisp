<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================================
// Optimized Schedules for Large-Scale Deployments (50+ tenants, 5000+ users)
// ============================================================================

// SMS Notifications (reduced frequency for scale)
Schedule::command('sms:send-expiry-notifications')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('sms:send-expiry-warnings')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground();

// Router Management (every 10 min instead of 4 to reduce API load)
Schedule::command('routers:sync')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// WireGuard Peers (every 5 min instead of 1 to reduce frequency)
Schedule::command('wireguard:sync-peers')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Disconnect Expired Users (every 5 min instead of every minute)
// Only checks users expiring in last 5 mins (optimized in command)
Schedule::command('users:disconnect-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// WinBox Rules Sync (every 5 min instead of 2 to reduce load)
Schedule::command('winbox:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Cleanup Stale Sessions (every 10 min for large deployments)
Schedule::command('app:cleanup-stale-sessions')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('app:sync-online-status')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground(); 

Schedule::command('network:process-upgrades')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Poll Mikrotik routers for active users and sync every 5 minutes (reduces DB/API load for large deployments)
Schedule::command('app:poll-mikrotik-users')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
