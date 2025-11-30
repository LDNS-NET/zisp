<?php

namespace App\Http\Controllers\Tenants;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use Inertia\Inertia;

class MikrotikDetailsController extends Controller
{
    public function index(Request $request, $tenantId)
    {
        $mikrotikDetails = TenantMikrotik::where('tenant_id', $tenantId)->first();

        return Inertia::render('Mikrotikdetails/Index', [
            'mikrotikDetails' => $mikrotikDetails,
            'tenantId' => $tenantId,
        ]);
    }
}
