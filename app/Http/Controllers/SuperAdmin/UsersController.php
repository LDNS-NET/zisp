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
