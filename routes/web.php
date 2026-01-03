<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Normal user controllers
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;

// SuperAdmin controllers

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
use App\Http\Controllers\Tenants\TenantSmsGatewayController;
use App\Http\Controllers\Tenants\TenantTicketController;
use App\Http\Controllers\Tenants\TenantUserController;
use App\Http\Controllers\Tenants\TenantWhatsappGatewayController;
use App\Http\Controllers\Tenants\VoucherController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\Tenants\TenantHotspotController;
use App\Http\Controllers\Tenants\MikrotikDetailsController;
use App\Http\Controllers\Tenants\SubscriptionController;
use App\Http\Controllers\Tenants\MomoController;

// SuperAdmin controllers
use App\Http\Controllers\SuperAdmin\SuperAdminController;
use App\Http\Controllers\SuperAdmin\UsersController;
use App\Http\Controllers\SuperAdmin\PaymentsController;
use App\Http\Controllers\SuperAdmin\AllMikrotiksController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public route for MikroTik to download hotspot template files
// Public route for MikroTik to download hotspot template files
Route::get('hotspot-templates/{file}', function ($file) {
    // Allowed files
    $allowedFiles = ['login.html', 'alogin.html', 'rlogin.html', 'flogin.html', 'logout.html', 'redirect.html', 'error.html'];

    if (!in_array($file, $allowedFiles)) {
        abort(404, 'Template file not found');
    }

    $templatePath = resource_path('scripts/zisp-hotspot/' . $file);

    if (!file_exists($templatePath)) {
        abort(404, 'Template file not found');
    }

    $content = file_get_contents($templatePath);

    // Inject the tenant domain from the request host header
    // This allows the HTML file (served locally by MikroTik) to know the correct cloud URL
    $domain = request()->getHost();
    $content = str_replace('{{DOMAIN}}', $domain, $content);

    return response($content)
        ->header('Content-Type', 'text/html')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
})->name('hotspot.templates.public');

// Hotspot routes (protected by subscription check) replace subscription with a safer middleware for hotspot safe redirects

Route::middleware(['check.subscription'])->group(function () {

    Route::get('/hotspot/success', function () {
        return view('hotspot.success');
    })->name('hotspot.success');

    Route::get('/hotspot/suspended', [TenantHotspotController::class, 'suspended'])->name('hotspot.suspended');
    
    Route::resource('hotspot', TenantHotspotController::class)->except(['show']);
    Route::post('/hotspot/purchase-stk-push', [TenantHotspotController::class, 'purchaseSTKPush'])->name('hotspot.purchase-stk-push');
    Route::post('/hotspot/checkout', [TenantHotspotController::class, 'checkout'])->name('hotspot.checkout');
    Route::post('/hotspot/callback', [TenantHotspotController::class, 'callback'])->name('hotspot.callback');
    Route::get('/hotspot/payment-status/{identifier}', [TenantHotspotController::class, 'checkPaymentStatus'])->name('hotspot.check-status');
    Route::post('/hotspot/voucher-auth', [VoucherController::class, 'authenticate'])->name('voucher.authenticate');

    // MoMo Routes
    Route::post('/hotspot/momo/checkout', [MomoController::class, 'checkout'])->name('hotspot.momo.checkout');
    Route::post('/hotspot/momo/callback', [MomoController::class, 'callback'])->name('hotspot.momo.callback');

    // Paystack routes
    Route::post('/paystack/webhook', [App\Http\Controllers\Tenants\PaystackController::class, 'webhook'])->name('paystack.webhook');
    Route::get('/paystack/callback', [App\Http\Controllers\Tenants\PaystackController::class, 'handleCallback'])->name('paystack.callback');
    
    // Flutterwave routes
    Route::post('/flutterwave/webhook', [App\Http\Controllers\Tenants\FlutterwaveController::class, 'webhook'])->name('flutterwave.webhook');
    Route::get('/flutterwave/callback', [App\Http\Controllers\Tenants\FlutterwaveController::class, 'handleCallback'])->name('flutterwave.callback');

    Route::get('/hotspot/momo/status/{referenceId}', [MomoController::class, 'checkStatus'])->name('hotspot.momo.status');
});


