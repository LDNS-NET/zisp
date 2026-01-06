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
use App\Http\Controllers\Tenants\MomoC2BController;

// M-Pesa C2B (Global endpoints to handle callbacks from any Paybill)
Route::post('/mpesa/c2b/validation', [MpesaC2BController::class, 'validation']);
Route::post('/mpesa/c2b/confirmation', [MpesaC2BController::class, 'confirmation']);

// MoMo C2B (Direct Payment Callback)
Route::post('/momo/c2b/callback', [MomoC2BController::class, 'callback']);

Route::middleware([InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])->group(function () {
    // Tenant info
});


Route::post('/radius/auth', [TenantRadiusController::class, 'auth']);
Route::post('/radius/accounting', [\App\Http\Controllers\Api\RadiusAccountingController::class, 'store']);
Route::get('/mikrotiks/status', [MikrotikController::class, 'status']);

