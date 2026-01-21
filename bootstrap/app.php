<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            //
        ]);

        // Exempt sync and WireGuard registration endpoints from CSRF (use token-based auth)
        $middleware->validateCsrfTokens(except: [
            'mikrotiks/*/sync',
            'mikrotiks/*/register-wireguard',
            'hotspot/callback',
            'mpesa/callback',
            'api/mpesa/c2b/*',
        ]);

        $middleware->alias([
            'customer' => \App\Http\Middleware\CustomerMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'staff_security' => \App\Http\Middleware\StaffSecurityMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException $e, \Illuminate\Http\Request $request) {
            $centralRegisterUrl = rtrim(config('app.url'), '/') . '/register';
            return redirect()->away($centralRegisterUrl);
        });
    })->create();
