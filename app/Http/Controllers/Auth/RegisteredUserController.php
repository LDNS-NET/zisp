<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:' . User::class,
            'email' => 'required|string|email|max:255|unique:' . User::class,
            'phone' => 'required|string|max:255|unique:' . User::class,
            'username' => 'required|string|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = null;

        DB::transaction(function () use ($request, &$user) {
            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'subscription_expires_at' => now()->addDays(14),
                'is_suspended' => false,
            ]);

            // Create tenant
            $tenantId = (string) Str::uuid();
            // Generate a unique subdomain (slug from username or name)
            $base = Str::slug($user->username ?? $user->name);
            $subdomain = $base ?: 'tenant';
            $counter = 0;
            while (DB::table('tenants')->where('subdomain', $subdomain)->exists()) {
                $counter++;
                $subdomain = $base . '-' . $counter;
            }

            DB::table('tenants')->insert([
                'id' => $tenantId,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'username' => $user->username,
                'subdomain' => $subdomain,
                'data' => json_encode(['name' => $user->name]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Also create a domain record for Stancl Tenancy so the tenant can be resolved by domain/subdomain.
            // Build a full domain when possible (subdomain + host from APP_URL), otherwise store the subdomain alone.
            $appUrlHost = parse_url(config('app.url') ?? env('APP_URL', ''), PHP_URL_HOST);
            $fullDomain = $appUrlHost ? $subdomain . '.' . $appUrlHost : $subdomain;

            // Use the tenancy Domain model so events / caches are handled by the package.
            Domain::create([
                'domain' => $fullDomain,
                'tenant_id' => $tenantId,
            ]);

            // Associate user with tenant
            $user->tenant_id = $tenantId;
            $user->save();
        });

        event(new Registered($user));
        Auth::login($user);

    // Redirect to tenant dashboard
    // Previous code passed `false` as the third argument which becomes an invalid HTTP status (0).
    // Use the normal redirect (default 302) or explicitly pass a valid status if needed.
    return redirect()->route('dashboard');


    }
}
