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
        $exceptions->renderable(function (\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException $e, \Illuminate\Http\Request $request) {
            $centralRegisterUrl = rtrim(config('app.url'), '/') . '/register';
            return redirect()->away($centralRegisterUrl);
        });
    })->create();
