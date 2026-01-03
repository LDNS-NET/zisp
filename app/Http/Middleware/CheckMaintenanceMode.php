<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow SuperAdmin routes and login
        if ($request->is('superadmin/*') || $request->is('admin/*') || $request->is('login')) {
            return $next($request);
        }

        // Allow the maintenance page itself to prevent redirect loops
        if ($request->routeIs('maintenance')) {
            return $next($request);
        }

        try {
            // Check if maintenance mode is enabled in DB
            // We use a try-catch or Schema check to avoid issues during migration
            if (Schema::hasTable('system_settings')) {
                $maintenanceMode = SystemSetting::where('key', 'maintenance_mode')->value('value');

                if ($maintenanceMode && $maintenanceMode == '1') {
                    // Redirect to maintenance page
                    return redirect()->route('maintenance');
                }
            }
        } catch (\Exception $e) {
            // Fail silently and allow request if DB check fails
        }

        return $next($request);
    }
}
