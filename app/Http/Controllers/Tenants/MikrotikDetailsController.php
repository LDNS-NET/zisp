<?php

namespace App\Http\Controllers\Tenants;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use Inertia\Inertia;

class MikrotikDetailsController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = tenant('id'); // since you're using Stancl Tenancy

        $mikrotikDetails = TenantMikrotik::where('tenant_id', $tenantId)->first();

        return Inertia::render('Tenants/Mikrotikdetails/Index', [
            'mikrotikDetails' => $mikrotikDetails,
            'tenantId'        => $tenantId,
        ]);
    }

}
