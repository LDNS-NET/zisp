<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Package; // or TenantHotspotPackage if present
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stancl\Tenancy\Contracts\Tenant;

class HotspotPortalController extends Controller
{
    /**
     * Display the hotspot captive portal for the current tenant.
     */
    public function index(Request $request): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant) {
            // No tenant resolved for this domain – return 404 instead of 500
            abort(404, 'Tenant not found for this domain.');
        }

        // Fetch lightweight package data for this tenant only
        $packages = Package::query()
            ->forTenant($tenant->id)
            ->select(['id', 'name', 'price', 'upload_speed', 'download_speed', 'duration_value', 'duration_unit'])
            ->get()
            ->map(fn (Package $p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'price'    => $p->price,
                'speed'    => sprintf('%s/%s Mbps', $p->upload_speed, $p->download_speed),
                'validity' => $p->duration_value . ' ' . $p->duration_unit,
            ]);

        // Capture Mikrotik hotspot GET params exactly as received
        $hotspotParams = $request->only([
            'link-login',
            'link-orig',
            'chap-id',
            'chap-challenge',
            'error',
            'username',
            'mac',
            'ip',
            'login-by',
            'dst',
            'popup',
        ]);

        return Inertia::render('Tenants/HotspotPortal/Index', [
            'tenant'        => [
                'id'            => $tenant->id,
                'business_name' => $tenant->business_name,
                'phone'         => $tenant->phone,
                'domain'        => $tenant->domains->first()->domain ?? $request->getHost(),
            ],
            'packages'      => $packages,
            'hotspotParams' => $hotspotParams,
        ]);
    }
}
