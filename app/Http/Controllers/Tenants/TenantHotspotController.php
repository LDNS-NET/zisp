<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\TenantHotspot;
use App\Http\Requests\StoreTenantHotspotRequest;
use App\Http\Requests\UpdateTenantHotspotRequest;
use App\Models\Package;
use App\Models\Tenants\TenantPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Tenants\TenantPaymentController;

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

    /**
     * Process hotspot package purchase with STK Push
     */
    public function purchaseSTKPush(Request $request)
    {
        $data = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'phone' => 'required|string|regex:/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8})$/',
        ]);

        $package = Package::findOrFail($data['package_id']);

        // Forward to TenantPaymentController for processing
        $paymentController = new TenantPaymentController();
        $request->merge([
            'phone' => $data['phone'],
            'package_id' => $data['package_id'],
            'amount' => $package->price,
        ]);

        return $paymentController->processSTKPush($request);
    }
}
