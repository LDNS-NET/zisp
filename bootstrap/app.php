<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnsureTenantDomain::class,
        ]);

        // Exempt sync endpoint from CSRF (uses token-based auth)
        $middleware->validateCsrfTokens(except: [
            'mikrotiks/*/sync',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException|\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByIdException $e, \Illuminate\Http\Request $request) {
            $centralRegisterUrl = rtrim(config('app.url'), '/') . '/register';
            return redirect()->away($centralRegisterUrl);
        });
        
        // Missing tenant authentication/session – redirect to tenant login instead of showing 500
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            $host = $request->getHost();
            // If host belongs to a tenant domain, send user to login page on that domain.
            if (\Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->exists()) {
                return redirect()->to('/login');
            }
            // Otherwise let Laravel handle normally (central domain).
        });

        // Generic tenancy exception fallback – log and redirect to central homepage.
        $exceptions->renderable(function (\Stancl\Tenancy\Exceptions\TenancyException $e, \Illuminate\Http\Request $request) {
            report($e);
            return redirect()->away(rtrim(config('app.url'), '/')); // central landing
        });
    })->create();
