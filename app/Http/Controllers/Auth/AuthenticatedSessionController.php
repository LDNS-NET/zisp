<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        $tenantName = null;
        $tenantLogo = null;
        $host = request()->getHost();

        // Try to find tenant by domain
        $tenantId = \Illuminate\Support\Facades\DB::table('domains')
            ->where('domain', $host)
            ->value('tenant_id');

        if ($tenantId) {
            $tenant = \Illuminate\Support\Facades\DB::table('tenants')
                ->where('id', $tenantId)
                ->first();

            if ($tenant) {
                // Decode data column if it exists and is JSON
                $data = json_decode($tenant->data ?? '{}', true);
                $tenantName = $data['name'] ?? $tenant->id;
                
                // Get logo from TenantGeneralSetting
                $setting = \Illuminate\Support\Facades\DB::table('tenant_general_settings')
                    ->where('tenant_id', $tenantId)
                    ->first();
                
                if ($setting && $setting->logo) {
                    // Convert relative path to full URL
                    if (!str_starts_with($setting->logo, 'http')) {
                        $tenantLogo = Storage::disk('public')->url($setting->logo);
                    } else {
                        $tenantLogo = $setting->logo;
                    }
                }
            }
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'tenantName' => $tenantName,
            'tenantLogo' => $tenantLogo,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): HttpResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user && $user->tenant_id && !$user->is_super_admin) {
            $host = $request->getHost();
            $centralDomains = config('tenancy.central_domains', []);

            $tenant = $user->tenant;
            $tenantDomain = $tenant ? optional($tenant->domains()->first())->domain : null;

            if ($tenantDomain && (in_array($host, $centralDomains, true) || $host !== $tenantDomain)) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return Inertia::location('https://' . $tenantDomain . '/login');
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
