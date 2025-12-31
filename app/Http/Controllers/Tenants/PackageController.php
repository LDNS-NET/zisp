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

    public function store(Request $request)
    {
        $validated = $this->validatePackage($request);
        $tenantId = tenant()?->id ?? auth()->user()?->tenant_id;

        if (!$tenantId) abort(500, 'Tenant context not resolved');

        try {
            DB::transaction(function () use ($validated, $tenantId) {
                // Generate a unique mikrotik_profile for hotspot packages
                $mikrotikProfile = $validated['type'] === 'hotspot'
                    ? $validated['name'] . '-' . time() // e.g., "Basic-1701234567"
                    : null;

                // Add mikrotik_profile to validated data
                $validated['mikrotik_profile'] = $mikrotikProfile;

                // 1️⃣ Create package in DB
                $package = Package::create([
                    ...$validated,
                    'tenant_id'  => $tenantId,
                    'created_by' => auth()->id(),
                ]);

                // 2️⃣ If hotspot, create TenantHotspot (DB only)
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
                }
            });

            return redirect()->route('packages.index')
                ->with('success', 'Package created successfully.');

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to create package: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Failed to create package. ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update the specified package in storage.
     */
    public function update(Request $request, Package $package)
    {
        $validated = $this->validatePackage($request, $package->id);

        try {
            DB::transaction(function () use ($validated, $package) {
                $oldType = $package->type;
                $package->update($validated);

                // Sync with TenantHotspot if type is hotspot or was hotspot
                if ($package->type === 'hotspot') {
                    TenantHotspot::updateOrCreate(
                        ['package_id' => $package->id],
                        [
                            'tenant_id'       => $package->tenant_id,
                            'name'            => $package->name,
                            'duration_value'  => $package->duration_value,
                            'duration_unit'   => $package->duration_unit,
                            'price'           => $package->price,
                            'device_limit'    => $package->device_limit,
                            'upload_speed'    => $package->upload_speed,
                            'download_speed'  => $package->download_speed,
                            'burst_limit'     => $package->burst_limit,
                        ]
                    );
                } elseif ($oldType === 'hotspot') {
                    // Type changed from hotspot to something else, remove hotspot entry
                    TenantHotspot::where('package_id', $package->id)->delete();
                }
            });

            return redirect()->route('packages.index')
                ->with('success', 'Package updated successfully.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to update package: {$e->getMessage()}");
            return redirect()->back()->with('error', 'Failed to update package. ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified package from storage.
     */
    public function destroy(Package $package)
    {
        try {
            DB::transaction(function () use ($package) {
                // If hotspot, the foreign key onDelete('cascade') will handle TenantHotspot deletion,
                // but let's be explicit if needed or let the DB handle it.
                // Since I added onDelete('cascade') in the migration, it's covered.
                $package->delete();
            });

            return redirect()->route('packages.index')
                ->with('success', 'Package deleted successfully.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to delete package: {$e->getMessage()}");
            return redirect()->back()->with('error', 'Failed to delete package.');
        }
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

        try {
            DB::transaction(function () use ($request) {
                Package::whereIn('id', $request->ids)->delete();
            });

            return back()->with('success', 'Selected packages deleted successfully.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to bulk delete packages: {$e->getMessage()}");
            return back()->with('error', 'Failed to delete selected packages.');
        }
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
