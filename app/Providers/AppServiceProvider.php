<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\CheckSubscription;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Tenants\TenantLeads;
use App\Models\Tenants\NetworkUser;
use App\Services\WireGuardService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WireGuardService::class, function ($app) {
            return new WireGuardService(config('wireguard.wg_interface', 'wg0'));
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
       // Register the Tenant middleware globally

       Relation::enforceMorphMap([
        'lead' => TenantLeads::class,
        'user' => NetworkUser::class,
    ]);
        
    }
}
