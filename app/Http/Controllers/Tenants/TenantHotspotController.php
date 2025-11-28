<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\TenantHotspot;
use App\Http\Requests\StoreTenantHotspotRequest;
use App\Http\Requests\UpdateTenantHotspotRequest;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantHotspotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $packages = Package::where('type', 'hotspot')
            ->orderBy('name')
            ->get();

        return Inertia::render('Hotspot/Index', [
            'packages' => $packages,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTenantHotspotRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TenantHotspot $tenantHotspot)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TenantHotspot $tenantHotspot)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTenantHotspotRequest $request, TenantHotspot $tenantHotspot)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TenantHotspot $tenantHotspot)
    {
        //
    }
}
