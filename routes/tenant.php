<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Tenants\CaptivePortalController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| These routes are automatically wrapped with tenant-specific middleware:
| - InitializeTenancyByDomain: Sets up tenant context
| - PreventAccessFromCentralDomains: Prevents central domain access
| - tenant.domain: Validates domain and handles authentication redirects
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    
    // Tenant hotspot landing page (external URL used by MikroTik hotspot login-page)
    Route::get('/hotspot', [CaptivePortalController::class, 'show'])
        ->name('tenants.hotspot.show');

    // Public captive portal APIs, all scoped to the current tenant via Stancl tenancy
    // These endpoints should be accessible without authentication for hotspot functionality
    Route::prefix('captive-portal')->group(function () {
        Route::get('/tenant', [CaptivePortalController::class, 'tenant']);
        Route::get('/packages', [CaptivePortalController::class, 'packages']);
        Route::post('/login', [CaptivePortalController::class, 'login']);
        Route::post('/voucher', [CaptivePortalController::class, 'voucher']);
        Route::post('/pay', [CaptivePortalController::class, 'pay']);
        Route::post('/payment/callback', [CaptivePortalController::class, 'paymentCallback']);
    });

    // Legacy hotspot routes (maintained for backward compatibility)
    Route::prefix('hotspot')->group(function () {
        Route::get('/packages', [CaptivePortalController::class, 'packages']);
        Route::post('/login', [CaptivePortalController::class, 'login']);
        Route::post('/voucher', [CaptivePortalController::class, 'voucher']);
        Route::post('/pay', [CaptivePortalController::class, 'pay']);
        Route::post('/payment/callback', [CaptivePortalController::class, 'paymentCallback']);
    });
});
