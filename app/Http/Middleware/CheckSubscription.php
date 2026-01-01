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
        if ($user->subscription_expires_at && now()->greaterThan($user->subscription_expires_at)) {
            $user->update(['is_suspended' => true]);
        }

        // Restore subscription if user comes from IntaSend payment redirect
        if ($user->is_suspended && $request->query('payment') === 'success') {
            $user->update([
                'subscription_expires_at' => now()->addDays(30),
                'is_suspended' => false,
            ]);
        }

        // Redirect suspended users to payment if not just paid
        if ($user->is_suspended || ($user->subscription_expires_at && now()->greaterThan($user->subscription_expires_at))) {
            // If it's an Inertia request, we should return a redirect that Inertia handles
            if ($request->header('X-Inertia')) {
                return inertia()->location(route('subscription.renew'));
            }
            return redirect()->route('subscription.renew');
        }

        return $next($request);
    }

}
