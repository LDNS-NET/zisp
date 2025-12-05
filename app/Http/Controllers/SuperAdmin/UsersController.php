<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Tenants\NetworkUser;
use App\Models\User;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenant;
use App\Models\Tenants\TenantGenenralSetting;
use App\Models\Tenants\TenantMikrotik;



class UsersController extends Controller
{
    public function index() {
        $users = User::orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
        ]);
    }
}
