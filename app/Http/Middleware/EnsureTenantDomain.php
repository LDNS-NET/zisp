<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Contracts\Tenancy;

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

        // 1. If host is a central domain, simply proceed.
        if (in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        // 2. Check if this host exists in `domains` table. If not → redirect to central registration.
        $domain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->first();
        if (!$domain) {
            return redirect()->away($centralRegisterUrl);
        }

        // 2b. A valid tenant domain was found – bootstrap tenancy early so that subsequent
        //     code (e.g. Breeze login pages) runs in the correct tenant context. This is
        //     important because the /login routes live outside the dedicated tenant route
        //     file and therefore don't have the InitializeTenancyByDomain middleware.
        try {
            if (! tenancy()->initialized) {
                tenancy()->initialize($domain->tenant);
            }
        } catch (\Throwable $e) {
            // If tenancy is already initialised we can safely ignore the exception.
            // For any other error, fall back to central registration to avoid a 500.
            report($e);
            return redirect()->away($centralRegisterUrl);
        }

        // 3. If guest (unauthenticated) and not already on /login → redirect to tenant login.
        if (!Auth::check()) {
            if (!$request->is('login', 'login/*')) {
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

        // Tenant users should always have tenant_id
        if ($user->tenant_id) {
            $host = $request->getHost();
            $centralDomains = config('tenancy.central_domains', []);

            // If the request host is a central domain, always redirect
            if (in_array($host, $centralDomains, true)) {
                return $this->forceLogoutToTenantDomain($request, $user);
            }

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
