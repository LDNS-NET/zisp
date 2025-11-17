<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Tenants\TenantActiveUsersController;
use App\Http\Controllers\Tenants\PackageController;
use App\Http\Controllers\Tenants\TenantUserController;
use App\Http\Controllers\Tenants\TenantLeadController;
use App\Http\Controllers\Tenants\TenantTicketController;
use App\Http\Controllers\Tenants\TenantEquipmentController;
use App\Http\Controllers\Tenants\VoucherController;
use App\Http\Controllers\Tenants\TenantPaymentController;
use App\Http\Controllers\Tenants\TenantInvoiceController;
use App\Http\Controllers\Tenants\TenantExpensesController;
use App\Http\Controllers\Tenants\TenantSMSController;
use App\Http\Controllers\Tenants\TenantSMSTemplateController;
use App\Http\Controllers\Tenants\TenantHotspotSettingsController;
use App\Http\Controllers\Tenants\TenantPaymentGatewayController;
use App\Http\Controllers\Tenants\TenantSmsGatewayController;
use App\Http\Controllers\Tenants\TenantGeneralSettingsController;
use App\Http\Controllers\Tenants\TenantMikrotikController;
use App\Http\Controllers\Tenants\CaptivePortalController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Subdomain Routes ({tenant}.zyraaf.cloud)
|--------------------------------------------------------------------------
| These routes are ONLY accessible on tenant subdomains:
| - Tenant dashboard and management
| - Hotspot captive portal
| - Wi-Fi services and management
| - Tenant-specific features
|
| Middleware Stack:
| - InitializeTenancyByDomain: Sets up tenant context
| - PreventAccessFromCentralDomains: Prevents access from central domains
| - tenant.domain: Custom EnsureTenantDomain middleware
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'tenant.domain', // Custom EnsureTenantDomain middleware
])->group(function () {
    // Redirect root to hotspot (most common tenant entry point)
    Route::get('/', function () {
        return redirect()->route('hotspot.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Hotspot Captive Portal Routes (Public Access)
    |--------------------------------------------------------------------------
    | These routes handle the Wi-Fi captive portal functionality
    | Accessible without authentication for guest users
    */
    Route::get('/hotspot', [CaptivePortalController::class, 'index'])->name('hotspot.index');
    Route::get('/hotspot/packages', [CaptivePortalController::class, 'packages'])->name('hotspot.packages');
    Route::post('/hotspot/login', [CaptivePortalController::class, 'login'])->name('hotspot.login');
    Route::post('/hotspot/voucher', [CaptivePortalController::class, 'voucher'])->name('hotspot.voucher');
    Route::post('/hotspot/pay', [CaptivePortalController::class, 'pay'])->name('hotspot.pay');
    Route::post('/hotspot/payment/callback', [CaptivePortalController::class, 'paymentCallback'])->name('hotspot.payment.callback');
    Route::get('/captive-portal', function () {
        return Inertia::render('CaptivePortal/Index');
    })->name('captive-portal');

    /*
    |--------------------------------------------------------------------------
    | Authenticated Tenant Routes
    |--------------------------------------------------------------------------
    | These routes require authentication and tenant verification
    | Uses TenantAuth middleware to ensure proper tenant isolation
    */
    Route::middleware(['auth', 'verified', 'check.subscription', 'tenant.auth'])->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

        /*
        |--------------------------------------------------------------------------
        | User Management Routes
        |--------------------------------------------------------------------------
        */
        // Active Users Management
        Route::resource('activeusers', TenantActiveUsersController::class);
        
        // Network Users (tenants' customers)
        Route::resource('users', TenantUserController::class);
        Route::delete('/users/bulk-delete', [TenantUserController::class, 'bulkDelete'])->name('users.bulk-delete');
        Route::post('users/details', [TenantUserController::class, 'update'])->name('users.details.update');

        /*
        |--------------------------------------------------------------------------
        | Business Management Routes
        |--------------------------------------------------------------------------
        */
        // Package Management
        Route::resource('packages', PackageController::class)->except(['show']);
        Route::delete('/packages/bulk-delete', [PackageController::class, 'bulkDelete'])->name('packages.bulk-delete');

        // Lead Management
        Route::resource('leads', TenantLeadController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('leads/bulk-delete', [TenantLeadController::class, 'bulkDelete'])->name('leads.bulk-delete');

        // Ticket Management
        Route::resource('tickets', TenantTicketController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('tickets/bulk-delete', [TenantTicketController::class, 'bulkDelete'])->name('tickets.bulk-delete');
        Route::put('/tickets/{ticket}/resolve', [TenantTicketController::class, 'resolve'])->name('tickets.resolve');

        /*
        |--------------------------------------------------------------------------
        | Equipment & Infrastructure Routes
        |--------------------------------------------------------------------------
        */
        // Equipment Management
        Route::resource('equipment', TenantEquipmentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/equipment/bulk-delete', [TenantEquipmentController::class, 'bulkDelete'])->name('equipment.bulk-delete');

        // MikroTik Management
        Route::resource('mikrotiks', TenantMikrotikController::class);
        Route::get('mikrotiks/{mikrotik}/test-connection', [TenantMikrotikController::class, 'testConnection'])->name('mikrotiks.testConnection');
        Route::get('mikrotiks/{mikrotik}/ping', [TenantMikrotikController::class, 'pingRouter'])->name('mikrotiks.ping');
        Route::get('mikrotiks/{mikrotik}/get-devices', [TenantMikrotikController::class, 'getDevices'])->name('mikrotiks.getDevices');
        Route::get('mikrotiks/{mikrotik}/get-interfaces', [TenantMikrotikController::class, 'getInterfaces'])->name('mikrotiks.getInterfaces');
        Route::get('mikrotiks/{mikrotik}/get-wireguard-peers', [TenantMikrotikController::class, 'getWireguardPeers'])->name('mikrotiks.getWireguardPeers');
        Route::get('mikrotiks/{mikrotik}/get-hotspot-users', [TenantMikrotikController::class, 'getHotspotUsers'])->name('mikrotiks.getHotspotUsers');
        Route::get('mikrotiks/{mikrotik}/get-dhcp-leases', [TenantMikrotikController::class, 'getDhcpLeases'])->name('mikrotiks.getDhcpLeases');
        Route::get('mikrotiks/{mikrotik}/get-traffic-stats', [TenantMikrotikController::class, 'getTrafficStats'])->name('mikrotiks.getTrafficStats');
        Route::get('mikrotiks/{mikrotik}/ca.crt', [TenantMikrotikController::class, 'downloadCACert'])->name('mikrotiks.downloadCACert');
        Route::get('mikrotiks/{mikrotik}/reprovision', [TenantMikrotikController::class, 'reprovision'])->name('mikrotiks.reprovision');
        Route::post('mikrotiks/{mikrotik}/provision-hotspot', [TenantMikrotikController::class, 'provisionHotspot'])->name('mikrotiks.provisionHotspot');

        /*
        |--------------------------------------------------------------------------
        | Voucher & Payment Routes
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | Communication Routes
        |--------------------------------------------------------------------------
        */
        // SMS Management
        Route::resource('sms', TenantSMSController::class)->only(['index','create', 'store', 'destroy']);

        // SMS Templates
        Route::resource('smstemplates', TenantSMSTemplateController::class)->only(['index', 'create','update','store', 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | Settings Routes
        |--------------------------------------------------------------------------
        */
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

        // General Settings
        Route::get('settings/general', [TenantGeneralSettingsController::class, 'edit'])->name('settings.general.edit');
        Route::post('settings/general', [TenantGeneralSettingsController::class, 'update'])->name('settings.general.update');
    });
});
