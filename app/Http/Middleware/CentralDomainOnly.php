<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Restrict certain routes so they are accessible only on the landlord / central
 * domain (e.g. zyraaf.cloud, www.zyraaf.cloud). If accessed from a tenant
 * sub-domain it redirects to an appropriate page.
 */
class CentralDomainOnly
{
    public function handle(Request $request, Closure $next)
    {
        $host           = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);

        // If we are on a central domain, allow the request.
        if (in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        // If host matches a tenant domain, send user to that tenant's login.
        if (Domain::where('domain', $host)->exists()) {
            // Avoid redirect loop when already on /login or any auth posting.
            if ($request->is('login', 'login/*')) {
                return $next($request);
            }

            return redirect()->to('/login');
        }

        // Unknown host – bounce to landlord registration page.
        return redirect()->away(rtrim(config('app.url'), '/') . '/register');
    }
}
