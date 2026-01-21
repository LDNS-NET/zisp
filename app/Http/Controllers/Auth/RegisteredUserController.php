<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class RegisteredUserController extends Controller
{
    /**
     * Show Registration Page
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Register a new user + create tenant + redirect to subdomain login.
     */
    public function store(Request $request): HttpResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:' . User::class,
            'email' => 'required|string|email|max:255|unique:' . User::class,
            'phone' => 'required|string|max:255|unique:' . User::class,
            'username' => 'required|string|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'country' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|max:10',
            'currency' => 'nullable|string|max:10',
            'currency_name' => 'nullable|string|max:255',
            'dial_code' => 'nullable|string|max:10',
        ]);

        $user = null;

        DB::transaction(function () use ($request, &$user) {
            // Create User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'country' => $request->country,
                'country_code' => $request->country_code,
                'currency' => $request->currency,
                'currency_name' => $request->currency_name,
                'dial_code' => $request->dial_code,
                'subscription_expires_at' => now()->addDays(20),
                'is_suspended' => false,
            ]);

            // Create unique subdomain from username
            $baseSubdomain = Str::slug($request->username);
            $subdomain = $baseSubdomain;
            $i = 1;

            while (DB::table('tenants')->where('subdomain', $subdomain)->exists()) {
                $subdomain = $baseSubdomain . '-' . $i++;
            }

            // Root domain from APP_URL
            $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $fullDomain = $subdomain . '.' . $baseDomain;

            // Create Tenant
            $tenantId = (string) Str::uuid();

            DB::table('tenants')->insert([
                'id' => $tenantId,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'username' => $user->username,
                'country' => $user->country,
                'country_code' => $user->country_code,
                'currency' => $user->currency,
                'currency_name' => $user->currency_name,
                'dial_code' => $user->dial_code,
                'subdomain' => $subdomain,
                'data' => json_encode(['name' => $user->name]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Attach domain
            DB::table('domains')->insert([
                'domain' => $fullDomain,
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Link user to tenant
            $user->tenant_id = $tenantId;
            $user->role = 'tenant_admin'; // Set the role column for legacy compatibility
            $user->save();

            // Assign Spatie role
            $user->assignRole('tenant_admin');
        });

        event(new Registered($user));

        // Logout from main domain → required for multi-tenant login
        Auth::logout();

        // Build login redirect (username.yourdomain/login)
        $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $tenantDomain = $user->username . '.' . $baseDomain;
        $loginUrl = 'https://' . $tenantDomain . '/login';

        // Use Inertia's external redirect to avoid CORS issues
        return Inertia::location($loginUrl);

    }
}
