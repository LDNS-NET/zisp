<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tenants\NetworkUser;
use App\Models\SuperAdmin\Users;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenant;
use App\Models\Tenants\TenantGenenralSetting;
use App\Models\Tenants\TenantMikrotik;



class UsersController extends Controller
{
    public function index()
    {
        $users = Users::orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function show($id)
    {
        $user = Users::findOrFail($id);

        // Fetch related data if needed, but the Users model (all_tenants) seems to have most info.
        // If there are other related tables, fetch them here.
        // Based on the model definition, most info is in the table itself.

        // We might still want related data if it exists and isn't in the main table
        // But for now, let's pass the user object which has all the fields from all_tenants table

        $tenantSettings = TenantGenenralSetting::where('tenant_id', $user->tenant_id)->first();
        $mikrotiks = TenantMikrotik::where('tenant_id', $user->tenant_id)->get();
        $totalEndUsers = NetworkUser::where('tenant_id', $user->tenant_id)->count();
        $totalPayments = TenantPayment::where('tenant_id', $user->tenant_id)->sum('amount');
        $totalMikrotiks = TenantMikrotik::where('tenant_id', $user->tenant_id)->count();

        return Inertia::render('SuperAdmin/Users/Show', [
            'user' => $user,
            'tenantSettings' => $tenantSettings,
            'mikrotiks' => $mikrotiks,
            'totalEndUsers' => $totalEndUsers,
            'totalPayments' => $totalPayments,
            'totalMikrotiks' => $totalMikrotiks,
        ]);
    }
}
