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
use App\Http\Controllers\Tenants\TenantSystemUserController;
use App\Http\Controllers\Tenants\ContentFilterController;

// SuperAdmin controllers
use App\Http\Controllers\SuperAdmin\SuperAdminController;
use App\Http\Controllers\SuperAdmin\UsersController;
use App\Http\Controllers\SuperAdmin\PaymentsController;
use App\Http\Controllers\SuperAdmin\AllMikrotiksController;
use App\Http\Controllers\SuperAdmin\HealthCheckController;
use App\Http\Controllers\OnboardingRequestController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', [\App\Http\Controllers\WelcomeController::class, 'index']);

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

// Maintenance Route
Route::get('/maintenance', function () {
    // Fetch support settings directly or via config if available
    // Since we injected them into config in AppServiceProvider, we can use config()
    return Inertia::render('Maintenance', [
        'support_email' => config('mail.from.address'), // Using the mapped config
        'support_phone' => \App\Models\SystemSetting::where('key', 'support_phone')->value('value'),
    ]);
})->name('maintenance');

// Onboarding Requests
Route::post('/onboarding-requests', [OnboardingRequestController::class, 'store'])
    ->middleware('throttle:registration')
    ->name('onboarding-requests.store');

// Hotspot routes (protected by subscription check) replace subscription with a safer middleware for hotspot safe redirects

