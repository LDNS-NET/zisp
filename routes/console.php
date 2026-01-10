<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled Tasks (Laravel 12 format)
// ============================================
// CRITICAL: Real-time sync operations (every minute for responsive UX)
// ============================================

// Disconnect expired users - critical for access control
Schedule::command('users:disconnect-expired')
    ->everyFourMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Process pending user upgrades - time-sensitive
Schedule::command('network:process-upgrades')
    ->everyFourMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sync online status from sessions - drives UI status display
Schedule::command('app:sync-online-status')
    ->everyFourMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ============================================
// HIGH PRIORITY: Core infrastructure (5-10 mins)
// ============================================

// Sync Wireguard peers - maintains VPN connectivity
Schedule::command('wireguard:sync-peers')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Poll Mikrotik routers for active users (optimized: only changed records)
Schedule::command('app:poll-mikrotik-users')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sync router status and metrics
Schedule::command('routers:sync')
    ->everyFourMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ============================================
// MEDIUM PRIORITY: Winbox and cleanup (15+ mins)
// ============================================

// Sync Winbox sessions
Schedule::command('winbox:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Clean up stale sessions
Schedule::command('app:cleanup-stale-sessions')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ============================================
// LOW PRIORITY: Notifications (5-30 mins)
// ============================================

// Send SMS expiry notifications
Schedule::command('sms:send-expiry-notifications')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Send expiry warnings (every 6 hours is fine - not time-critical)
Schedule::command('sms:send-expiry-warnings')
    ->everyHour()
    ->withoutOverlapping()
    ->runInBackground();
