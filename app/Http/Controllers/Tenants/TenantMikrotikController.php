<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use Inertia\Inertia;

class TenantMikrotikController extends Controller
{
    public function index()
    {
        $tenantMikrotiks = TenantMikrotik::all();
        return Inertia::render('Mikrotiks/Index', [
            'tenantMikrotiks' => $tenantMikrotiks,
        ]);
    }
}