Route::middleware(['check.subscription', 'maintenance.mode'])->group(function () {

    Route::middleware('throttle:portal_login')->group(function () {
        Route::get('/hotspot/success', function () {
            return view('hotspot.success');
        })->name('hotspot.success');

        Route::get('/hotspot/suspended', [TenantHotspotController::class, 'suspended'])->name('hotspot.suspended');
        
        Route::resource('hotspot', TenantHotspotController::class)->except(['show']);
        
        Route::post('/hotspot/purchase-stk-push', [TenantHotspotController::class, 'purchaseSTKPush'])
            ->middleware('throttle:stk_push')
            ->name('hotspot.purchase-stk-push');
            
        Route::post('/hotspot/checkout', [TenantHotspotController::class, 'checkout'])->name('hotspot.checkout');
        Route::post('/hotspot/callback', [TenantHotspotController::class, 'callback'])->name('hotspot.callback');
        Route::get('/hotspot/payment-status/{identifier}', [TenantHotspotController::class, 'checkPaymentStatus'])->name('hotspot.check-status');
        
        Route::post('/hotspot/voucher-auth', [VoucherController::class, 'authenticate'])
            ->middleware('throttle:voucher_auth')
            ->name('voucher.authenticate');

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

Route::middleware(['auth', 'verified', 'tenant.domain', 'maintenance.mode'])
    ->group(function () {
        // Subscription & Renewal (Accessible even if expired)
        Route::get('/subscription/renew', [SubscriptionController::class, 'showRenewal'])->name('subscription.renew');
        Route::post('/subscription/initialize-payment', [SubscriptionController::class, 'initializePayment'])
            ->middleware('throttle:stk_push')
            ->name('subscription.initialize-payment');
        Route::get('/subscription/callback', [SubscriptionController::class, 'handleCallback'])->name('subscription.callback');

        Route::middleware(['check.subscription'])->group(function () {


        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/data', [DashboardController::class, 'data'])
            ->name('dashboard.data');

        //Active Users
        Route::middleware(['role:tenant_admin|admin|customer_care|technical'])->group(function () {
            Route::resource('activeusers', TenantActiveUsersController::class)->middleware('throttle:online_users');
        });

        Route::middleware('throttle:bulk_actions')->group(function () {
            Route::middleware(['role:tenant_admin|admin'])->group(function () {
                Route::delete('/packages/bulk-delete', [PackageController::class, 'bulkDelete'])->name('packages.bulk-delete');
                Route::delete('/vouchers/bulk-delete', [VoucherController::class, 'bulkDelete'])->name('vouchers.bulk-delete');
                Route::delete('/payments/bulk-delete', [TenantPaymentController::class, 'bulkDelete'])->name('payments.bulk-delete');
                Route::delete('/invoices/bulk-delete', [TenantInvoiceController::class, 'bulkDelete'])->name('invoices.bulk-delete');
            });
            
            Route::middleware(['role:tenant_admin|admin|marketing'])->group(function () {
                Route::delete('leads/bulk-delete', [TenantLeadController::class, 'bulkDelete'])->name('leads.bulk-delete');
            });
            
            Route::middleware(['role:tenant_admin|admin|customer_care'])->group(function () {
                Route::delete('tickets/bulk-delete', [TenantTicketController::class, 'bulkDelete'])->name('tickets.bulk-delete');
            });

            Route::middleware(['role:tenant_admin|admin|network_engineer|technical'])->group(function () {
                Route::delete('/equipment/bulk-delete', [TenantEquipmentController::class, 'bulkDelete'])->name('equipment.bulk-delete');
            });
        });

        //tenants packages
        Route::middleware(['role:tenant_admin|admin|marketing'])->group(function () {
            Route::resource('packages', PackageController::class)->except(['show']);
        });

        //network users( wifi users )
        Route::middleware(['role:tenant_admin|admin|customer_care|technical'])->group(function () {
            Route::resource('users', TenantUserController::class)->middleware('throttle:user_crud');
            Route::delete('/users/bulk-delete', [TenantUserController::class, 'bulkDelete'])
                ->middleware('throttle:bulk_actions')
                ->name('users.bulk-delete');
            Route::post('/users/import', [TenantUserController::class, 'import'])
                ->middleware('throttle:bulk_actions')
                ->name('users.import');
            Route::post('users/details', [TenantUserController::class, 'update'])->name('users.details.update');
        });


        //Leads
        Route::middleware(['role:tenant_admin|admin|marketing'])->group(function () {
            Route::resource('leads', TenantLeadController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::delete('leads/bulk-delete', [TenantLeadController::class, 'bulkDelete'])->name('leads.bulk-delete');
        });

        //tickets
        Route::middleware(['role:tenant_admin|admin|customer_care|technical'])->group(function () {
            Route::resource('tickets', TenantTicketController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::delete('tickets/bulk-delete', [TenantTicketController::class, 'bulkDelete'])->name('tickets.bulk-delete');
            Route::put('/tickets/{ticket}/resolve', [TenantTicketController::class, 'resolve'])->name('tickets.resolve');
        });

        //Equipment
        Route::middleware(['role:tenant_admin|admin|network_engineer|technical'])->group(function () {
            Route::resource('equipment', TenantEquipmentController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::delete('/equipment/bulk-delete', [TenantEquipmentController::class, 'bulkDelete'])->name('equipment.bulk-delete');
        });

        //vouchers
        Route::middleware(['role:tenant_admin|admin|marketing|customer_care'])->group(function () {
            Route::get('/vouchers/print', [VoucherController::class, 'print'])->name('vouchers.print');
            Route::delete('/vouchers/bulk-delete', [VoucherController::class, 'bulkDelete'])->name('vouchers.bulk-delete');
            Route::resource('vouchers', VoucherController::class);
            Route::post('/vouchers/{voucher}/send', [VoucherController::class, 'send'])->name('vouchers.send');
        });

        //Payments
        Route::middleware(['role:tenant_admin|admin'])->group(function () {
            Route::resource('payments', TenantPaymentController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::delete('/payments/bulk-delete', [TenantPaymentController::class, 'bulkDelete'])->name('payments.bulk-delete');
        });

        //Invoices
        Route::middleware(['role:tenant_admin|admin|customer_care'])->group(function () {
            Route::resource('invoices', TenantInvoiceController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::delete('/invoices/bulk-delete', [TenantInvoiceController::class, 'bulkDelete'])->name('invoices.bulk-delete');
        });

        //Expenses
        Route::middleware(['role:tenant_admin|admin'])->group(function () {
            Route::resource('expenses', TenantExpensesController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::delete('/expenses/bulk-delete', [TenantExpensesController::class, 'bulkDelete'])->name('expenses.bulk-delete');
        });

        //Communication
        Route::middleware(['role:tenant_admin|admin|marketing|customer_care'])->group(function () {
            //SMS
            Route::resource('sms', TenantSMSController::class)
                ->only(['index', 'create', 'store', 'destroy'])
                ->middleware('throttle:sms_sending');
            Route::delete('/sms/bulk-delete', [TenantSMSController::class, 'bulkDelete'])
                ->middleware('throttle:bulk_actions')
                ->name('sms.bulk-delete');

            // SMS Templates
            Route::resource('smstemplates', TenantSMSTemplateController::class)->only(['index', 'create', 'update', 'store', 'destroy']);
            Route::delete('/smstemplates/bulk-delete', [TenantSMSTemplateController::class, 'bulkDelete'])->name('smstemplates.bulk-delete');
        });

        //Hotspot Settings
        Route::get('settings/hotspot', [TenantHotspotSettingsController::class, 'edit'])->name('settings.hotspot.edit');
        Route::post('settings/hotspot', [TenantHotspotSettingsController::class, 'update'])
            ->middleware('throttle:file_upload')
            ->name('settings.hotspot.update');

        //payment gateways settings
        Route::get('settings/payment', [TenantPaymentGatewayController::class, 'edit'])->name('settings.payment.edit');
        Route::post('settings/payment', [TenantPaymentGatewayController::class, 'update'])->name('settings.payment.update');

        //sms gateway settings
        Route::get('settings/sms', [TenantSmsGatewayController::class, 'edit'])->name('settings.sms.edit');
        Route::post('settings/sms', [TenantSmsGatewayController::class, 'update'])->name('settings.sms.update');
        Route::get('/settings/sms/show', [TenantSmsGatewayController::class, 'show'])->name('settings.sms.show');
        Route::get('/settings/sms/json', [TenantSmsGatewayController::class, 'getGateway'])->name('settings.sms.json');



        // Analytics routes
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::middleware(['role:tenant_admin|admin|network_engineer|technical'])->group(function () {
                Route::get('/traffic', [App\Http\Controllers\Tenants\TrafficAnalyticsController::class, 'index'])->name('traffic');
                Route::get('/traffic/user/{userId}', [App\Http\Controllers\Tenants\TrafficAnalyticsController::class, 'getUserBandwidth'])->name('traffic.user');
            });
            
            Route::middleware(['role:tenant_admin|network_engineer|technical'])->group(function () {
                Route::get('/topology', [App\Http\Controllers\Tenants\NetworkTopologyController::class, 'index'])->name('topology');
                Route::get('/topology/updates', [App\Http\Controllers\Tenants\NetworkTopologyController::class, 'getTopologyUpdates'])->name('topology.updates');
                Route::get('/topology/device/{id}', [App\Http\Controllers\Tenants\NetworkTopologyController::class, 'getDeviceDetails'])->name('topology.device');
            });

            Route::middleware(['role:tenant_admin|admin|network_engineer'])->group(function () {
                Route::get('/predictions', [App\Http\Controllers\Tenants\PredictiveAnalyticsController::class, 'index'])->name('predictions');
                Route::post('/predictions/refresh', [App\Http\Controllers\Tenants\PredictiveAnalyticsController::class, 'refresh'])->name('predictions.refresh');
            });

            Route::middleware(['role:tenant_admin|admin|marketing'])->group(function () {
                // Report Builder
                Route::resource('reports', App\Http\Controllers\Tenants\ReportBuilderController::class)->only(['index', 'store', 'destroy']);
                Route::post('/reports/{report}/generate', [App\Http\Controllers\Tenants\ReportBuilderController::class, 'generate'])->name('reports.generate');
                
                // Financial Intelligence
                Route::get('/finance', [App\Http\Controllers\Tenants\FinancialAnalyticsController::class, 'index'])->name('finance');
            });
        });

        // Staff Management (Tenant Admin only)
        Route::middleware(['role:tenant_admin|admin'])->group(function () {
            Route::prefix('settings/staff')->name('settings.staff.')->group(function () {
                Route::get('/', [TenantSystemUserController::class, 'index'])->name('index');
                Route::post('/', [TenantSystemUserController::class, 'store'])->name('store');
                Route::put('/{user}', [TenantSystemUserController::class, 'update'])->name('update');
                Route::delete('/{user}', [TenantSystemUserController::class, 'destroy'])->name('destroy');
                Route::post('/{user}/toggle-status', [TenantSystemUserController::class, 'toggleStatus'])->name('toggle-status');
            });
            
            Route::get('settings/general', [TenantGeneralSettingsController::class, 'edit'])->name('settings.general.edit');
            Route::post('settings/general', [TenantGeneralSettingsController::class, 'update'])
                ->middleware('throttle:file_upload')
                ->name('settings.general.update');
        });

        // Network Management
        Route::middleware(['role:tenant_admin|network_engineer|network_admin|technical'])->group(function () {
            Route::middleware('throttle:mikrotik_api')->group(function () {
                Route::post('mikrotiks/{mikrotik}/reboot', [TenantMikrotikController::class, 'reboot'])->name('mikrotiks.reboot');
                Route::delete('mikrotiks/{mikrotik}/force-delete', [TenantMikrotikController::class, 'forceDelete'])->name('mikrotiks.forceDelete');
                Route::resource('mikrotiks', TenantMikrotikController::class);
                // ... other mikrotik routes ...
            });
            
            Route::get('settings/content-filter', [ContentFilterController::class, 'index'])->name('settings.content-filter.index');
            Route::post('settings/content-filter', [ContentFilterController::class, 'update'])->name('settings.content-filter.update');
            Route::post('settings/content-filter/apply/{router}', [ContentFilterController::class, 'applyToRouter'])->name('settings.content-filter.apply');
        });

        // Domain Requests
        Route::get('domain-requests', [App\Http\Controllers\Tenants\DomainRequestController::class, 'index'])->name('domain-requests.index');
        Route::post('domain-requests', [App\Http\Controllers\Tenants\DomainRequestController::class, 'store'])->name('domain-requests.store');

        //Mikrotik Details
        Route::resource('mikrotikdetails', MikrotikDetailsController::class)->only(['index']);


        //mikrotiks
        Route::middleware('throttle:mikrotik_api')->group(function () {
            Route::post('mikrotiks/{mikrotik}/reboot', [TenantMikrotikController::class, 'reboot'])->name('mikrotiks.reboot');
            Route::post('mikrotiks/{mikrotik}/identity', [TenantMikrotikController::class, 'updateIdentity'])->name('mikrotiks.updateIdentity');
            Route::post('mikrotiks/{mikrotik}/restore', [TenantMikrotikController::class, 'restore'])->name('mikrotiks.restore');
            Route::delete('mikrotiks/{mikrotik}/force-delete', [TenantMikrotikController::class, 'forceDelete'])->name('mikrotiks.forceDelete');
            Route::resource('mikrotiks', TenantMikrotikController::class);
            Route::get('mikrotiks/{mikrotik}/test-connection', [TenantMikrotikController::class, 'testConnection'])->name('mikrotiks.testConnection');
            Route::get('mikrotiks/{mikrotik}/ping', [TenantMikrotikController::class, 'pingRouter'])->name('mikrotiks.ping');
            Route::get('mikrotiks/status', [TenantMikrotikController::class, 'getAllStatus'])->name('mikrotiks.statusAll');
            Route::get('mikrotiks/{mikrotik}/status', [TenantMikrotikController::class, 'getStatus'])->name('mikrotiks.status');
            Route::get('mikrotiks/{mikrotik}/resource', [TenantMikrotikController::class, 'getResource'])->name('mikrotiks.resource');
            Route::get('mikrotiks/{mikrotik}/interfaces', [TenantMikrotikController::class, 'getInterfaces'])->name('mikrotiks.interfaces');
            Route::get('mikrotiks/{mikrotik}/active-sessions', [TenantMikrotikController::class, 'getActiveSessions'])->name('mikrotiks.activeSessions');
            Route::post('mikrotiks/{mikrotik}/set-ip', [TenantMikrotikController::class, 'setIp'])->name('mikrotiks.setIp');
            Route::get('mikrotiks/{mikrotik}/download-setup-script', [TenantMikrotikController::class, 'downloadSetupScript'])->name('mikrotiks.downloadSetupScript');
            Route::get('mikrotiks/{mikrotik}/download-radius-script', [TenantMikrotikController::class, 'downloadRadiusScript'])->name('mikrotiks.downloadRadiusScript');
            Route::get('mikrotiks/{mikrotik}/download-advanced-config', [TenantMikrotikController::class, 'downloadAdvancedConfig'])->name('mikrotiks.downloadAdvancedConfig');
            Route::get('mikrotiks/{mikrotik}/download-hotspot-templates', [TenantMikrotikController::class, 'downloadHotspotTemplates'])->name('mikrotiks.downloadHotspotTemplates');
            Route::get('mikrotiks/{mikrotik}/hotspot-upload-script', [TenantMikrotikController::class, 'getHotspotUploadScript'])->name('mikrotiks.hotspotUploadScript');
            Route::get('mikrotiks/{mikrotik}/remote-management', [TenantMikrotikController::class, 'remoteManagement'])->name('mikrotiks.remoteManagement');
            Route::get('mikrotiks/{mikrotik}/ca.crt', [TenantMikrotikController::class, 'downloadCACert'])->name('mikrotiks.downloadCACert');
        });

        Route::get('mikrotiks/{mikrotik}/reprovision', [TenantMikrotikController::class, 'reprovision'])
            ->middleware('throttle:wireguard_sync')
            ->name('mikrotiks.reprovision');
        
        Route::post('mikrotiks/{mikrotik}/provision-hotspot', [TenantMikrotikController::class, 'provisionHotspot'])->name('mikrotiks.provisionHotspot');

        // Analytics routes
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/traffic', [App\Http\Controllers\Tenants\TrafficAnalyticsController::class, 'index'])->name('traffic');
            Route::get('/traffic/user/{userId}', [App\Http\Controllers\Tenants\TrafficAnalyticsController::class, 'getUserBandwidth'])->name('traffic.user');
            
            Route::get('/topology', [App\Http\Controllers\Tenants\NetworkTopologyController::class, 'index'])->name('topology');
            Route::get('/topology/updates', [App\Http\Controllers\Tenants\NetworkTopologyController::class, 'getTopologyUpdates'])->name('topology.updates');
            Route::get('/topology/device/{id}', [App\Http\Controllers\Tenants\NetworkTopologyController::class, 'getDeviceDetails'])->name('topology.device');

            Route::get('/predictions', [App\Http\Controllers\Tenants\PredictiveAnalyticsController::class, 'index'])->name('predictions');
            Route::post('/predictions/refresh', [App\Http\Controllers\Tenants\PredictiveAnalyticsController::class, 'refresh'])->name('predictions.refresh');

            // Report Builder
            Route::resource('reports', App\Http\Controllers\Tenants\ReportBuilderController::class)->only(['index', 'store', 'destroy']);
            Route::post('/reports/{report}/generate', [App\Http\Controllers\Tenants\ReportBuilderController::class, 'generate'])->name('reports.generate');
            
            // Financial Intelligence
            Route::get('/finance', [App\Http\Controllers\Tenants\FinancialAnalyticsController::class, 'index'])->name('finance');
        });

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
    Route::post('admin/login', [App\Http\Controllers\Admin\AuthController::class, 'login'])
        ->middleware('throttle:tenant_login');
});

Route::post('admin/logout', [App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('admin.logout');
Route::post('impersonate/leave', [App\Http\Controllers\ImpersonationController::class, 'leave'])->name('impersonate.leave');

Route::middleware(['auth', 'superadmin', 'throttle:120,1'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
        
        // User Management
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UsersController::class, 'index'])->name('index');
            Route::get('/export', [UsersController::class, 'export'])
                ->middleware('throttle:bulk_actions')
                ->name('export');
            Route::post('/bulk-action', [UsersController::class, 'bulkAction'])
                ->middleware('throttle:bulk_actions')
                ->name('bulk-action');
            Route::get('/{user}', [UsersController::class, 'show'])->name('show');
            Route::put('/{user}', [UsersController::class, 'update'])->name('update');
            Route::delete('/{user}', [UsersController::class, 'destroy'])->name('destroy');
            Route::post('/{user}/suspend', [UsersController::class, 'suspend'])->name('suspend');
            Route::post('/{user}/unsuspend', [UsersController::class, 'unsuspend'])->name('unsuspend');
            Route::post('/{user}/impersonate', [UsersController::class, 'impersonate'])->name('impersonate');
        });

        // Payment Management
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentsController::class, 'index'])->name('index');
            Route::get('/export', [PaymentsController::class, 'export'])
                ->middleware('throttle:bulk_actions')
                ->name('export');
            Route::get('/{payment}', [PaymentsController::class, 'show'])->name('show');
            Route::delete('/{payment}', [PaymentsController::class, 'destroy'])->name('destroy');
            Route::post('/{payment}/disburse', [PaymentsController::class, 'disburse'])->name('disburse');
        });

        // MikroTik Management
        Route::resource('allmikrotiks', AllMikrotiksController::class)->only(['index', 'show', 'destroy']);

        // Settings & Configuration
        Route::prefix('settings')->name('settings.')->group(function () {
            // Payment Gateways
            Route::get('payment-gateways', [App\Http\Controllers\SuperAdmin\PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
            Route::post('payment-gateways/toggle', [App\Http\Controllers\SuperAdmin\PaymentGatewayController::class, 'toggle'])->name('payment-gateways.toggle');
            
            // SMS Gateways
            Route::get('sms-gateways', [App\Http\Controllers\SuperAdmin\SmsGatewayController::class, 'index'])->name('sms-gateways.index');
            Route::post('sms-gateways/toggle', [App\Http\Controllers\SuperAdmin\SmsGatewayController::class, 'toggle'])->name('sms-gateways.toggle');
            
            // System Settings
            Route::get('system', [App\Http\Controllers\SuperAdmin\SystemSettingsController::class, 'index'])->name('system.index');
            Route::post('system', [App\Http\Controllers\SuperAdmin\SystemSettingsController::class, 'update'])->name('system.update');
            
            // Pricing Plans
            Route::get('pricing-plans', [App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'index'])->name('pricing-plans.index');
            Route::post('pricing-plans', [App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'store'])->name('pricing-plans.store');
            Route::delete('pricing-plans/{id}', [App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'destroy'])->name('pricing-plans.destroy');
        });

        // Requests & Approvals
        Route::prefix('requests')->name('requests.')->group(function () {
            // Onboarding Requests
            Route::get('onboarding', [OnboardingRequestController::class, 'index'])->name('onboarding.index');
            Route::patch('onboarding/{onboardingRequest}', [OnboardingRequestController::class, 'update'])->name('onboarding.update');
            
            // Domain Requests
            Route::get('domains', [App\Http\Controllers\SuperAdmin\DomainRequestController::class, 'index'])->name('domains.index');
            Route::patch('domains/{domainRequest}', [App\Http\Controllers\SuperAdmin\DomainRequestController::class, 'update'])->name('domains.update');
            Route::delete('domains/{domainRequest}', [App\Http\Controllers\SuperAdmin\DomainRequestController::class, 'destroy'])->name('domains.destroy');
        });

        // System Management
        Route::prefix('system')->name('system.')->group(function () {
            // Activity Log
            Route::get('activity-log', [App\Http\Controllers\SuperAdmin\ActivityLogController::class, 'index'])->name('activity-log');
            
            // Admin Management
            Route::get('admins', [App\Http\Controllers\SuperAdmin\AdminController::class, 'index'])->name('admins.index');
            Route::post('admins', [App\Http\Controllers\SuperAdmin\AdminController::class, 'store'])->name('admins.store');
            Route::put('admins/{id}', [App\Http\Controllers\SuperAdmin\AdminController::class, 'update'])->name('admins.update');
            Route::delete('admins/{id}', [App\Http\Controllers\SuperAdmin\AdminController::class, 'destroy'])->name('admins.destroy');

            // Health Check
            Route::get('health', [HealthCheckController::class, 'index'])->name('health');
        });
        
        // Legacy route compatibility (to be deprecated)
        Route::get('payment-gateways', [App\Http\Controllers\SuperAdmin\PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::post('payment-gateways/toggle', [App\Http\Controllers\SuperAdmin\PaymentGatewayController::class, 'toggle'])->name('payment-gateways.toggle');
        Route::get('domain-requests', [App\Http\Controllers\SuperAdmin\DomainRequestController::class, 'index'])->name('domain-requests.index');
        Route::patch('domain-requests/{domainRequest}', [App\Http\Controllers\SuperAdmin\DomainRequestController::class, 'update'])->name('domain-requests.update');
        Route::delete('domain-requests/{domainRequest}', [App\Http\Controllers\SuperAdmin\DomainRequestController::class, 'destroy'])->name('domain-requests.destroy');
        Route::get('pricing-plans', [App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'index'])->name('pricing-plans.index');
        Route::post('pricing-plans', [App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'store'])->name('pricing-plans.store');
        Route::delete('pricing-plans/{id}', [App\Http\Controllers\SuperAdmin\PricingPlanController::class, 'destroy'])->name('pricing-plans.destroy');
        Route::get('system-settings', [App\Http\Controllers\SuperAdmin\SystemSettingsController::class, 'index'])->name('system-settings.index');
        Route::post('system-settings', [App\Http\Controllers\SuperAdmin\SystemSettingsController::class, 'update'])->name('system-settings.update');
        Route::get('sms-gateways', [App\Http\Controllers\SuperAdmin\SmsGatewayController::class, 'index'])->name('sms-gateways.index');
        Route::post('sms-gateways/toggle', [App\Http\Controllers\SuperAdmin\SmsGatewayController::class, 'toggle'])->name('sms-gateways.toggle');
        Route::get('admins', [App\Http\Controllers\SuperAdmin\AdminController::class, 'index'])->name('admins.index');
        Route::post('admins', [App\Http\Controllers\SuperAdmin\AdminController::class, 'store'])->name('admins.store');
        Route::put('admins/{id}', [App\Http\Controllers\SuperAdmin\AdminController::class, 'update'])->name('admins.update');
        Route::delete('admins/{id}', [App\Http\Controllers\SuperAdmin\AdminController::class, 'destroy'])->name('admins.destroy');
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
        Route::post('login', [App\Http\Controllers\Customer\AuthController::class, 'login'])
            ->middleware('throttle:tenant_login');
    });
    
    // Authenticated customer routes
    Route::middleware(['auth:customer', 'customer', 'maintenance.mode'])->group(function () {
        Route::get('dashboard', [App\Http\Controllers\Customer\DashboardController::class, 'index'])->name('dashboard');
        Route::get('renew', [App\Http\Controllers\Customer\RenewalController::class, 'index'])->name('renew');
        Route::post('renew/pay', [App\Http\Controllers\Customer\RenewalController::class, 'initiatePayment'])->name('renew.pay');
        Route::get('renew/status/{referenceId}', [App\Http\Controllers\Customer\RenewalController::class, 'checkPaymentStatus'])->name('renew.status');
        
        Route::get('upgrade', [App\Http\Controllers\Customer\UpgradeController::class, 'index'])->name('upgrade');
        Route::post('upgrade/pay', [App\Http\Controllers\Customer\UpgradeController::class, 'initiatePayment'])->name('upgrade.pay');
        Route::get('upgrade/status/{referenceId}', [App\Http\Controllers\Customer\UpgradeController::class, 'checkPaymentStatus'])->name('upgrade.status');

        Route::get('history', [App\Http\Controllers\Customer\SessionController::class, 'index'])->name('history');
        
        Route::prefix('tickets')->name('tickets.')->group(function () {
            Route::get('/', [App\Http\Controllers\Customer\TicketController::class, 'index'])->name('index');
            Route::post('/', [App\Http\Controllers\Customer\TicketController::class, 'store'])->name('store');
            Route::get('/{ticket}', [App\Http\Controllers\Customer\TicketController::class, 'show'])->name('show');
            Route::post('/{ticket}/reply', [App\Http\Controllers\Customer\TicketController::class, 'reply'])->name('reply');
        });

        // Paystack verification (for customer portal)
        Route::get('paystack/verify/{reference}', [App\Http\Controllers\Tenants\PaystackController::class, 'verify'])->name('paystack.verify');
        
        // Flutterwave verification (for customer portal)
        Route::get('flutterwave/verify/{reference}', [App\Http\Controllers\Tenants\FlutterwaveController::class, 'verify'])->name('flutterwave.verify');

        Route::post('logout', [App\Http\Controllers\Customer\AuthController::class, 'logout'])->name('logout');
    });
});

require __DIR__ . '/auth.php';
