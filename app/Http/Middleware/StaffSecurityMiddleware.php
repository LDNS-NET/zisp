<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\UserDevice;
use Carbon\Carbon;
use Inertia\Inertia;

class StaffSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If not logged in, just proceed (let auth middleware handle it)
        if (!$user) {
            return $next($request);
        }

        // Skip security enforcement for SuperAdmins and TenantAdmins (owners)
        if ($user->hasRole('superadmin') || $user->hasRole('tenant_admin')) {
            return $next($request);
        }

        // 1. Working Hours Enforcement (Shift Logic)
        if ($user->working_hours && !$request->routeIs('errors.off-duty') && !$request->routeIs('logout')) {
            $now = Carbon::now();
            $dayOfWeek = strtolower($now->format('l'));
            $schedule = $user->working_hours[$dayOfWeek] ?? null;

            if ($schedule && isset($schedule['start'], $schedule['end'])) {
                $start = Carbon::createFromTimeString($schedule['start']);
                $end = Carbon::createFromTimeString($schedule['end']);

                if (!$now->between($start, $end)) {
                    return redirect()->route('errors.off-duty');
                }
            }
        }

        // 2. IP Whitelisting
        if ($user->allowed_ips && !empty($user->allowed_ips)) {
            $clientIp = $request->ip();
            $isAllowed = false;
            
            foreach ($user->allowed_ips as $allowedIp) {
                if ($this->ipMatches($clientIp, $allowedIp)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                Auth::logout();
                return redirect()->route('login')->with('error', "Access denied: Unauthorized network ($clientIp).");
            }
        }

        // 3. Proxy/VPN Blocking
        $this->checkForProxies($request);

        // 4. Device Locking & Single Session Enforcement
        if ($user->is_device_lock_enabled) {
            $deviceId = $request->header('X-Device-ID') ?? $this->generateFallBackDeviceId($request);
            $userAgent = $request->header('User-Agent');
            $friendlyName = $this->parseUserAgent($userAgent);
            
            // Check if this specific device is registered
            $device = UserDevice::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->first();

            if (!$device) {
                // Check if we reached the limit of registered devices
                $deviceCount = UserDevice::where('user_id', $user->id)->count();
                $limit = $user->security_config['max_devices'] ?? 1;

                if ($deviceCount >= $limit) {
                    Auth::logout();
                    return redirect()->route('login')->with('error', 'Limit reached: This new device is not authorized. Max allowed devices: ' . $limit);
                }

                // Register new device
                $device = UserDevice::create([
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                    'device_name' => $friendlyName,
                    'last_ip' => $request->ip(),
                    'is_locked' => false
                ]);
            }

            if ($device->is_locked) {
                Auth::logout();
                return redirect()->route('login')->with('error', 'Access denied: This device has been locked by administration.');
            }

            // Strict Session Check: If another record from this user was updated in the last 5 minutes 
            // and it's NOT this device, we block it to enforce "One Workstation at a Time".
            $otherActiveDevice = UserDevice::where('user_id', $user->id)
                ->where('device_id', '!=', $deviceId)
                ->where('updated_at', '>', now()->subMinutes(5)) // Active threshold
                ->first();

            if ($otherActiveDevice) {
                Auth::logout();
                return redirect()->route('login')->with('error', 'Security Policy: You have an active workshop session on another device (' . $otherActiveDevice->device_name . '). Please logout there first.');
            }
            
            // Update last seen heartbeat
            $device->touch();
            $device->update(['last_ip' => $request->ip()]);
        }

        return $next($request);
    }

    private function ipMatches($clientIp, $allowedIp) {
        return $clientIp === $allowedIp;
    }

    private function parseUserAgent($ua) {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) return 'Tablet';
        if (preg_match('/Mobile|iP(hone|od|ad)|Android|BlackBerry|IEMobile|Kindle|NetFront|Silk-Accelerated|(hpw|web)OS|Fennec|Minimo|Opera M(obi|ini)|Blazer|Dolfin|Dolphin|Skyfire|Zune/i', $ua)) return 'Mobile Device';
        if (preg_match('/Windows/i', $ua)) return 'Windows Desktop';
        if (preg_match('/Macintosh/i', $ua)) return 'Mac Desktop';
        if (preg_match('/Linux/i', $ua)) return 'Linux Desktop';
        return 'Unknown Station';
    }

    private function checkForProxies(Request $request) {
        $proxyHeaders = [
            'HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_FORWARDED_FOR', 'HTTP_X_FORWARDED',
            'HTTP_FORWARDED', 'HTTP_CLIENT_IP', 'HTTP_FORWARDED_FOR_IP', 'VIA',
            'X_FORWARDED_FOR', 'FORWARDED_FOR', 'X_FORWARDED', 'FORWARDED', 'CLIENT_IP',
            'FORWARDED_FOR_IP', 'HTTP_PROXY_CONNECTION'
        ];

        foreach ($proxyHeaders as $header) {
            if ($request->header($header)) {
                Auth::logout();
                abort(redirect()->route('login')->with('error', 'Access denied: Proxies and VPNs are prohibited.'));
            }
        }
    }

    private function generateFallBackDeviceId(Request $request) {
        return md5($request->header('User-Agent') . $request->ip());
    }
}
