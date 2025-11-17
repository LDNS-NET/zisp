<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Ensure Tenant Domain middleware handles:
 * 1. Tenant domain validation against domains table
 * 2. Session validation and proper redirects
 * 3. Graceful handling of invalid subdomains
 * 
 * Behavior:
 * - Valid tenant subdomain + active session → allow access
 * - Valid tenant subdomain + no session → redirect to tenant login
 * - Invalid subdomain → redirect to central registration
 * - Central domains → passthrough
 */
class EnsureTenantDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', []);
        $centralRegisterUrl = rtrim(config('app.url'), '/') . '/register';

        // 1. If host is a central domain, simply proceed (central routes)
        if (in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        // 2. Check if this host exists in `domains` table. If not → redirect to central registration.
        if (!Domain::where('domain', $host)->exists()) {
            // Log the invalid domain attempt for debugging
            \Log::info('Invalid tenant domain attempted', ['domain' => $host, 'ip' => $request->ip()]);
            return redirect()->away($centralRegisterUrl);
        }

        // 3. If guest (unauthenticated) and not already on login routes → redirect to tenant login.
        if (!Auth::check()) {
            // Allow access to login and related auth routes
            $allowedAuthRoutes = ['login', 'login/*', 'register', 'register/*', 'password/*', 'forgot-password'];
            
            if (!$this->requestMatchesPatterns($request, $allowedAuthRoutes)) {
                return redirect()->to('/login');
            }
            
            // Already on auth route -> let it through
            return $next($request);
        }

        $user = $request->user();

        // Super-admins are not restricted to a subdomain
        if ($user->is_super_admin ?? false) {
            return $next($request);
        }

        // 4. Tenant users should always access through their correct subdomain
        if ($user->tenant_id) {
            $tenant = $user->tenant;
            if ($tenant) {
                $tenantDomain = optional($tenant->domains()->first())->domain;
                
                // If user is accessing from wrong subdomain, redirect to correct one
                if ($tenantDomain && $host !== $tenantDomain) {
                    return $this->forceLogoutToTenantDomain($request, $user, $tenantDomain);
                }
                
                // If tenant exists but user has no valid tenant domain, logout
                if (!$tenantDomain) {
                    \Log::warning('User has tenant_id but no domain assigned', [
                        'user_id' => $user->id, 
                        'tenant_id' => $user->tenant_id
                    ]);
                    return $this->forceLogoutToTenantDomain($request, $user);
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if current request matches any of the given patterns
     */
    protected function requestMatchesPatterns(Request $request, array $patterns): bool
    {
        $path = $request->path();
        
        foreach ($patterns as $pattern) {
            if ($this->patternMatches($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Simple pattern matching for route patterns (supports wildcards)
     */
    protected function patternMatches(string $pattern, string $path): bool
    {
        // Convert pattern to regex
        $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
        return preg_match("/^{$regex}$/", $path);
    }

    /**
     * Log the user out and redirect to their tenant domain (or central login if unknown).
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
