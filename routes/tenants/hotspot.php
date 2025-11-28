<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenants\HotspotPortalController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// Public hotspot routes bound to tenant by domain
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Mikrotik hotspot landing page
    Route::get('/hotspot', [HotspotPortalController::class, 'index'])
        ->name('hotspot.portal');
});
