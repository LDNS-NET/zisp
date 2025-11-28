<?php

use App\Http\Controllers\Tenants\HotspotPortalController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Hotspot Portal Routes
|--------------------------------------------------------------------------
|
| These routes are executed in the tenant context, initialised by the
| domain that triggered the request. They expose a single GET endpoint
| which Mikrotik routers redirect unauthenticated users to.
|
*/

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/hotspot', [HotspotPortalController::class, 'index'])
        ->name('tenant.hotspot.portal');
});
