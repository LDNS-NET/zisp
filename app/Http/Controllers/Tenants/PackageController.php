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
            \Log::info('Package store method called', [
                'request_data' => $request->all(),
                'auth_check' => auth()->check(),
                'user_id' => auth()->id(),
            ]);

            $validated = $this->validatePackage($request);

            $validated['created_by'] = auth()->id();

            $package = Package::create($validated);

            \Log::info('Package created successfully', [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'package_type' => $package->type,
            ]);

            // Sync to tenant_hotspot table if it's a hotspot package
            if ($package->type === 'hotspot') {
                \Log::info('Processing hotspot package sync', [
                    'package_id' => $package->id,
                    'package_type' => $package->type,
                ]);

                try {
                    // Try to get tenant from context first, then fallback to authenticated user
                    $tenantId = tenant('id');
                    
                    if (!$tenantId && auth()->check() && auth()->user()->tenant_id) {
                        $tenantId = auth()->user()->tenant_id;
                        \Log::info('Using tenant from authenticated user', [
                            'tenant_id' => $tenantId,
                            'user_id' => auth()->id(),
                        ]);
                    }
                    
                    if (!$tenantId) {
                        \Log::error('No tenant context available', [
                            'package_id' => $package->id,
                            'package_name' => $package->name,
                            'auth_check' => auth()->check(),
                            'user_tenant_id' => auth()->user()->tenant_id ?? null,
                        ]);
                        
                        // Continue without TenantHotspot sync but don't fail the package creation
                        return redirect()->route('packages.index')
                            ->with('success', 'Package created successfully (TenantHotspot sync skipped - no tenant context).');
                    }
                    
                    \Log::info('Creating TenantHotspot record', [
                        'tenant_id' => $tenantId,
                        'package_name' => $package->name,
                    ]);
                    
                    $tenantHotspot = \App\Models\Tenants\TenantHotspot::create([
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
                        'domain' => request()->getHost(),
                    ]);
                    
                    \Log::info('TenantHotspot record created successfully', [
                        'tenant_hotspot_id' => $tenantHotspot->id,
                        'package_id' => $package->id,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to create related TenantHotspot: ' . $e->getMessage(), [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'tenant_id' => $tenantId ?? 'unknown',
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            return redirect()->route('packages.index')
                ->with('success', 'Package created successfully.');
        } catch (\Exception $e) {
            \Log::error('Package creation failed: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
                'tenant_id' => tenant('id', 'unknown'),
                'trace' => $e->getTraceAsString(),
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

            // Sync to tenant_hotspot table if it's a hotspot package
            if ($package->type === 'hotspot') {
                try {
                    // Try to get tenant from context first, then fallback to authenticated user
                    $tenantId = tenant('id');
                    
                    if (!$tenantId && auth()->check() && auth()->user()->tenant_id) {
                        $tenantId = auth()->user()->tenant_id;
                    }
                    
                    if (!$tenantId) {
                        \Log::warning('No tenant context available for TenantHotspot update', [
                            'package_id' => $package->id,
                            'package_name' => $package->name,
                        ]);
                        // Continue without TenantHotspot sync
                        return redirect()->route('packages.index')
                            ->with('success', 'Package updated successfully (TenantHotspot sync skipped - no tenant context).');
                    }
                    
                    \App\Models\Tenants\TenantHotspot::where('name', $package->name)
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
                        ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to update related TenantHotspot: ' . $e->getMessage(), [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'tenant_id' => $tenantId ?? 'unknown',
                    ]);
                }
            }

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
            // Get the packages to delete before deletion for TenantHotspot cleanup
            $packages = Package::whereIn('id', $request->ids)->get();
            
            // Delete related TenantHotspot records for hotspot packages
            foreach ($packages as $package) {
                if ($package->type === 'hotspot') {
                    try {
                        \App\Models\Tenants\TenantHotspot::where('name', $package->name)
                            ->where('tenant_id', tenant('id'))
                            ->delete();
                    } catch (\Exception $e) {
                        \Log::error('Failed to delete related TenantHotspot during bulk delete: ' . $e->getMessage(), [
                            'package_id' => $package->id,
                            'package_name' => $package->name,
                            'tenant_id' => tenant('id', 'unknown'),
                        ]);
                    }
                }
            }

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
            // Delete related TenantHotspot record if it's a hotspot package
            if ($package->type === 'hotspot') {
                try {
                    \App\Models\Tenants\TenantHotspot::where('name', $package->name)
                        ->where('tenant_id', tenant('id'))
                        ->delete();
                } catch (\Exception $e) {
                    \Log::error('Failed to delete related TenantHotspot: ' . $e->getMessage(), [
                        'package_id' => $package->id,
                        'package_name' => $package->name,
                        'tenant_id' => tenant('id', 'unknown'),
                    ]);
                }
            }

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
