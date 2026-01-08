<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled Tasks (Laravel 12 format)
Schedule::command('sms:send-expiry-notifications')->everyFiveMinutes();
Schedule::command('sms:send-expiry-warnings')->everySixHours();

Schedule::command('routers:sync')
    ->everyFourMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('wireguard:sync-peers')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('users:disconnect-expired')->everyMinute();

Schedule::command('winbox:sync')
    ->everyTwoMinutes()
    ->withoutOverlapping();

Schedule::command('app:cleanup-stale-sessions')->everyFiveMinutes();

// Sync online flags from tenant_active_sessions to network_users
Schedule::command('app:sync-online-status')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('network:process-upgrades')
    ->everyMinute()
    ->withoutOverlapping();
