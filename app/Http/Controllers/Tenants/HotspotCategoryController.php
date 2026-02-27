<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\HotspotCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HotspotCategoryController extends Controller
{
    public function index()
    {
        $categories = HotspotCategory::orderBy('display_order')->get();
        return Inertia::render('Settings/Hotspot/Categories', [
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_order' => 'nullable|integer',
        ]);

        $tenantId = tenant()?->id ?? auth()->user()?->tenant_id;

        if (!$tenantId) {
            return back()->with('error', 'Tenant context not resolved. Please refresh and try again.');
        }

        HotspotCategory::create([
            ...$validated,
            'tenant_id' => $tenantId,
        ]);

        return back()->with('success', 'Category created successfully');
    }

    public function update(Request $request, HotspotCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_order' => 'nullable|integer',
        ]);

        $category->update($validated);

        return back()->with('success', 'Category updated successfully');
    }

    public function destroy(HotspotCategory $category)
    {
        $category->delete();
        return back()->with('success', 'Category deleted successfully');
    }
}
