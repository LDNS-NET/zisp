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
            $tenant = tenant() ?: $user->tenant;
            $domain = $tenant ? $tenant->domains()->first()->domain : null;

            if ($domain) {
                $protocol = $request->secure() ? 'https://' : 'http://';
                $returnUrl = $protocol . $domain . '/payment/success';

                try {
                    $publicKey = env('INTASEND_PUBLIC_KEY');
                    $secretKey = env('INTASEND_SECRET_KEY');

                    // Split name into first and last if available
                    $parts = explode(' ', $user->name ?? '', 2);
                    $firstName = $parts[0] ?? null;
                    $lastName = $parts[1] ?? null;

                    \Illuminate\Support\Facades\Log::info('IntaSend API Request', [
                        'url' => 'https://payment.intasend.com/api/v1/checkout/',
                        'public_key_present' => !empty($publicKey),
                        'secret_key_present' => !empty($secretKey),
                        'email' => $user->email,
                        'redirect_url' => $returnUrl,
                    ]);

                    // Generate dynamic checkout link via IntaSend API
                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                        'Authorization' => 'Bearer ' . $secretKey,
                    ])->post('https://payment.intasend.com/api/v1/checkout/', [
                                'public_key' => $publicKey,
                                'amount' => 1000, // Default subscription amount
                                'currency' => 'KES',
                                'email' => $user->email ?? 'customer@example.com',
                                'phone_number' => $user->phone,
                                'first_name' => $firstName,
                                'last_name' => $lastName,
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
                        // Temporary debug: Show error to user
                        return response()->json($response->json(), 500);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('IntaSend API Exception', ['error' => $e->getMessage()]);
                    return response()->json(['error' => $e->getMessage()], 500);
                }
            } else {
                // Should not happen for tenants, but safe fallback
                abort(403, 'Tenant domain not found.');
            }

            // If we reach here, something went wrong with logic flow
            abort(500, 'Unable to initiate payment.');
        }

        return $next($request);
    }

}
