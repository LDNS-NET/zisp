<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\UserDevice;
use Carbon\Carbon;

class StaffSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If not logged in or is superadmin, skip checks
        if (!$user || $user->hasRole('superadmin')) {
            return $next($request);
        }

        // 1. Working Hours Enforcement
        if ($user->working_hours) {
            $now = Carbon::now();
            $dayOfWeek = strtolower($now->format('l'));
            $schedule = $user->working_hours[$dayOfWeek] ?? null;

            if ($schedule && isset($schedule['start'], $schedule['end'])) {
                $start = Carbon::createFromTimeString($schedule['start']);
                $end = Carbon::createFromTimeString($schedule['end']);

                if (!$now->between($start, $end)) {
                    Auth::logout();
                    return redirect()->route('login')->with('error', 'Access denied: Outside working hours.');
                }
            }
        }

        // 2. IP Whitelisting
        if ($user->allowed_ips && !empty($user->allowed_ips)) {
            $clientIp = $request->ip();
            if (!in_array($clientIp, $user->allowed_ips)) {
                Auth::logout();
                return redirect()->route('login')->with('error', "Access denied: Unauthorized network ($clientIp).");
            }
        }

        // 3. Proxy/VPN Blocking (Basic Header Checks)
        $proxyHeaders = [
            'HTTP_VIA',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED_FOR_IP',
            'VIA',
            'X_FORWARDED_FOR',
            'FORWARDED_FOR',
            'X_FORWARDED',
            'FORWARDED',
            'CLIENT_IP',
            'FORWARDED_FOR_IP',
            'HTTP_PROXY_CONNECTION'
        ];

        foreach ($proxyHeaders as $header) {
            if ($request->header($header) || isset($_SERVER[$header])) {
                Auth::logout();
                return redirect()->route('login')->with('error', 'Access denied: Proxies and VPNs are prohibited.');
            }
        }

        // 4. Device Identification & Locking
        if ($user->is_device_lock_enabled) {
            $deviceId = $request->header('X-Device-ID');
            
            if (!$deviceId) {
                // If device ID is missing, we might want to block or allow but flag.
                // For strict security, we block if it's mandatory.
                // return response()->json(['error' => 'Device identification missing.'], 403);
            } else {
                $device = UserDevice::firstOrCreate(
                    ['user_id' => $user->id, 'device_id' => $deviceId],
                    ['device_name' => $request->header('User-Agent'), 'last_ip' => $request->ip()]
                );

                if ($device->is_locked) {
                    Auth::logout();
                    return redirect()->route('login')->with('error', 'Access denied: This device has been locked.');
                }

                // Check for "multiple device" usage if config exists
                $deviceLimit = $user->security_config['max_devices'] ?? 1;
                $activeDevices = UserDevice::where('user_id', $user->id)
                    ->where('is_locked', false)
                    ->count();

                if ($activeDevices > $deviceLimit && !$user->devices()->where('device_id', $deviceId)->exists()) {
                    Auth::logout();
                    return redirect()->route('login')->with('error', 'Access denied: Maximum device limit reached.');
                }
                
                $device->update(['last_ip' => $request->ip()]);
            }
        }

        return $next($request);
    }
}
