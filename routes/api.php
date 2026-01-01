<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenants\CaptivePortalController;
use App\Http\Controllers\Tenants\TenantRadiusController;
use App\Http\Controllers\MikrotikController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Captive Portal API Routes (public, tenant-initialized)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Tenants\MpesaC2BController;

// M-Pesa C2B (Global endpoints to handle callbacks from any Paybill)
Route::post('/mpesa/c2b/validation', [MpesaC2BController::class, 'validation']);
Route::post('/mpesa/c2b/confirmation', [MpesaC2BController::class, 'confirmation']);

Route::middleware([InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])->group(function () {
    // Tenant info
});


Route::post('/radius/auth', [TenantRadiusController::class, 'auth']);
Route::get('/mikrotiks/status', [MikrotikController::class, 'status']);

