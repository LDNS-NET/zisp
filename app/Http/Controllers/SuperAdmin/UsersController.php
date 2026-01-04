<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tenants\NetworkUser;
use App\Models\User;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenant;
use App\Models\TenantGeneralSetting;
use App\Models\Tenants\TenantMikrotik;



class UsersController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->where('role', '!=', 'superadmin'); // Exclude superadmins

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('tenant_id', 'like', "%{$search}%");
            });
        }

        // Filter by Status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_suspended', false);
            } elseif ($request->status === 'suspended') {
                $query->where('is_suspended', true);
            }
        }

        // Filter by Country
        if ($request->filled('country')) {
            $query->where('country_code', $request->country);
        }

        $users = $query->with('tenantGeneralSetting')->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        
        // Get unique countries for filter
        $countries = User::select('country_code', 'country')->distinct()->whereNotNull('country_code')->get();

        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'status', 'country']),
            'countries' => $countries,
        ]);
    }

    public function suspend(User $user)
    {
        $user->update(['is_suspended' => true]);
        return back()->with('success', 'User suspended successfully.');
    }

    public function unsuspend(User $user)
    {
        $user->update(['is_suspended' => false]);
        return back()->with('success', 'User activated successfully.');
    }

    public function destroy(User $user)
    {
        // Optional: Add logic to delete tenant data (payments, users, etc.)
        // For now, just delete the user record
        $user->delete();
        return back()->with('success', 'User deleted successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:20',
            'username' => 'required|string|unique:users,username,' . $user->id,
            'domain' => 'nullable|string|max:255',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'username' => $validated['username'],
        ]);

        // Update domain in TenantGeneralSetting and domains table
        if ($user->tenant_id) {
            $oldDomain = TenantGeneralSetting::where('tenant_id', $user->tenant_id)->value('website');
            
            TenantGeneralSetting::updateOrCreate(
                ['tenant_id' => $user->tenant_id],
                ['website' => $validated['domain']]
            );

            $tenant = \App\Models\Tenant::find($user->tenant_id);
            if ($tenant) {
                // Remove old domain if it changed
                if ($oldDomain && $oldDomain !== $validated['domain']) {
                    $tenant->domains()->where('domain', $oldDomain)->delete();
                }
                
                // Add new domain if provided and not exists
                if ($validated['domain'] && !$tenant->domains()->where('domain', $validated['domain'])->exists()) {
                    $tenant->domains()->create(['domain' => $validated['domain']]);
                }
            }
        }

        return back()->with('success', 'User details updated successfully.');
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        
        // Fetch settings
        $tenantSettings = TenantGeneralSetting::where('tenant_id', $user->tenant_id)->first();
        
        // Calculate stats
        $mikrotiks = TenantMikrotik::withoutGlobalScope('created_by')->where('created_by', $user->id)->get();
        $totalEndUsers = NetworkUser::withoutGlobalScope('created_by')->where('created_by', $user->id)->count();
        $totalPayments = TenantPayment::withoutGlobalScope('created_by')->where('created_by', $user->id)->sum('amount');
        $totalMikrotiks = TenantMikrotik::withoutGlobalScope('created_by')->where('created_by', $user->id)->count();

        // Construct tenantDetails manually to bypass missing 'all_tenants' table
        $tenantDetails = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->is_suspended ? 'suspended' : 'active',
            'business_registration_number' => null, // Missing in DB
            'address' => $tenantSettings?->address,
            'country' => $tenantSettings?->country ?? $user->country_code,
            'timezone' => $tenantSettings?->timezone,
            'language' => $tenantSettings?->language,
            'domain' => $tenantSettings?->website, // Mapping website to domain
            'wallet_balance' => '0.00', // Missing
            'user_value' => '0.00', // Missing
            'bank_name' => null,
            'account_name' => null,
            'account_number' => null,
            'mpesa_number' => null,
            'paybill_number' => null,
            'till_number' => null,
            'all_subscribers' => $totalEndUsers,
            'users_count' => $totalEndUsers,
            'mikrotik_count' => $totalMikrotiks,
            'lifetime_traffic' => '0 MB', // Missing
            'joining_date' => $user->created_at->format('Y-m-d'),
            'expiry_date' => $user->subscription_expires_at?->format('Y-m-d'),
            'prunning_date' => null,
            'email_verified_at' => $user->email_verified_at,
            'phone_verified_at' => null,
            'last_login_ip' => null,
            'two_factor_enabled' => false,
            'account_locked_until' => null,
        ];

        return Inertia::render('SuperAdmin/Users/Show', [
            'user' => $user,
            'tenantDetails' => $tenantDetails,
            'tenantSettings' => $tenantSettings,
            'mikrotiks' => $mikrotiks,
            'totalEndUsers' => $totalEndUsers,
            'totalPayments' => $totalPayments,
            'totalMikrotiks' => $totalMikrotiks,
        ]);
    }
}
