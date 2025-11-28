<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

class HotspotPortalController extends Controller
{
    /**
     * Display the hotspot captive portal landing page (public).
     */
    public function index(Request $request)
    {
        // Resolve the active tenant from tenancy package helper
        $tenant = tenant();

        if (!$tenant) {
            // As a fallback, attempt to resolve by current host
            $tenant = Tenant::query()->whereHas('domains', function ($q) {
                $q->where('domain', request()->getHost());
            })->first();
        }

        abort_if(!$tenant, 404, 'Tenant not found');

        // Fetch hotspot-specific packages available for this tenant
        $packages = Package::query()
            ->where('type', 'hotspot')
            ->when($tenant->id, fn ($q) => $q->where(function ($inner) use ($tenant) {
                // if packages table has tenant_id column use it, otherwise rely on created_by scope
                if (Schema::hasColumn('packages', 'tenant_id')) {
                    $inner->where('tenant_id', $tenant->id);
                }
            }))
            ->get();

        return Inertia::render('Tenants/HotspotPortal/Index', [
            'tenant' => [
                'business_name' => $tenant->business_name ?? $tenant->name,
                'phone'         => $tenant->phone ?? null,
                'domain'        => $tenant->domains->first()->domain ?? request()->getHost(),
            ],
            'packages'     => $packages,
            'hotspotParams' => $request->all(),
        ]);
    }
}
