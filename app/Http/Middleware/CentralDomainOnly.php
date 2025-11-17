<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CentralDomainOnly middleware ensures that specific routes
 * are only accessible on central domains (zyraaf.cloud)
 * 
 * Behavior:
 * - Central domain access → allow
 * - Valid tenant subdomain → redirect to tenant login
 * - Invalid subdomain → redirect to central registration
 */
class CentralDomainOnly
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);
        $centralRegisterUrl = rtrim(config('app.url'), '/') . '/register';

        // Check if the request is coming from a central domain
        if (!in_array($host, $centralDomains, true)) {
            // Log the attempt to access central domain route from tenant domain
            \Log::info('Central domain route accessed from tenant domain', [
                'host' => $host,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            // Check if this is a valid tenant domain
            $domain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->first();
            
            if ($domain) {
                // Redirect to tenant login page with a message
                \Log::info('Redirecting to tenant login from central domain route', [
                    'tenant_domain' => $host,
                    'requested_path' => $request->path(),
                ]);
                return redirect()->away("https://{$host}/login");
            }

            // Invalid domain - redirect to central registration
            \Log::info('Invalid domain accessing central route, redirecting to registration', [
                'invalid_domain' => $host,
                'requested_path' => $request->path(),
            ]);
            return redirect()->away($centralRegisterUrl);
        }

        return $next($request);
    }
}
