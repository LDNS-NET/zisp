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
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

    // Sync Wireguard peers every minute
Schedule::command('wireguard:sync-peers')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

    // Disconnect expired users every minute
Schedule::command('users:disconnect-expired')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sync Winbox sessions every 2 minutes
Schedule::command('winbox:sync')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();


    // Clean up stale sessions every 5 minutes
Schedule::command('app:cleanup-stale-sessions')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sync online flags from tenant_active_sessions to network_users
Schedule::command('app:sync-online-status')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();  

    // Process pending user upgrades every minute. this handles time-based upgrades for customers who upgrade their packages
Schedule::command('network:process-upgrades')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Poll Mikrotik routers for active users and sync every 3 minutes (reduces DB/API load for large deployments)
Schedule::command('app:poll-mikrotik-users')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Prune deleted routers older than 5 days
Schedule::call(function () {
    \App\Models\Tenants\TenantMikrotik::onlyTrashed()
        ->where('deleted_at', '<', now()->subDays(5))
        ->forceDelete();
})->daily();

// Aggregate traffic data hourly for analytics
Schedule::command('analytics:aggregate-traffic --hours=1')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Sync TR-069 devices from GenieACS every 10 minutes
Schedule::job(new \App\Jobs\SyncGenieACSDevicesJob)
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->runInBackground();
