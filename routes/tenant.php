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
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });

    // Tenant hotspot landing page (external URL used by MikroTik hotspot login-page)
    Route::get('/hotspot', [CaptivePortalController::class, 'show'])
        ->name('tenants.hotspot.show');

    // Public captive portal APIs, all scoped to the current tenant via Stancl tenancy
    Route::get('/captive-portal/tenant', [CaptivePortalController::class, 'tenant']);
    Route::get('/hotspot/packages', [CaptivePortalController::class, 'packages']);
    Route::post('/hotspot/login', [CaptivePortalController::class, 'login']);
    Route::post('/hotspot/voucher', [CaptivePortalController::class, 'voucher']);
    Route::post('/hotspot/pay', [CaptivePortalController::class, 'pay']);
    Route::post('/hotspot/payment/callback', [CaptivePortalController::class, 'paymentCallback']);
});
