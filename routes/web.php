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
| Central Domain Only Routes (Public)
|--------------------------------------------------------------------------
| These routes are only accessible on central domains (zyraaf.cloud)
| They include welcome page, registration, and central authentication
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
| Public MikroTik Sync Endpoints (Token-based authentication)
|--------------------------------------------------------------------------
| These endpoints are called by MikroTik routers and use token-based auth
| They are accessible from any domain but require valid sync tokens
*/
Route::post('mikrotiks/{mikrotik}/sync', [TenantMikrotikController::class, 'sync'])->name('mikrotiks.sync');
Route::post('mikrotiks/{mikrotik}/register-wireguard', [TenantMikrotikController::class, 'registerWireguard'])->name('mikrotiks.registerWireguard');

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
| Authenticated User Routes (Tenant Dashboard & Features)
|--------------------------------------------------------------------------
| These routes require authentication and valid subscription
| They are automatically handled by EnsureTenantDomain middleware globally
| - Valid tenant subdomain + session → access tenant features
| - Valid tenant subdomain + no session → redirect to tenant login
| - Invalid subdomain → redirect to central registration
*/
Route::middleware(['auth', 'verified', 'check.subscription'])
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

        // Active Users Management
        Route::resource('activeusers', TenantActiveUsersController::class);

        // Package Management
        Route::resource('packages', PackageController::class)->except(['show']);
        Route::delete('/packages/bulk-delete', [PackageController::class, 'bulkDelete'])->name('packages.bulk-delete');

        // User Management (Network Users)
        Route::resource('users', TenantUserController::class);
        Route::delete('/users/bulk-delete', [TenantUserController::class, 'bulkDelete'])->name('users.bulk-delete');
        Route::post('users/details', [TenantUserController::class, 'update'])->name('users.details.update');

        // Lead Management
        Route::resource('leads', TenantLeadController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('leads/bulk-delete', [TenantLeadController::class, 'bulkDelete'])->name('leads.bulk-delete');

        // Ticket Management
        Route::resource('tickets', TenantTicketController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('tickets/bulk-delete', [TenantTicketController::class, 'bulkDelete'])->name('tickets.bulk-delete');
        Route::put('/tickets/{ticket}/resolve', [TenantTicketController::class, 'resolve'])->name('tickets.resolve');

        // Equipment Management
        Route::resource('equipment', TenantEquipmentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/equipment/bulk-delete', [TenantEquipmentController::class, 'bulkDelete'])->name('equipment.bulk-delete');
        
        // Voucher Management
        Route::resource('vouchers', VoucherController::class);
        Route::post('/vouchers/{voucher}/send', [VoucherController::class, 'send'])->name('vouchers.send');
        Route::delete('/vouchers/bulk-delete', [VoucherController::class, 'bulkDelete'])->name('vouchers.bulk-delete');

        // Payment Management
        Route::resource('payments', TenantPaymentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/payments/bulk-delete', [TenantPaymentController::class, 'bulkDelete'])->name('payments.bulk-delete');

        // Invoice Management
        Route::resource('invoices', TenantInvoiceController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/invoices/bulk-delete', [TenantInvoiceController::class, 'bulkDelete'])->name('invoices.bulk-delete');

        // Expense Management
        Route::resource('expenses', TenantExpensesController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/expenses/bulk-delete', [TenantExpensesController::class, 'bulkDelete'])->name('expenses.bulk-delete');
        
        // SMS Management
        Route::resource('sms', TenantSMSController::class)->only(['index','create', 'store', 'destroy']);

        // SMS Templates
        Route::resource('smstemplates', TenantSMSTemplateController::class)->only(['index', 'create','update','store', 'destroy']);

        // Hotspot Settings
        Route::get('settings/hotspot', [TenantHotspotSettingsController::class, 'edit'])->name('settings.hotspot.edit');
        Route::post('settings/hotspot', [TenantHotspotSettingsController::class, 'update'])->name('settings.hotspot.update');

        // Payment Gateway Settings
        Route::get('settings/payment', [TenantPaymentGatewayController::class, 'edit'])->name('settings.payment.edit');
        Route::post('settings/payment', [TenantPaymentGatewayController::class, 'update'])->name('settings.payment.update');

        // SMS Gateway Settings
        Route::get('settings/sms', [TenantSmsGatewayController::class, 'edit'])->name('settings.sms.edit');
        Route::post('settings/sms', [TenantSmsGatewayController::class, 'update'])->name('settings.sms.update');
        Route::get('/settings/sms/show', [TenantSmsGatewayController::class, 'show'])->name('settings.sms.show');
        Route::get('/settings/sms/json', [SmsGatewayController::class, 'getGateway'])->name('settings.sms.json');

        // General Settings
        Route::get('settings/general', [TenantGeneralSettingsController::class, 'edit'])->name('settings.general.edit');
        Route::post('settings/general', [TenantGeneralSettingsController::class, 'update'])->name('settings.general.update');

        // MikroTik Management
        Route::resource('mikrotiks', TenantMikrotikController::class);
        Route::get('mikrotiks/{mikrotik}/test-connection', [TenantMikrotikController::class, 'testConnection'])->name('mikrotiks.testConnection');
        Route::get('mikrotiks/{mikrotik}/ping', [TenantMikrotikController::class, 'pingRouter'])->name('mikrotiks.ping');
        Route::get('mikrotiks/{mikrotik}/status', [TenantMikrotikController::class, 'getStatus'])->name('mikrotiks.status');
        Route::post('mikrotiks/{mikrotik}/set-ip', [TenantMikrotikController::class, 'setIp'])->name('mikrotiks.setIp');
        Route::post('mikrotiks/validate', [TenantMikrotikController::class, 'validateRouter'])->name('mikrotiks.validate');
        Route::get('mikrotiks/{mikrotik}/download-setup-script', [TenantMikrotikController::class, 'downloadSetupScript'])->name('mikrotiks.downloadSetupScript');
        Route::get('mikrotiks/{mikrotik}/download-radius-script', [TenantMikrotikController::class, 'downloadRadiusScript'])->name('mikrotiks.downloadRadiusScript');
        Route::get('mikrotiks/{mikrotik}/download-advanced-config', [TenantMikrotikController::class, 'downloadAdvancedConfig'])->name('mikrotiks.downloadAdvancedConfig');
        Route::get('mikrotiks/{mikrotik}/remote-management', [TenantMikrotikController::class, 'remoteManagement'])->name('mikrotiks.remoteManagement');
        Route::get('mikrotiks/{mikrotik}/ca.crt', [TenantMikrotikController::class, 'downloadCACert'])->name('mikrotiks.downloadCACert');
        Route::get('mikrotiks/{mikrotik}/reprovision', [TenantMikrotikController::class, 'reprovision'])->name('mikrotiks.reprovision');
        Route::post('mikrotiks/{mikrotik}/provision-hotspot', [TenantMikrotikController::class, 'provisionHotspot'])->name('mikrotiks.provisionHotspot');

        // Captive Portal (Internal Dashboard View)
        Route::get('/captive-portal', function () {
            return Inertia::render('CaptivePortal/Index');
        })->name('captive-portal');

        // Hotspot Package & Payment Routes (Internal)
        Route::get('/hotspot/packages', [CaptivePortalController::class, 'packages']);
        Route::post('/hotspot/login', [CaptivePortalController::class, 'login']);
        Route::post('/hotspot/voucher', [CaptivePortalController::class, 'voucher']);
        Route::post('/hotspot/pay', [CaptivePortalController::class, 'pay']);
        Route::post('/hotspot/payment/callback', [CaptivePortalController::class, 'paymentCallback']);
    });

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
        // Dashboard
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
        
        // Additional superadmin routes can be added here
    });
