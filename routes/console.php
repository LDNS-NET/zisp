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

// Sync Mikrotik active users every 5 minutes (offset by 1)
Schedule::command('routers:sync')
    ->cron('1-59/5 * * * *')
    ->withoutOverlapping()
    ->runInBackground();

    // Sync Wireguard peers every minute
Schedule::command('wireguard:sync-peers')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

    // Disconnect expired users every 10 minutes (offset by 2)
Schedule::command('users:disconnect-expired')
    ->cron('2-59/10 * * * *')
    ->withoutOverlapping()
    ->runInBackground();

// Sync Winbox sessions every 10 minutes (offset by 3)
Schedule::command('winbox:sync')
    ->cron('3-59/10 * * * *')
    ->withoutOverlapping()
    ->runInBackground();


    // Clean up stale sessions every 10 minutes (offset by 4)
Schedule::command('app:cleanup-stale-sessions')
    ->cron('4-59/10 * * * *')
    ->withoutOverlapping()
    ->runInBackground();


    // Process pending user upgrades every 10 minutes (offset by 6)
Schedule::command('network:process-upgrades')
    ->cron('6-59/10 * * * *')
    ->withoutOverlapping();

// Poll Mikrotik routers for active users and sync every 10 minutes (offset by 7)
Schedule::command('app:poll-mikrotik-users')
    ->cron('7-59/10 * * * *')
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

// Sync QuickBooks data every 30 minutes
Schedule::command('quickbooks:sync')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Sync TR-069 devices from GenieACS every 10 minutes
Schedule::job(new \App\Jobs\SyncGenieACSDevicesJob)
    ->everyTwoMinutes()
    ->withoutOverlapping();
