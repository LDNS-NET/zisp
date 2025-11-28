<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Package;

class HotspotPortalController extends Controller
{
    /**
     * Display the hotspot portal landing page.
     *
     * This method resolves the current tenant, fetches tenant business details,
     * loads the tenant-specific hotspot packages, and forwards any MikroTik
     * hotspot parameters to the Vue page for automatic handling.
     */
    public function index(Request $request)
    {
        $tenant = tenant();

        if (!$tenant) {
            abort(404, 'Tenant context could not be resolved.');
        }

        $tenantInfo = [
            'business_name' => $tenant->business_name ?? $tenant->name,
            'phone'         => $tenant->phone ?? $tenant->support_phone ?? null,
            'domain'        => $tenant->primary_domain
                ?? ($tenant->domains->first()->domain ?? $request->getHost()),
        ];

        // Fetch hotspot packages that belong to this tenant
        $packages = Package::query()
            ->where('type', 'hotspot')
            ->where(function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)
                  ->orWhere('created_by', $tenant->id);
            })
            ->get();

        return Inertia::render('Tenants/HotspotPortal/Index', [
            'tenant'        => $tenantInfo,
            'packages'      => $packages,
            'hotspotParams' => $request->all(),
        ]);
    }
}
