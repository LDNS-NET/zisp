<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

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
        try {
            $validated = $this->validatePackage($request);

            $validated['created_by'] = auth()->id();

            Package::create($validated);

            return redirect()->route('packages.index')
                ->with('success', 'Package created successfully.');
        } catch (\Exception $e) {
            \Log::error('Package creation failed: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
                'tenant_id' => tenant('id', 'unknown'),
            ]);

            return redirect()->route('packages.index')
                ->with('error', 'Failed to create package. Please check the logs for details.');
        }
    }



    /**
     * Update the specified package in storage.
     */
    public function update(Request $request, Package $package)
    {
        try {
            $validated = $this->validatePackage($request, $package->id);

            $package->update($validated);

            return redirect()->route('packages.index')
                ->with('success', 'Package updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Package update failed: ' . $e->getMessage(), [
                'package_id' => $package->id,
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
                'tenant_id' => tenant('id', 'unknown'),
            ]);

            return redirect()->route('packages.index')
                ->with('error', 'Failed to update package. Please check the logs for details.');
        }
    }




    //bulk delete action for package lists
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:packages,id',
        ]);

        try {
            $deletedCount = Package::whereIn('id', $request->ids)->delete();

            return back()->with('success', "Selected {$deletedCount} packages deleted successfully.");
        } catch (\Exception $e) {
            \Log::error('Bulk package deletion failed: ' . $e->getMessage(), [
                'ids' => $request->ids,
                'user_id' => auth()->id(),
                'tenant_id' => tenant('id', 'unknown'),
            ]);

            return back()->with('error', 'Failed to delete selected packages. They may be in use by active users or vouchers.');
        }
    }

    /**
     * Remove the specified package from storage.
     */
    public function destroy(Package $package)
    {
        try {
            $package->delete();

            return redirect()->route('packages.index')
                ->with('success', 'Package deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Package deletion failed: ' . $e->getMessage(), [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'user_id' => auth()->id(),
                'tenant_id' => tenant('id', 'unknown'),
            ]);

            return redirect()->route('packages.index')
                ->with('error', 'Failed to delete package. It may be in use by active users or vouchers.');
        }
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
