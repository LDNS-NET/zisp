<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
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
            'country' => 'nullable|string|max:255',
            'country_code' => 'nullable|string|max:10',
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
                'country' => $request->country,
                'country_code' => $request->country_code,
                'currency' => $request->currency,
                'currency_name' => $request->currency_name,
                'dial_code' => $request->dial_code,
                'subscription_expires_at' => now()->addDays(14),
                'is_suspended' => false,
            ]);

            $baseSubdomain = Str::slug($request->username);
            $subdomain = $baseSubdomain;
            $counter = 1;

            while (DB::table('tenants')->where('subdomain', $subdomain)->exists()) {
                $subdomain = $baseSubdomain . '-' . $counter++;
            }

            $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $fullDomain = $subdomain . '.' . $baseDomain;

            // Create tenant
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

            DB::table('domains')->insert([
                'domain' => $fullDomain,
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Associate user with tenant
            $user->tenant_id = $tenantId;
            $user->save();
        });

        event(new Registered($user));
        Auth::login($user);

        // Redirect to tenant dashboard
        return redirect()->route('dashboard', [], false);


    }
}
