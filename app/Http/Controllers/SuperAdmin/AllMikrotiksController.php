<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\MOdels\Tenants\TenantMikroTik;
use inertia\Inertia;

class AllMikrotiksController extends Controller
{
    public function index()
    {
        $mikrotiks = TenantMikroTik::all();
        return Inertia::render('SuperAdmin/Allmikrotiks/Index', [
            'mikrotiks' => $mikrotiks,
        ]);
    }

    //This is the Show function
    public function show($id) {
        $allmikrotiks = TenantMikroTik::where('tenant_id', $id)->first();

        return Inertia::render('SuperAdmin/Allmikrotiks/Show', [
            'allmikrotiks' => $allmikrotiks,
        ]);
    }
}
