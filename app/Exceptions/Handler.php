<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     */
    protected array $dontReport = [
        TokenMismatchException::class,
    ];

    /**
     * Register any exception handling callbacks for the application.
     */
    public function register(): void
    {
        // 1️⃣ Handle unknown tenant domains → redirect to central registration page
        $this->renderable(function (TenantCouldNotBeIdentifiedException $e, Request $request) {
            $centralRegisterUrl = rtrim(config('app.url'), '/') . '/register';
            
            // Log the failed tenant identification attempt
            \Log::warning('Tenant could not be identified', [
                'host' => $request->getHost(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return redirect()->away($centralRegisterUrl);
        });

        // 2️⃣ Handle unauthenticated access on tenant sub-domains → redirect to tenant login
        $this->renderable(function (AuthenticationException $e, Request $request) {
            $host = $request->getHost();
            $centralDomains = config('tenancy.central_domains', []);

            // If we are on a central domain, use the default behavior
            if (in_array($host, $centralDomains, true)) {
                return redirect()->guest(route('login'));
            }

            // For tenant subdomains, redirect to tenant-specific login
            \Log::info('Unauthenticated access attempt on tenant domain', [
                'host' => $host,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return redirect()->guest('/login');
        });

        // 3️⃣ Handle 404 errors on tenant domains → redirect appropriately
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            $host = $request->getHost();
            $centralDomains = config('tenancy.central_domains', []);

            // If this is a tenant domain and the route doesn't exist
            if (!in_array($host, $centralDomains, true)) {
                // Check if it's a valid tenant domain
                if (\Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->exists()) {
                    // Valid tenant domain but invalid route - redirect to tenant login
                    if (!auth()->check()) {
                        return redirect()->to('/login');
                    }
                    // Authenticated user on invalid route - let them see 404
                } else {
                    // Invalid tenant domain - redirect to central registration
                    $centralRegisterUrl = rtrim(config('app.url'), '/') . '/register';
                    \Log::info('404 on invalid tenant domain', [
                        'host' => $host,
                        'path' => $request->path(),
                        'ip' => $request->ip(),
                    ]);
                    return redirect()->away($centralRegisterUrl);
                }
            }
        });

        // 4️⃣ Handle general tenant-related errors gracefully
        $this->renderable(function (Throwable $e, Request $request) {
            $host = $request->getHost();
            $centralDomains = config('tenancy.central_domains', []);

            // Only handle tenant-related errors on tenant subdomains
            if (!in_array($host, $centralDomains, true)) {
                // Check if this is a tenant domain
                if (\Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->exists()) {
                    // Log the error for debugging
                    \Log::error('Tenant error occurred', [
                        'host' => $host,
                        'path' => $request->path(),
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'ip' => $request->ip(),
                    ]);

                    // For authenticated users, you might want to show a friendly error page
                    if (auth()->check()) {
                        // You could redirect to a tenant error page or dashboard
                        return redirect()->to('/dashboard')->with('error', 
                            'An error occurred. Please try again or contact support.');
                    }

                    // For unauthenticated users, redirect to login
                    return redirect()->to('/login')->with('error', 
                        'Please login to continue.');
                }
            }
        });

        // 5️⃣ Handle CSRF token mismatches gracefully
        $this->renderable(function (TokenMismatchException $e, Request $request) {
            $host = $request->getHost();
            $centralDomains = config('tenancy.central_domains', []);

            \Log::warning('CSRF token mismatch', [
                'host' => $host,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            // Redirect back to the previous page with an error message
            return redirect()->back()->with('error', 
                'Your session has expired. Please try again.');
        });
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        // If we are on a central domain, use the default behavior
        if (in_array($host, $centralDomains, true)) {
            return redirect()->guest(route('login'));
        }

        // For tenant subdomains, redirect to tenant-specific login
        return redirect()->guest('/login');
    }
}
