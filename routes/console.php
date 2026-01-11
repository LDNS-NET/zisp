<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled Tasks (Laravel 12 format)
Schedule::command('sms:send-expiry-notifications')->everyFiveMinutes();

// Send expiry warnings every 6 hours
Schedule::command('sms:send-expiry-warnings')->everySixHours();

// Sync Mikrotik active users every 4 minutes
Schedule::command('routers:sync')
    ->everyFourMinutes()
    ->withoutOverlapping()
    ->runInBackground();

    // Sync Wireguard peers every minute
Schedule::command('wireguard:sync-peers')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

    // Disconnect expired users every minute
Schedule::command('users:disconnect-expired')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Sync Winbox sessions every 2 minutes
Schedule::command('winbox:sync')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->runInBackground();


    // Clean up stale sessions every 5 minutes
Schedule::command('app:cleanup-stale-sessions')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sync online flags from tenant_active_sessions to network_users
Schedule::command('app:sync-online-status')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();  

    // Process pending user upgrades every minute. this handles time-based upgrades for customers who upgrade their packages
Schedule::command('network:process-upgrades')
    ->everyMinute()
    ->withoutOverlapping();

// Poll Mikrotik routers for active users and sync every 3 minutes (reduces DB/API load for large deployments)
Schedule::command('app:poll-mikrotik-users')
    ->everyThreeMinutes()
    ->withoutOverlapping()
    ->runInBackground();
