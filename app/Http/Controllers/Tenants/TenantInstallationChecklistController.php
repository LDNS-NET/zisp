<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantInstallationChecklist;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantInstallationChecklistController extends Controller
{
    public function index(Request $request)
    {
        $checklists = TenantInstallationChecklist::with('creator:id,name')
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
            })
            ->when($request->installation_type, function ($q) use ($request) {
                $q->where('installation_type', $request->installation_type);
            })
            ->when($request->service_type, function ($q) use ($request) {
                $q->where('service_type', $request->service_type);
            })
            ->when($request->is_active !== null, function ($q) use ($request) {
                $q->where('is_active', $request->is_active);
            })
            ->orderBy('order')
            ->paginate($request->get('per_page', 10));

        return Inertia::render('Tenants/Checklists/Index', [
            'checklists' => $checklists,
            'filters' => [
                'search' => $request->search,
                'installation_type' => $request->installation_type,
                'service_type' => $request->service_type,
                'is_active' => $request->is_active,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'installation_type' => 'required|in:new,relocation,upgrade,repair,maintenance',
            'service_type' => 'required|in:fiber,wireless,hybrid,all',
            'checklist_items' => 'required|array|min:1',
            'checklist_items.*.title' => 'required|string',
            'checklist_items.*.description' => 'nullable|string',
            'checklist_items.*.required' => 'boolean',
            'checklist_items.*.order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        $checklist = TenantInstallationChecklist::create($validated);

        return redirect()->back()->with('success', 'Checklist created successfully.');
    }

    public function update(Request $request, TenantInstallationChecklist $checklist)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'installation_type' => 'required|in:new,relocation,upgrade,repair,maintenance',
            'service_type' => 'required|in:fiber,wireless,hybrid,all',
            'checklist_items' => 'required|array|min:1',
            'checklist_items.*.title' => 'required|string',
            'checklist_items.*.description' => 'nullable|string',
            'checklist_items.*.required' => 'boolean',
            'checklist_items.*.order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        $checklist->update($validated);

        return redirect()->back()->with('success', 'Checklist updated successfully.');
    }

    public function destroy(TenantInstallationChecklist $checklist)
    {
        $checklist->delete();

        return back()->with('success', 'Checklist deleted successfully.');
    }

    public function getForInstallation(Request $request)
    {
        $validated = $request->validate([
            'installation_type' => 'required|in:new,relocation,upgrade,repair,maintenance',
            'service_type' => 'required|in:fiber,wireless,hybrid',
        ]);

        $checklist = TenantInstallationChecklist::active()
            ->forInstallationType($validated['installation_type'])
            ->forServiceType($validated['service_type'])
            ->orderBy('order')
            ->first();

        if (!$checklist) {
            return response()->json([
                'checklist' => null,
                'message' => 'No checklist found for this installation type and service type',
            ]);
        }

        return response()->json([
            'checklist' => [
                'id' => $checklist->id,
                'name' => $checklist->name,
                'description' => $checklist->description,
                'items' => $checklist->checklist_items,
            ],
        ]);
    }

    public function toggleActive(TenantInstallationChecklist $checklist)
    {
        $checklist->update([
            'is_active' => !$checklist->is_active,
        ]);

        return back()->with('success', 'Checklist status updated.');
    }

    public function duplicate(TenantInstallationChecklist $checklist)
    {
        $newChecklist = $checklist->replicate();
        $newChecklist->name = $checklist->name . ' (Copy)';
        $newChecklist->is_default = false;
        $newChecklist->save();

        return back()->with('success', 'Checklist duplicated successfully.');
    }
}
