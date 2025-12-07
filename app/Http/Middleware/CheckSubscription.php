<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Inertia\Inertia;

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

            // Default static link as fallback
            $paymentUrl = 'https://payment.intasend.com/pay/8d7f60c4-f2c2-4642-a2b6-0654a3cc24e3/';

            if ($domain) {
                $protocol = $request->secure() ? 'https://' : 'http://';
                $returnUrl = $protocol . $domain . '/payment/success';

                try {
                    $publicKey = env('INTASEND_PUBLIC_KEY');
                    \Illuminate\Support\Facades\Log::info('IntaSend API Request', [
                        'url' => 'https://payment.intasend.com/api/v1/checkout/',
                        'public_key_present' => !empty($publicKey),
                        'email' => $user->email,
                        'redirect_url' => $returnUrl,
                    ]);

                    // Generate dynamic checkout link via IntaSend API
                    $response = \Illuminate\Support\Facades\Http::post('https://payment.intasend.com/api/v1/checkout/', [
                        'public_key' => $publicKey,
                        'amount' => 1000, // Default subscription amount
                        'currency' => 'KES',
                        'email' => $user->email ?? 'customer@example.com',
                        'redirect_url' => $returnUrl,
                        'api_ref' => 'SUB-' . $user->id . '-' . time(),
                        'method' => 'API-Checkout'
                    ]);

                    \Illuminate\Support\Facades\Log::info('IntaSend API Response', [
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['url'])) {
                            return Inertia::location($data['url']);
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::error('IntaSend API Failed', ['response' => $response->body()]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('IntaSend API Exception', ['error' => $e->getMessage()]);
                }

                // Fallback: Append redirect_url to static link if API fails
                $paymentUrl .= '?' . http_build_query(['redirect_url' => $returnUrl]);
            }

            return Inertia::location($paymentUrl);
        }

        return $next($request);
    }

}
