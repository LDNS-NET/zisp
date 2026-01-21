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
use App\Models\SuperAdminActivity;



class UsersController extends Controller
{
    public function index(Request $request)
    {
        $query = User::role('tenant_admin'); // Use Spatie role scope for reliability

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
        
        SuperAdminActivity::log(
            'user.suspended',
            "Suspended tenant: {$user->name} ({$user->email})",
            $user,
            ['tenant_id' => $user->tenant_id]
        );
        
        return back()->with('success', 'User suspended successfully.');
    }

    public function unsuspend(User $user)
    {
        $user->update(['is_suspended' => false]);
        
        SuperAdminActivity::log(
            'user.unsuspended',
            "Activated tenant: {$user->name} ({$user->email})",
            $user,
            ['tenant_id' => $user->tenant_id]
        );
        
        return back()->with('success', 'User activated successfully.');
    }

    public function destroy(User $user)
    {
        $userName = $user->name;
        $userEmail = $user->email;
        $tenantId = $user->tenant_id;
        
        // Optional: Add logic to delete tenant data (payments, users, etc.)
        // For now, just delete the user record
        $user->delete();
        
        SuperAdminActivity::log(
            'user.deleted',
            "Deleted tenant: {$userName} ({$userEmail})",
            null,
            ['tenant_id' => $tenantId, 'deleted_user' => $userName]
        );
        
        return back()->with('success', 'User deleted successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $oldData = $user->only(['name', 'email', 'phone', 'username']);
        
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
        $oldDomain = null;
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
        
        SuperAdminActivity::log(
            'user.updated',
            "Updated tenant details: {$user->name}",
            $user,
            [
                'old' => $oldData,
                'new' => $user->only(['name', 'email', 'phone', 'username']),
                'domain_changed' => $oldDomain !== $validated['domain'],
            ]
        );

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

    /**
     * Bulk action handler
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:suspend,activate,delete',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();
        $count = $users->count();

        switch ($validated['action']) {
            case 'suspend':
                foreach ($users as $user) {
                    $user->update(['is_suspended' => true]);
                }
                SuperAdminActivity::log(
                    'users.bulk_suspended',
                    "Bulk suspended {$count} tenants",
                    null,
                    ['user_ids' => $validated['user_ids'], 'count' => $count]
                );
                return back()->with('success', "{$count} users suspended successfully.");

            case 'activate':
                foreach ($users as $user) {
                    $user->update(['is_suspended' => false]);
                }
                SuperAdminActivity::log(
                    'users.bulk_activated',
                    "Bulk activated {$count} tenants",
                    null,
                    ['user_ids' => $validated['user_ids'], 'count' => $count]
                );
                return back()->with('success', "{$count} users activated successfully.");

            case 'delete':
                $userNames = $users->pluck('name')->toArray();
                User::whereIn('id', $validated['user_ids'])->delete();
                SuperAdminActivity::log(
                    'users.bulk_deleted',
                    "Bulk deleted {$count} tenants",
                    null,
                    ['user_ids' => $validated['user_ids'], 'user_names' => $userNames, 'count' => $count]
                );
                return back()->with('success', "{$count} users deleted successfully.");
        }
    }

    /**
     * Export users to CSV
     */
    public function export(Request $request)
    {
        $query = User::role('tenant_admin');

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('tenant_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_suspended', false);
            } elseif ($request->status === 'suspended') {
                $query->where('is_suspended', true);
            }
        }

        if ($request->filled('country')) {
            $query->where('country_code', $request->country);
        }

        $users = $query->with('tenantGeneralSetting')->get();

        // Create CSV
        $filename = 'tenants_export_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID',
                'Name',
                'Email',
                'Phone',
                'Username',
                'Tenant ID',
                'Domain',
                'Country',
                'Status',
                'Subscription Expires',
                'Created At',
            ]);

            // CSV Data
            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone,
                    $user->username,
                    $user->tenant_id,
                    $user->tenantGeneralSetting?->website ?? '',
                    $user->country ?? '',
                    $user->is_suspended ? 'Suspended' : 'Active',
                    $user->subscription_expires_at?->format('Y-m-d H:i:s') ?? '',
                    $user->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        SuperAdminActivity::log(
            'users.exported',
            "Exported {$users->count()} tenants to CSV",
            null,
            ['count' => $users->count(), 'filters' => $request->only(['search', 'status', 'country'])]
        );

        return response()->stream($callback, 200, $headers);
    }

    public function impersonate(User $user)
    {
        // 1. Ensure the user is not a superadmin
        if ($user->role === 'superadmin') {
            return back()->with('error', 'Cannot impersonate another superadmin.');
        }

        // 2. Store the original superadmin ID in the session
        session()->put('impersonated_by', auth()->id());

        // 3. Log the activity
        SuperAdminActivity::log(
            'user.impersonated',
            "Started impersonating tenant: {$user->name}",
            $user
        );

        // 4. Log in as the tenant admin
        auth()->login($user);

        // 5. Redirect to the tenant dashboard
        return redirect()->route('dashboard')->with('success', "Now impersonating {$user->name}");
    }
}
