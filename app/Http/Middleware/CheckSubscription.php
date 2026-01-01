<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // 1. Handle Authenticated Users
        if ($user) {
            // Suspend user if subscription expired
            if ($user->subscription_expires_at && now()->greaterThan($user->subscription_expires_at) && !$user->is_suspended) {
                $user->update(['is_suspended' => true]);
            }

            // Redirect suspended users to renewal page
            if ($user->is_suspended) {
                if ($request->header('X-Inertia')) {
                    return inertia()->location(route('subscription.renew'));
                }
                return redirect()->route('subscription.renew');
            }
            
            return $next($request);
        }

        // 2. Handle Unauthenticated Guests (Hotspot)
        // Skip check for the suspension page itself to avoid infinite loops
        if ($request->routeIs('hotspot.suspended')) {
            return $next($request);
        }

        $tenant = tenant();
        
        // If tenant is not initialized, try to identify from subdomain
        if (!$tenant) {
            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];
            $centralDomains = config('tenancy.central_domains', []);
            
            if (!in_array($host, $centralDomains)) {
                $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
            }
        }

        if ($tenant && $tenant->isSubscriptionExpired()) {
            return redirect()->route('hotspot.suspended');
        }

        return $next($request);
    }

}