Route::get('mikrotiks/status', [MikrotikController::class, 'status'])->name('mikrotiks.health');

/*
|--------------------------------------------------------------------------
| Phone-home sync endpoint removed.
| Router monitoring now uses RouterOS API polling via SyncRoutersCommand.
|--------------------------------------------------------------------------
*/

// WireGuard registration endpoint (public, token-authenticated)
Route::post('mikrotiks/{mikrotik}/register-wireguard', [TenantMikrotikController::class, 'registerWireguard'])->name('mikrotiks.registerWireguard');

// Public route for downloading setup script (uses token authentication)
Route::get('mikrotiks/{mikrotik}/download-script', [TenantMikrotikController::class, 'downloadScriptPublic'])->name('mikrotiks.downloadScriptPublic');

/*
|--------------------------------------------------------------------------
| Authenticated + Subscription Checked Routes (Tenants)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'tenant.domain'])
    ->group(function () {
        // Subscription & Renewal (Accessible even if expired)
        Route::get('/subscription/renew', [SubscriptionController::class, 'showRenewal'])->name('subscription.renew');
        Route::post('/subscription/initialize-payment', [SubscriptionController::class, 'initializePayment'])->name('subscription.initialize-payment');
        Route::get('/subscription/callback', [SubscriptionController::class, 'handleCallback'])->name('subscription.callback');

        Route::middleware(['check.subscription'])->group(function () {


        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

        //Active Users
        Route::resource('activeusers', TenantActiveUsersController::class);

        //tenants packages
        Route::resource('packages', PackageController::class)->except(['show']);
        Route::delete('/packages/bulk-delete', [PackageController::class, 'bulkDelete'])->name('packages.bulk-delete');

        //network users( tenants )
        Route::resource('users', TenantUserController::class);
        Route::delete('/users/bulk-delete', [TenantUserController::class, 'bulkDelete'])->name('users.bulk-delete');
        Route::post('users/details', [TenantUserController::class, 'update'])->name('users.details.update');


        //Leads
        Route::resource('leads', TenantLeadController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('leads/bulk-delete', [TenantLeadController::class, 'bulkDelete'])->name('leads.bulk-delete');

        //tickets
        Route::resource('tickets', TenantTicketController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('tickets/bulk-delete', [TenantTicketController::class, 'bulkDelete'])->name('tickets.bulk-delete');
        Route::put('/tickets/{ticket}/resolve', [TenantTicketController::class, 'resolve'])->name('tickets.resolve');

        //Equipment
        Route::resource('equipment', TenantEquipmentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/equipment/bulk-delete', [TenantEquipmentController::class, 'bulkDelete'])->name('equipment.bulk-delete');

        //vouchers
        Route::get('/vouchers/print', [VoucherController::class, 'print'])->name('vouchers.print');
        Route::delete('/vouchers/bulk-delete', [VoucherController::class, 'bulkDelete'])->name('vouchers.bulk-delete');
        Route::resource('vouchers', VoucherController::class);
        Route::post('/vouchers/{voucher}/send', [VoucherController::class, 'send'])->name('vouchers.send');

        //Payments
        Route::resource('payments', TenantPaymentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/payments/bulk-delete', [TenantPaymentController::class, 'bulkDelete'])->name('payments.bulk-delete');

        //Invoices
        Route::resource('invoices', TenantInvoiceController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/invoices/bulk-delete', [TenantInvoiceController::class, 'bulkDelete'])->name('invoices.bulk-delete');

        //Expenses
        Route::resource('expenses', TenantExpensesController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::delete('/expenses/bulk-delete', [TenantExpensesController::class, 'bulkDelete'])->name('expenses.bulk-delete');

        //SMS
        Route::resource('sms', TenantSMSController::class)->only(['index', 'create', 'store', 'destroy']);
        Route::delete('/sms/bulk-delete', [TenantSMSController::class, 'bulkDelete'])->name('sms.bulk-delete');

        // SMS Templates
        Route::resource('smstemplates', TenantSMSTemplateController::class)->only(['index', 'create', 'update', 'store', 'destroy']);
        Route::delete('/smstemplates/bulk-delete', [TenantSMSTemplateController::class, 'bulkDelete'])->name('smstemplates.bulk-delete');

        //Hotspot Settings
        Route::get('settings/hotspot', [TenantHotspotSettingsController::class, 'edit'])->name('settings.hotspot.edit');
        Route::post('settings/hotspot', [TenantHotspotSettingsController::class, 'update'])->name('settings.hotspot.update');

        //payment gateways settings
        Route::get('settings/payment', [TenantPaymentGatewayController::class, 'edit'])->name('settings.payment.edit');
        Route::post('settings/payment', [TenantPaymentGatewayController::class, 'update'])->name('settings.payment.update');

        //sms gateway settings
        Route::get('settings/sms', [TenantSmsGatewayController::class, 'edit'])->name('settings.sms.edit');
        Route::post('settings/sms', [TenantSmsGatewayController::class, 'update'])->name('settings.sms.update');
        Route::get('/settings/sms/show', [TenantSmsGatewayController::class, 'show'])->name('settings.sms.show');
        Route::get('/settings/sms/json', [TenantSmsGatewayController::class, 'getGateway'])->name('settings.sms.json');



        //general settings
        Route::get('settings/general', [TenantGeneralSettingsController::class, 'edit'])->name('settings.general.edit');
        Route::post('settings/general', [TenantGeneralSettingsController::class, 'update'])->name('settings.general.update');

        //Mikrotik Details
        Route::resource('mikrotikdetails', MikrotikDetailsController::class)->only(['index']);


        //mikrotiks
        Route::post('mikrotiks/{mikrotik}/reboot', [TenantMikrotikController::class, 'reboot'])->name('mikrotiks.reboot');
        Route::post('mikrotiks/{mikrotik}/identity', [TenantMikrotikController::class, 'updateIdentity'])->name('mikrotiks.updateIdentity');
        Route::resource('mikrotiks', TenantMikrotikController::class);
        Route::get('mikrotiks/{mikrotik}/test-connection', [TenantMikrotikController::class, 'testConnection'])->name('mikrotiks.testConnection');
        Route::get('mikrotiks/{mikrotik}/ping', [TenantMikrotikController::class, 'pingRouter'])->name('mikrotiks.ping');
        Route::get('mikrotiks/status', [TenantMikrotikController::class, 'getAllStatus'])->name('mikrotiks.statusAll');
        Route::get('mikrotiks/{mikrotik}/status', [TenantMikrotikController::class, 'getStatus'])->name('mikrotiks.status');
        Route::get('mikrotiks/{mikrotik}/resource', [TenantMikrotikController::class, 'getResource'])->name('mikrotiks.resource');
        Route::get('mikrotiks/{mikrotik}/interfaces', [TenantMikrotikController::class, 'getInterfaces'])->name('mikrotiks.interfaces');
        Route::get('mikrotiks/{mikrotik}/active-sessions', [TenantMikrotikController::class, 'getActiveSessions'])->name('mikrotiks.activeSessions');
        Route::post('mikrotiks/{mikrotik}/set-ip', [TenantMikrotikController::class, 'setIp'])->name('mikrotiks.setIp');
        Route::post('mikrotiks/validate', [TenantMikrotikController::class, 'validateRouter'])->name('mikrotiks.validate');
        Route::get('mikrotiks/{mikrotik}/download-setup-script', [TenantMikrotikController::class, 'downloadSetupScript'])->name('mikrotiks.downloadSetupScript');
        Route::get('mikrotiks/{mikrotik}/download-radius-script', [TenantMikrotikController::class, 'downloadRadiusScript'])->name('mikrotiks.downloadRadiusScript');
        Route::get('mikrotiks/{mikrotik}/download-advanced-config', [TenantMikrotikController::class, 'downloadAdvancedConfig'])->name('mikrotiks.downloadAdvancedConfig');
        Route::get('mikrotiks/{mikrotik}/download-hotspot-templates', [TenantMikrotikController::class, 'downloadHotspotTemplates'])->name('mikrotiks.downloadHotspotTemplates');
        Route::get('mikrotiks/{mikrotik}/hotspot-upload-script', [TenantMikrotikController::class, 'getHotspotUploadScript'])->name('mikrotiks.hotspotUploadScript');
        Route::get('mikrotiks/{mikrotik}/remote-management', [TenantMikrotikController::class, 'remoteManagement'])->name('mikrotiks.remoteManagement');
        Route::get('mikrotiks/{mikrotik}/ca.crt', [TenantMikrotikController::class, 'downloadCACert'])->name('mikrotiks.downloadCACert');
        Route::get('mikrotiks/{mikrotik}/reprovision', [TenantMikrotikController::class, 'reprovision'])->name('mikrotiks.reprovision');
        Route::post('mikrotiks/{mikrotik}/provision-hotspot', [TenantMikrotikController::class, 'provisionHotspot'])->name('mikrotiks.provisionHotspot');


        //captive portal
    
        // Tenant settings routes
    


    });
});






/*
|--------------------------------------------------------------------------
| Payment Success Callback | Works for system renewals
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'tenant.domain'])->group(function () {
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
});











/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'tenant.domain'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});










/*
|--------------------------------------------------------------------------
| SuperAdmin Routes
|--------------------------------------------------------------------------
*/

