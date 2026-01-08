<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('sms:send-expiry-notifications')->everyFiveMinutes();

        // Sync routers via RouterOS API polling every minute (prevent overlapping runs)
        $schedule->command('routers:sync')
            ->everyFourMinutes()
            ->withoutOverlapping()
            ->runInBackground();


        // Sync WireGuard peers every minute to pick up new registrations quickly
        $schedule->command('wireguard:sync-peers')->everyMinute()->withoutOverlapping()->runInBackground();

        // Disconnect expired users who are still active (every minute)
        $schedule->command('users:disconnect-expired')->everyMinute();

        // Cleanup stale sessions (every 5 minutes)
        $schedule->command('app:cleanup-stale-sessions')->everyFiveMinutes();

        // Process pending package upgrades every minute
        $schedule->command('network:process-upgrades')->everyMinute()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
