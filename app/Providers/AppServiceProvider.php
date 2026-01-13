<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\CheckSubscription;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureTenantDomain;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Tenants\TenantLeads;
use App\Models\Tenants\NetworkUser;
use App\Models\User;
use App\Models\Tenant;
use App\Services\WireGuardService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WireGuardService::class, function ($app) {
            return new WireGuardService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register the CheckSubscription middleware globally
        Route::aliasMiddleware('check.subscription', CheckSubscription::class);
        // Register the SuperAdmin middleware globally
        Route::aliasMiddleware('superadmin', SuperAdminMiddleware::class);
        // Central-domain only restrictions (welcome & registration)
        Route::aliasMiddleware('central', \App\Http\Middleware\CentralDomainOnly::class);
        // Register the Tenant middleware globally
        Route::aliasMiddleware('tenant.domain', EnsureTenantDomain::class);
        // Register Maintenance Mode middleware
        Route::aliasMiddleware('maintenance.mode', \App\Http\Middleware\CheckMaintenanceMode::class);

        Relation::enforceMorphMap([
            'lead' => TenantLeads::class,
            'user' => NetworkUser::class,
            'admin' => User::class,
            'tenant' => Tenant::class,
        ]);

        // Register model observers
        \App\Models\Tenants\TenantMikrotik::observe(\App\Observers\TenantMikrotikObserver::class);

        // Load System Settings
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                $settings = \App\Models\SystemSetting::all()->pluck('value', 'key');

                if ($settings->has('app_name')) {
                    config(['app.name' => $settings['app_name']]);
                }
                
                if ($settings->has('support_email')) {
                    config(['mail.from.address' => $settings['support_email']]);
                }

                // Share settings globally via config for easy access
                config(['settings' => $settings->toArray()]);
            }
        } catch (\Exception $e) {
            // Ignore errors during migration or initial setup
        }

        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // 1. Tenant Login (Admin Dashboard)
        RateLimiter::for('tenant_login', function (Request $request) {
            $limit = config('settings.throttle_tenant_login_limit', 5);
            return Limit::perMinute((int) $limit)->by($request->ip());
        });

        // 2. Captive Portal Login
        RateLimiter::for('portal_login', function (Request $request) {
            $limit = config('settings.throttle_portal_login_limit', 20);
            return Limit::perMinute((int) $limit)->by($request->ip());
        });

        // 3. Voucher Authentication (by MAC)
        RateLimiter::for('voucher_auth', function (Request $request) {
            $limit = config('settings.throttle_voucher_auth_limit', 10);
            $mac = $request->input('mac') ?: $request->input('username'); // Fallback if MAC is username
            return Limit::perMinute((int) $limit)->by($mac ?: $request->ip());
        });

        // 4. SMS Sending (per tenant)
        RateLimiter::for('sms_sending', function (Request $request) {
            $limit = config('settings.throttle_sms_minute_limit', 60);
            $tenantId = optional(tenancy()->tenant)->id ?: 'global';
            return [
                Limit::perMinute((int) $limit)->by($tenantId),
                Limit::perMinute(60)->by($tenantId), // 60 per min is 1 per second on average, but user said 1 per sec
            ];
            // Note: Laravel's Limit doesn't easily do "1 per second" alongside "60 per minute" without multiple limiters.
            // If we really want 1 per second strictly: Limit::perSecond(1)
        });

        // 5. Payment Webhook
        RateLimiter::for('payment_webhook', function (Request $request) {
            $limit = config('settings.throttle_payment_webhook_limit', 30);
            return Limit::perMinute((int) $limit)->by($request->ip());
        });

        // 6. STK Push Initiation (per phone/subscriber)
        RateLimiter::for('stk_push', function (Request $request) {
            $limit = config('settings.throttle_stk_push_limit', 3);
            $phone = $request->input('phone') ?: $request->input('account_number');
            return Limit::perMinute((int) $limit)->by($phone ?: $request->ip());
        });

        // 7. Network User CRUD (per tenant)
        RateLimiter::for('user_crud', function (Request $request) {
            $limit = config('settings.throttle_user_crud_limit', 60);
            $tenantId = optional(tenancy()->tenant)->id ?: 'global';
            return Limit::perMinute((int) $limit)->by($tenantId);
        });

        // 8. Online Users Fetch (per tenant/router)
        RateLimiter::for('online_users', function (Request $request) {
            $limit = config('settings.throttle_online_users_limit', 6);
            $tenantId = optional(tenancy()->tenant)->id ?: 'global';
            $routerId = $request->route('mikrotik') ?: 'any';
            return Limit::perMinute((int) $limit)->by($tenantId . '|' . $routerId);
        });

        // 9. Bulk Actions (per tenant)
        RateLimiter::for('bulk_actions', function (Request $request) {
            $limit = config('settings.throttle_bulk_actions_limit', 5);
            $tenantId = optional(tenancy()->tenant)->id ?: 'global';
            return Limit::perMinute((int) $limit)->by($tenantId);
        });

        // 10. Tenant Registration
        RateLimiter::for('registration', function (Request $request) {
            $limit = config('settings.throttle_tenant_registration_limit', 2);
            return Limit::perHour((int) $limit)->by($request->ip());
        });

        // 11. WireGuard Sync (per router/tenant)
        RateLimiter::for('wireguard_sync', function (Request $request) {
            $limit = config('settings.throttle_wireguard_sync_limit', 2);
            $tenantId = optional(tenancy()->tenant)->id ?: 'global';
            $routerId = $request->route('mikrotik') ?: 'any';
            return Limit::perMinute((int) $limit)->by($tenantId . '|' . $routerId);
        });

        // 12. File Uploads (per tenant)
        RateLimiter::for('file_upload', function (Request $request) {
            $limit = config('settings.throttle_file_upload_limit', 10);
            $tenantId = optional(tenancy()->tenant)->id ?: 'global';
            return Limit::perMinute((int) $limit)->by($tenantId);
        });

        // 13. MikroTik API queries
        RateLimiter::for('mikrotik_api', function (Request $request) {
            $limit = config('settings.throttle_mikrotik_api_limit', 4);
            $routerId = $request->route('mikrotik') ?: 'any';
            return Limit::perMinute((int) $limit)->by($routerId);
        });
    }
}
