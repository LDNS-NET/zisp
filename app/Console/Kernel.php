<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('sms:send-expiry-notifications')->everyFiveMinutes();
        
        // Check MikroTik router status every 3 minutes
        $schedule->command('mikrotik:check-status')->everyThreeMinutes();

        // Sync WireGuard peers every minute to pick up new registrations quickly
        $schedule->command('wireguard:sync-peers')->everyMinute();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
