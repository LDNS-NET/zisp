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

        // 1. If host is a central domain, check if user is authenticated
        if (in_array($host, $centralDomains, true)) {
            // If user is authenticated and is a tenant (not super admin), redirect to their subdomain
            if (Auth::check()) {
                $user = $request->user();
                
                // Check if this is a tenant user (has tenant_id and not super admin)
                if ($user->tenant_id && !($user->is_super_admin ?? false)) {
                    $tenant = $user->tenant;
                    if ($tenant) {
                        $tenantDomain = optional($tenant->domains()->first())->domain;
                        if ($tenantDomain) {
                            // Log the redirect for debugging
                            \Log::info('Tenant user accessing central domain, redirecting to subdomain', [
                                'user_id' => $user->id,
                                'tenant_id' => $user->tenant_id,
                                'central_host' => $host,
                                'tenant_domain' => $tenantDomain,
                                'requested_path' => $request->path(),
                            ]);
                            

                            // Force logout from central domain and redirect to tenant subdomain
                            Auth::guard('web')->logout();
                            $request->session()->invalidate();
                            $request->session()->regenerateToken();
                            

                            return redirect()->away("https://{$tenantDomain}/login");
                        }
                    }
                    
                    // User has tenant_id but no domain assigned - logout and redirect to registration
                    \Log::warning('Tenant user has no domain assigned', [
                        'user_id' => $user->id,
                        'tenant_id' => $user->tenant_id,
                    ]);
                    
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    
                    return redirect()->away($centralRegisterUrl);
                }
            }
            
            // Central domain - allow access (for guests, super admins, or central-only users)
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

        // 4. Super-admins are not restricted to a subdomain
        if ($user->is_super_admin ?? false) {
            return $next($request);
        }

        // 5. Verify that authenticated user belongs to this tenant
        // Note: tenant() is available because InitializeTenancyByDomain middleware runs before this
        if ($user->tenant_id) {
            $currentTenant = tenant();
            
            // Ensure the user's tenant matches the current tenant
            if ($currentTenant && $user->tenant_id !== $currentTenant->id) {
                \Log::warning('User accessing wrong tenant domain', [
                    'user_id' => $user->id,
                    'user_tenant_id' => $user->tenant_id,
                    'current_tenant_id' => $currentTenant->id,
                    'current_domain' => $host,
                ]);
                
                // Force logout and redirect to correct tenant domain
                return $this->forceLogoutToCorrectTenantDomain($request, $user);
            }
        } else {
            // User is authenticated but has no tenant_id - shouldn't happen on tenant domain
            \Log::warning('Non-tenant user accessing tenant domain', [
                'user_id' => $user->id,
                'domain' => $host,
            ]);
            
            // Logout and redirect to central login
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect()->away(rtrim(config('app.url'), '/') . '/login');
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

    /**
     * Force logout user and redirect to their correct tenant domain
     */
    protected function forceLogoutToCorrectTenantDomain(Request $request, $user): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Get the user's correct tenant domain
        if ($user->tenant) {
            $tenantDomain = optional($user->tenant->domains()->first())->domain;
            if ($tenantDomain) {
                \Log::info('Redirecting user to correct tenant domain', [
                    'user_id' => $user->id,
                    'correct_domain' => $tenantDomain,
                ]);
                return redirect()->away("https://{$tenantDomain}/login");
            }
        }

        // Fallback to central registration if no domain found
        \Log::warning('No tenant domain found for user', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
        
        return redirect()->away(rtrim(config('app.url'), '/') . '/register');
    }
}
