<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure that an authenticated tenant user is always accessing the app
 * through their own assigned tenant subdomain.
 *
 * Behaviour:
 *  - If the user is not authenticated → passthrough.
 *  - If the user is a super-admin (is_super_admin === true) → passthrough
 *  - Otherwise, compare the current request host with the first domain
 *    attached to the user's tenant. If they don't match OR if the host is in
 *    central_domains, log the user out and redirect them to their correct
 *    subdomain.
 */
class EnsureTenantDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);
        $centralRegisterUrl = rtrim(config('app.url'), '/') . '/register';

        // 1. If guest (unauthenticated) and not already on /login → redirect to login.
        if (!Auth::check()) {
            if (!$request->is('login', 'login/*')) {
                // If on central domain, redirect to central login
                if (in_array($host, $centralDomains, true)) {
                    return redirect()->to('/login');
                }
                // If on tenant domain, redirect to tenant login
                return redirect()->to('/login');
            }
            // Already on login -> let it through
            return $next($request);
        }

        $user = $request->user();

        // Super-admins are not restricted to a subdomain
        if ($user->is_super_admin ?? false) {
            return $next($request);
        }

        // 2. If host is a central domain and user is not super admin, check if they're a tenant
        if (in_array($host, $centralDomains, true)) {
            // Tenant users should not access tenant routes from central domains
            if ($user->tenant_id) {
                return $this->forceLogoutToTenantDomain($request, $user);
            }
            // Non-tenant users on central domain can proceed
            return $next($request);
        }

        // 3. Check if this host exists in `domains` table. If not → redirect to central registration.
        if (! \Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->exists()) {
            return redirect()->away($centralRegisterUrl);
        }

        // 4. Tenant users should always have tenant_id and be on their correct domain
        if ($user->tenant_id) {
            $tenant = $user->tenant;
            if ($tenant) {
                $tenantDomain = optional($tenant->domains()->first())->domain;
                if ($tenantDomain && $host !== $tenantDomain) {
                    return $this->forceLogoutToTenantDomain($request, $user, $tenantDomain);
                }
            }
        }

        return $next($request);
    }

    /**
     * Log the user out and redirect to their tenant domain (or home page if unknown).
     */
    protected function forceLogoutToTenantDomain(Request $request, $user, string $tenantDomain = null): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Fallback: if tenantDomain not provided, derive from relation
        if (!$tenantDomain && $user->tenant) {
            $tenantDomain = optional($user->tenant->domains()->first())->domain;
        }

        $target = $tenantDomain ? 'https://' . $tenantDomain . '/login' : route('login');

        return redirect()->away($target);
    }
}
