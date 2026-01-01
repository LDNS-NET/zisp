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

        // Skip subscription checks if no user is logged in
        if (!$user) {
            return $next($request);
        }

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

}
