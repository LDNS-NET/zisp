<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Tenants\TenantHotspot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class PackageController extends Controller
{
    /**
     * Display a listing of the packages.
     */
    public function index()
    {
        $packages = Package::latest()->paginate(10)->withQueryString();

        return Inertia::render('Packages/index', [
            'packages' => $packages->items(),
            'pagination' => $packages->toArray(),

        ]);
    }

    /**
     * Store a newly created package in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePackage($request);
        $validated['created_by'] = auth()->id();

        $package = null;

        DB::transaction(function () use ($validated, &$package) {
            // Create the package
            $package = Package::create($validated);

            // If it's a hotspot package, create corresponding tenant_hotspot record
            if ($validated['type'] === 'hotspot') {
                $tenantId = tenant('id');
                if ($tenantId) {
                    DB::table('tenant_hotspot')->insert([
                        'tenant_id' => $tenantId,
                        'name' => $package->name,
                        'duration_value' => $package->duration_value,
                        'duration_unit' => $package->duration_unit,
                        'price' => $package->price,
                        'device_limit' => $package->device_limit,
                        'upload_speed' => $package->upload_speed,
                        'download_speed' => $package->download_speed,
                        'burst_limit' => $package->burst_limit,
                        'created_by' => $package->created_by,
                        'domain' => Request::host(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
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

        DB::transaction(function () use ($validated, $package) {
            // Update the package
            $package->update($validated);

            // If it's a hotspot package, update corresponding tenant_hotspot record
            if ($validated['type'] === 'hotspot') {
                $tenantId = tenant('id');
                if ($tenantId) {
                    DB::table('tenant_hotspot')
                        ->where('name', $package->name)
                        ->where('tenant_id', $tenantId)
                        ->update([
                            'duration_value' => $package->duration_value,
                            'duration_unit' => $package->duration_unit,
                            'price' => $package->price,
                            'device_limit' => $package->device_limit,
                            'upload_speed' => $package->upload_speed,
                            'download_speed' => $package->download_speed,
                            'burst_limit' => $package->burst_limit,
                            'created_by' => $package->created_by,
                            'updated_at' => now(),
                        ]);
                }
            }
        });

        return redirect()->route('packages.index')
            ->with('success', 'Package updated successfully.');
    }




    //bulk delete action for package lists
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:packages,id',
        ]);

        DB::transaction(function () use ($request) {
            // Get hotspot packages before deletion
            $hotspotPackages = Package::whereIn('id', $request->ids)
                ->where('type', 'hotspot')
                ->get();

            // Delete corresponding tenant_hotspot records
            $tenantId = tenant('id');
            if ($tenantId && $hotspotPackages->isNotEmpty()) {
                $packageNames = $hotspotPackages->pluck('name');
                DB::table('tenant_hotspot')
                    ->whereIn('name', $packageNames)
                    ->where('tenant_id', $tenantId)
                    ->delete();
            }

            // Delete the packages
            Package::whereIn('id', $request->ids)->delete();
        });

        return back()->with('success', 'Selected packages deleted successfully.');
    }

    /**
     * Remove the specified package from storage.
     */
    public function destroy(Package $package)
    {
        DB::transaction(function () use ($package) {
            // If it's a hotspot package, delete corresponding tenant_hotspot record
            if ($package->type === 'hotspot') {
                $tenantId = tenant('id');
                if ($tenantId) {
                    DB::table('tenant_hotspot')
                        ->where('name', $package->name)
                        ->where('tenant_id', $tenantId)
                        ->delete();
                }
            }

            // Delete the package
            $package->delete();
        });

        return redirect()->route('packages.index')
            ->with('success', 'Package deleted successfully.');
    }

    /**
     * Shared validation for store and update.
     */
    protected function validatePackage(Request $request, $id = null)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('packages')->ignore($id)],
            'type' => ['required', 'in:hotspot,pppoe,static'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_value' => ['required', 'integer', 'min:1'],
            'duration_unit' => ['required', 'in:minutes,hours,days,weeks,months'],
            'upload_speed' => ['required', 'numeric', 'min:1'],
            'download_speed' => ['required', 'numeric', 'min:1'],
            'burst_limit' => ['nullable', 'numeric', 'min:0'],
            'device_limit' => ['nullable', 'integer', 'min:1'],
        ];

        // Hotspot-specific rule
        if ($request->input('type') === 'hotspot') {
            $rules['device_limit'] = ['required', 'integer', 'min:1'];
        }

        return $request->validate($rules);
    }
}