// Admin Login Routes
Route::middleware('guest')->group(function () {
    Route::get('admin/login', [App\Http\Controllers\Admin\AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('admin/login', [App\Http\Controllers\Admin\AuthController::class, 'login']);
});

Route::post('admin/logout', [App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('admin.logout');

Route::middleware(['auth', 'superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {


        // Dashboard
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');

        // Users Management
        Route::post('users/{user}/suspend', [UsersController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/unsuspend', [UsersController::class, 'unsuspend'])->name('users.unsuspend');
        Route::resource('users', UsersController::class)->only(['index', 'show', 'destroy']);

        // Payments Management
        Route::resource('payments', PaymentsController::class)->only(['index', 'show', 'destroy']);

        // all mikrotiks in the system
        Route::resource('allmikrotiks', AllMikrotiksController::class)->only(['index', 'show', 'destroy']);

        // Payment Gateways Management
        Route::get('payment-gateways', [App\Http\Controllers\SuperAdmin\PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::post('payment-gateways/toggle', [App\Http\Controllers\SuperAdmin\PaymentGatewayController::class, 'toggle'])->name('payment-gateways.toggle');
    });

/*
|--------------------------------------------------------------------------
| Customer Portal Routes
|--------------------------------------------------------------------------
*/
Route::prefix('customer')->name('customer.')->group(function () {
    // Guest routes
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [App\Http\Controllers\Customer\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [App\Http\Controllers\Customer\AuthController::class, 'login']);
    });
    
    // Authenticated customer routes
    Route::middleware(['auth:customer', 'customer'])->group(function () {
        Route::get('dashboard', [App\Http\Controllers\Customer\DashboardController::class, 'index'])->name('dashboard');
        Route::get('renew', [App\Http\Controllers\Customer\RenewalController::class, 'index'])->name('renew');
        Route::post('renew/pay', [App\Http\Controllers\Customer\RenewalController::class, 'initiatePayment'])->name('renew.pay');
        Route::get('renew/status/{referenceId}', [App\Http\Controllers\Customer\RenewalController::class, 'checkPaymentStatus'])->name('renew.status');
        
        Route::get('upgrade', [App\Http\Controllers\Customer\UpgradeController::class, 'index'])->name('upgrade');
        Route::post('upgrade/pay', [App\Http\Controllers\Customer\UpgradeController::class, 'initiatePayment'])->name('upgrade.pay');
        Route::get('upgrade/status/{referenceId}', [App\Http\Controllers\Customer\UpgradeController::class, 'checkPaymentStatus'])->name('upgrade.status');

        // Paystack verification (for customer portal)
        Route::get('paystack/verify/{reference}', [App\Http\Controllers\Tenants\PaystackController::class, 'verify'])->name('paystack.verify');
        
        // Flutterwave verification (for customer portal)
        Route::get('flutterwave/verify/{reference}', [App\Http\Controllers\Tenants\FlutterwaveController::class, 'verify'])->name('flutterwave.verify');

        Route::post('logout', [App\Http\Controllers\Customer\AuthController::class, 'logout'])->name('logout');
    });
});

require __DIR__ . '/auth.php';
