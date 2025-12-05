<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tenants\NetworkUser;
use App\Models\User;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenant;
use App\Models\TenantGenenralSetting;
use App\Models\Tenants\TenantMikrotik;



class UsersController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);
        $tenant = Tenant::all();

        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
            'tenant' => $tenant,
        ]);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        // Fetch detailed tenant info from the all_tenants table (SuperAdmin\Users model)
        $tenantDetails = \App\Models\SuperAdmin\Users::where('tenant_id', $user->tenant_id)->first();

        $tenantSettings = TenantGenenralSetting::where('tenant_id', $user->tenant_id)->first();
        $mikrotiks = TenantMikrotik::where('tenant_id', $user->tenant_id)->get();
        $totalEndUsers = NetworkUser::where('tenant_id', $user->tenant_id)->count();
        $totalPayments = TenantPayment::where('tenant_id', $user->tenant_id)->sum('amount');
        $totalMikrotiks = TenantMikrotik::where('tenant_id', $user->tenant_id)->count();

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
