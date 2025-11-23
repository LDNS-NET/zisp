<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register custom artisan commands if needed
        $this->commands([
            \App\Console\Commands\RoutersSync::class,
            \App\Console\Commands\WireGuardSyncPeers::class,
            \App\Console\Commands\SmsSendExpiryNotifications::class,
        ]);
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Schedule commands
            $schedule->command('routers:sync')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground()
                ->before(function () {
                    \Log::info('routers:sync triggered at '.now());
                });

            $schedule->command('wireguard:sync-peers')->everyMinute();
            $schedule->command('sms:send-expiry-notifications')->everyFiveMinutes();
        });
    }
}
