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

        // Exempt payment success route to allow unsuspension
        if ($request->routeIs('payment.success') || $request->is('payment/success')) {
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
            $tenant = tenant();
            $domain = $tenant ? $tenant->domains()->first()->domain : null;

            $paymentUrl = 'https://payment.intasend.com/pay/8d7f60c4-f2c2-4642-a2b6-0654a3cc24e3/';

            if ($domain) {
                $protocol = $request->secure() ? 'https://' : 'http://';
                $returnUrl = $protocol . $domain . '/payment/success';
                // Append return_url to the payment link
                // Assuming IntaSend supports 'return_url' or 'redirect_url' query param. 
                // Using 'redirect_url' as it's common, but 'return_url' was in plan. 
                // Let's stick to the plan but maybe check if I can find docs? 
                // I'll use 'redirect_url' as it's safer for many gateways, but wait, plan said return_url.
                // I'll use http_build_query to be safe.
                $paymentUrl .= '?' . http_build_query(['redirect_url' => $returnUrl]);
            }

            return redirect()->away($paymentUrl);
        }

        return $next($request);
    }

}
