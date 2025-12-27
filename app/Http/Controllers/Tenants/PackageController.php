<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Tenants\TenantHotspot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Services\Mikrotik\HotspotProfileService;

class PackageController extends Controller
{
    /**
     * Display a listing of packages.
     */
    public function index()
    {
        $packages = Package::latest()->paginate(10)->withQueryString();
        $currency = auth()->user()?->tenant?->currency ?? 'KES';

        return Inertia::render('Packages/index', [
            'packages'   => $packages->items(),
            'pagination' => $packages->toArray(),
            'currency'   => $currency,
        ]);
    }

    /**
     * Store a newly created package in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePackage($request);
        $tenantId = tenant()?->id ?? auth()->user()?->tenant_id;

        if (!$tenantId) abort(500, 'Tenant context not resolved');

        DB::transaction(function () use ($validated, $tenantId) {

            // 1️⃣ Generate a unique mikrotik_profile for hotspot packages
            $mikrotikProfile = $validated['type'] === 'hotspot'
                ? $validated['name'] . '-' . time()
                : null;

            // 2️⃣ Create package in DB
            $package = Package::create([
                ...$validated,
                'tenant_id'        => $tenantId,
                'created_by'       => auth()->id(),
                'mikrotik_profile' => $mikrotikProfile,
            ]);

            // 3️⃣ If hotspot, create TenantHotspot + MikroTik profile
            if ($package->type === 'hotspot') {

                TenantHotspot::create([
                    'tenant_id'       => $tenantId,
                    'package_id'      => $package->id,
                    'name'            => $package->name,
                    'duration_value'  => $package->duration_value,
                    'duration_unit'   => $package->duration_unit,
                    'price'           => $package->price,
                    'device_limit'    => $package->device_limit,
                    'upload_speed'    => $package->upload_speed,
                    'download_speed'  => $package->download_speed,
                    'burst_limit'     => $package->burst_limit,
                    'created_by'      => auth()->id(),
                ]);

                $mikrotik = app(HotspotProfileService::class);
                $mikrotik->syncFromPackage($package);
            }
        });

        return redirect()->route('packages.index')
            ->with('success', 'Package created successfully.');
    }

    /**
     * Update the specified package in storage.
     */
    public function update(Request $request, Package $package)
    {
        $validated = $this->validatePackage($request, $package->id);
        $package->update($validated);

        // Sync MikroTik profile if hotspot
        if ($package->type === 'hotspot') {
            $mikrotik = app(HotspotProfileService::class);
            $mikrotik->syncFromPackage($package);
        }

        return redirect()->route('packages.index')
            ->with('success', 'Package updated successfully.');
    }

    /**
     * Remove the specified package from storage.
     */
    public function destroy(Package $package)
    {
        if ($package->type === 'hotspot') {
            $mikrotik = app(HotspotProfileService::class);
            $mikrotik->deleteProfile($package->mikrotik_profile);
        }

        $package->delete();

        return redirect()->route('packages.index')
            ->with('success', 'Package deleted successfully.');
    }

    /**
     * Bulk delete packages.
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:packages,id',
        ]);

        $packages = Package::whereIn('id', $request->ids)->get();

        foreach ($packages as $package) {
            if ($package->type === 'hotspot') {
                $mikrotik = app(HotspotProfileService::class);
                $mikrotik->deleteProfile($package->mikrotik_profile);
            }
        }

        Package::whereIn('id', $request->ids)->delete();

        return back()->with('success', 'Selected packages deleted successfully.');
    }

    /**
     * Shared validation for store and update.
     */
    protected function validatePackage(Request $request, $id = null)
    {
        $rules = [
            'name'           => ['required', 'string', 'max:255', Rule::unique('packages')->ignore($id)],
            'type'           => ['required', 'in:hotspot,pppoe,static'],
            'price'          => ['required', 'numeric', 'min:0'],
            'duration_value' => ['required', 'integer', 'min:1'],
            'duration_unit'  => ['required', 'in:minutes,hours,days,weeks,months'],
            'upload_speed'   => ['required', 'numeric', 'min:1'],
            'download_speed' => ['required', 'numeric', 'min:1'],
            'burst_limit'    => ['nullable', 'numeric', 'min:0'],
            'device_limit'   => ['nullable', 'integer', 'min:1'],
        ];

        if ($request->input('type') === 'hotspot') {
            $rules['device_limit'] = ['required', 'integer', 'min:1'];
        }

        return $request->validate($rules);
    }
}
