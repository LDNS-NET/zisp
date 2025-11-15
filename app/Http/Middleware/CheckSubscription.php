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
    if ($user->is_suspended) {
        return redirect()->away('https://payment.intasend.com/pay/8d7f60c4-f2c2-4642-a2b6-0654a3cc24e3/');
    }

    return $next($request);
}

}
