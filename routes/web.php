<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Normal user controllers
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;

// SuperAdmin controllers
use App\Http\Controllers\SuperAdmin\SuperAdminController;
use App\Http\Controllers\Tenants\CaptivePortalController;
use App\Http\Controllers\Tenants\PackageController;
use App\Http\Controllers\Tenants\TenantActiveUsersController;
use App\Http\Controllers\Tenants\TenantEquipmentController;
use App\Http\Controllers\Tenants\TenantExpensesController;
use App\Http\Controllers\Tenants\TenantGeneralSettingsController;
use App\Http\Controllers\Tenants\TenantHotspotSettingsController;
use App\Http\Controllers\Tenants\TenantInvoiceController;
use App\Http\Controllers\Tenants\TenantLeadController;
use App\Http\Controllers\Tenants\TenantMikrotikController;
use App\Http\Controllers\Tenants\TenantNotificationSettingsController;
use App\Http\Controllers\Tenants\TenantPaymentController;
use App\Http\Controllers\Tenants\TenantPaymentGatewayController;
use App\Http\Controllers\Tenants\TenantPayoutSettingsController;
use App\Http\Controllers\Tenants\TenantSettingsController;
use App\Http\Controllers\Tenants\TenantSMSController;
use App\Http\Controllers\Tenants\TenantSMSTemplateController;
use App\Http\Controllers\Tenants\TenantTicketController;
use App\Http\Controllers\Tenants\TenantUserController;
use App\Http\Controllers\Tenants\TenantWhatsappGatewayController;
use App\Http\Controllers\Tenants\VoucherController;

/*
|--------------------------------------------------------------------------
| Central Domain Routes (zyraaf.cloud)
|--------------------------------------------------------------------------
| These routes are ONLY accessible on central domains:
| - Welcome/Landing page
| - Registration pages  
| - Central authentication
| - Marketing pages
| - Public API endpoints
*/

/*
|--------------------------------------------------------------------------
| Public Landing Page (Central Domain Only)
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'central'])->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    })->name('welcome');
});

/*
|--------------------------------------------------------------------------
| Public API Endpoints (Token-based authentication)
|--------------------------------------------------------------------------
| These endpoints are called by MikroTik routers and use token-based auth
| They are accessible from any domain but require valid sync tokens
*/
Route::post('mikrotiks/{mikrotik}/sync', [\App\Http\Controllers\Tenants\TenantMikrotikController::class, 'sync'])->name('mikrotiks.sync');
Route::post('mikrotiks/{mikrotik}/register-wireguard', [\App\Http\Controllers\Tenants\TenantMikrotikController::class, 'registerWireguard'])->name('mikrotiks.registerWireguard');

/*
|--------------------------------------------------------------------------
| Central Authentication Routes
|--------------------------------------------------------------------------
| Standard Laravel authentication routes - only accessible on central domains
*/
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Payment Success Callback (Public)
|--------------------------------------------------------------------------
| Works for system renewals - accessible from any domain
*/
Route::get('/payment/success', function () {
    $user = auth()->user();
    if ($user) {
        $user->update([
            'subscription_expires_at' => now()->addDays(30),
            'is_suspended' => false,
        ]);
    }
    return redirect()->route('dashboard');
})->name('payment.success');

/*
|--------------------------------------------------------------------------
| Profile Routes (Authenticated Users)
|--------------------------------------------------------------------------
| User profile management - accessible from any valid domain context
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| SuperAdmin Routes (Central Domain Only)
|--------------------------------------------------------------------------
| Super admin functionality - restricted to central domains only
*/
Route::middleware(['auth', 'superadmin', 'central'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
        
        // Additional superadmin routes can be added here
    });
