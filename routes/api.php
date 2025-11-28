<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenants\TenantRadiusController;
use App\Http\Controllers\MikrotikController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Hotspot API Routes (public, tenant-initialized)
|--------------------------------------------------------------------------
*/

Route::middleware([InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])->group(function () {
    // Tenant info

});


Route::post('/radius/auth', [TenantRadiusController::class, 'auth']);
Route::get('/mikrotiks/status', [MikrotikController::class, 'status']);

