<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * TenantAuth middleware ensures that tenant subdomain routes
 * are properly protected and only accessible to authenticated users
 * 
 * Behavior:
 * - Unauthenticated users → redirect to tenant login
 * - Authenticated users → ensure they belong to current tenant
 * - Super admins → allowed access for management
 */
class TenantAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->to('/login');
        }

        $user = $request->user();

        // Super admins can access any tenant for management purposes
        if ($user->is_super_admin ?? false) {
            return $next($request);
        }

        // Ensure tenant context is properly initialized
        if (!function_exists('tenant') || !tenant()) {
            \Log::error('Tenant context not available in TenantAuth middleware', [
                'user_id' => $user->id,
                'host' => $request->getHost(),
                'path' => $request->path(),
            ]);
            
            return redirect()->to('/login');
        }

        $currentTenant = tenant();

        // Verify that the authenticated user belongs to the current tenant
        if ($user->tenant_id && $currentTenant) {
            if ($user->tenant_id !== $currentTenant->id) {
                \Log::warning('User accessing tenant they don\'t belong to', [
                    'user_id' => $user->id,
                    'user_tenant_id' => $user->tenant_id,
                    'current_tenant_id' => $currentTenant->id,
                    'current_domain' => $request->getHost(),
                    'requested_path' => $request->path(),
                ]);

                // Logout and redirect to correct tenant domain
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Get user's correct tenant domain
                if ($user->tenant) {
                    $tenantDomain = optional($user->tenant->domains()->first())->domain;
                    if ($tenantDomain) {
                        return redirect()->away("https://{$tenantDomain}/login");
                    }
                }

                // Fallback to central registration
                return redirect()->away(rtrim(config('app.url'), '/') . '/register');
            }
        } else {
            // User doesn't have tenant_id or tenant context is missing
            \Log::warning('Non-tenant user attempting to access tenant route', [
                'user_id' => $user->id,
                'user_tenant_id' => $user->tenant_id,
                'current_tenant_id' => $currentTenant ? $currentTenant->id : null,
                'host' => $request->getHost(),
                'path' => $request->path(),
            ]);

            // Logout and redirect to central login
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->away(rtrim(config('app.url'), '/') . '/login');
        }

        return $next($request);
    }
}
