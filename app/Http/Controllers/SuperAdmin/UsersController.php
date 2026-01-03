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

        $users = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        
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

    public function show($id)
    {
        $user = User::findOrFail($id);
        // Fetch detailed tenant info from the all_tenants table (SuperAdmin\Users model)
        $tenantDetails = \App\Models\SuperAdmin\Users::where('tenant_id', $user->tenant_id)->first();

        $tenantSettings = TenantGeneralSetting::where('tenant_id', $user->tenant_id)->first();
        $mikrotiks = TenantMikrotik::withoutGlobalScope('created_by')->where('created_by', $user->id)->get();
        $totalEndUsers = NetworkUser::withoutGlobalScope('created_by')->where('created_by', $user->id)->count();
        $totalPayments = TenantPayment::withoutGlobalScope('created_by')->where('created_by', $user->id)->sum('amount');
        $totalMikrotiks = TenantMikrotik::withoutGlobalScope('created_by')->where('created_by', $user->id)->count();

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
