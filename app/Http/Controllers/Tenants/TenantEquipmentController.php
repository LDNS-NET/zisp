<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantEquipment;
use App\Models\Tenants\TenantEquipmentLog;
use App\Models\Tenants\NetworkUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class TenantEquipmentController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        
        $equipment = TenantEquipment::with(['assignedUser', 'creator'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search}%")
                          ->orWhere('brand', 'like', "%{$request->search}%")
                          ->orWhere('type', 'like', "%{$request->search}%")
                          ->orWhere('serial_number', 'like', "%{$request->search}%")
                          ->orWhere('mac_address', 'like', "%{$request->search}%")
                          ->orWhere('model', 'like', "%{$request->search}%");
                });
            })
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->latest()
            ->paginate($request->get('per_page', 20))
            ->withQueryString();
            
        $totalPrice = (float) TenantEquipment::where('tenant_id', $tenantId)->sum('total_price');

        return Inertia::render('Equipment/Index', [
            'equipment' => $equipment,
            'totalPrice' => $totalPrice,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255|unique:tenant_equipments',
            'mac_address' => 'nullable|string|max:255|unique:tenant_equipments',
            'status' => 'required|in:in_stock,assigned,faulty,retired,lost',
            'condition' => 'required|in:new,used,refurbished',
            'quantity' => 'required|integer|min:0',
            'location' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'total_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'warranty_expiry' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function() use ($validated) {
            $equipment = TenantEquipment::create($validated);

            TenantEquipmentLog::create([
                'equipment_id' => $equipment->id,
                'action' => 'created',
                'new_status' => $equipment->status,
                'performed_by' => auth()->id(),
                'description' => 'Initial inventory entry',
            ]);
        });

        return redirect()->back()->with('success', 'Equipment added successfully.');
    }

    protected function authorizeAccess(TenantEquipment $equipment): void
    {
        // Optionally restrict by user, but not required for tenant DB isolation
    }

    public function update(Request $request, TenantEquipment $equipment)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255|unique:tenant_equipments,serial_number,' . $equipment->id,
            'mac_address' => 'nullable|string|max:255|unique:tenant_equipments,mac_address,' . $equipment->id,
            'status' => 'required|in:in_stock,assigned,faulty,retired,lost',
            'condition' => 'required|in:new,used,refurbished',
            'quantity' => 'required|integer|min:0',
            'location' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'total_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'warranty_expiry' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $oldStatus = $equipment->status;

        DB::transaction(function() use ($equipment, $validated, $oldStatus) {
            $equipment->update($validated);

            if ($oldStatus !== $equipment->status) {
                TenantEquipmentLog::create([
                    'equipment_id' => $equipment->id,
                    'action' => 'status_change',
                    'old_status' => $oldStatus,
                    'new_status' => $equipment->status,
                    'performed_by' => auth()->id(),
                    'description' => 'Manual status update',
                ]);
            }
        });

        return redirect()->back()->with('success', 'Equipment updated.');
    }

    public function destroy(TenantEquipment $equipment)
    {
        $equipment->delete();

        return back()->with('success', 'Equipment deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tenant_equipments,id',
        ]);

        TenantEquipment::whereIn('id', $request->ids)->delete();

        return back()->with('success', 'Selected Equipment deleted successfully.');
    }

    /**
     * Assign equipment to a user
     */
    public function assign(Request $request, TenantEquipment $equipment)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:network_users,id',
            'notes' => 'nullable|string',
        ]);

        $user = NetworkUser::findOrFail($validated['user_id']);
        $oldStatus = $equipment->status;

        DB::transaction(function() use ($equipment, $user, $validated, $oldStatus) {
            $equipment->update([
                'status' => 'assigned',
                'assigned_user_id' => $user->id,
                'assigned_to' => $user->full_name ?? $user->username,
            ]);

            TenantEquipmentLog::create([
                'equipment_id' => $equipment->id,
                'action' => 'assigned',
                'old_status' => $oldStatus,
                'new_status' => 'assigned',
                'performed_by' => auth()->id(),
                'description' => "Assigned to user: {$user->username}. " . ($validated['notes'] ?? ''),
            ]);
        });

        return redirect()->back()->with('success', 'Equipment assigned successfully.');
    }

    /**
     * Release equipment from a user
     */
    public function release(Request $request, TenantEquipment $equipment)
    {
        $oldStatus = $equipment->status;
        $user = $equipment->assignedUser;

        DB::transaction(function() use ($equipment, $user, $oldStatus) {
            $equipment->update([
                'status' => 'in_stock',
                'assigned_user_id' => null,
                'assigned_to' => null,
            ]);

            TenantEquipmentLog::create([
                'equipment_id' => $equipment->id,
                'action' => 'unassigned',
                'old_status' => $oldStatus,
                'new_status' => 'in_stock',
                'performed_by' => auth()->id(),
                'description' => "Released from user: " . ($user ? $user->username : 'Unknown'),
            ]);
        });

        return redirect()->back()->with('success', 'Equipment released back to stock.');
    }

    /**
     * Get equipment history
     */
    public function history(TenantEquipment $equipment)
    {
        $history = $equipment->logs()->with('performer')->get();
        return response()->json($history);
    }

    /**
     * Search users for assignment
     */
    public function searchUsers(Request $request)
    {
        $search = $request->get('q');
        $users = NetworkUser::where('username', 'like', "%{$search}%")
            ->orWhere('full_name', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->limit(10)
            ->get(['id', 'username', 'full_name', 'phone']);

        return response()->json($users);
    }

    /**
     * Log equipment usage
     */
    public function logUsage(Request $request, TenantEquipment $equipment)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:' . $equipment->quantity,
            'details' => 'nullable|string|max:255',
        ]);

        DB::transaction(function() use ($equipment, $validated) {
            $equipment->decrement('quantity', $validated['quantity']);

            \App\Models\Tenants\TenantEquipmentUsage::create([
                'equipment_id' => $equipment->id,
                'user_id' => auth()->id(),
                'quantity' => $validated['quantity'],
                'details' => $validated['details'],
                'used_at' => now(),
            ]);

            TenantEquipmentLog::create([
                'equipment_id' => $equipment->id,
                'action' => 'usage_logged',
                'old_status' => $equipment->status,
                'new_status' => $equipment->status,
                'performed_by' => auth()->id(),
                'description' => "Used {$validated['quantity']} units. Details: " . ($validated['details'] ?? 'None'),
            ]);
        });

        return redirect()->back()->with('success', 'Usage logged and stock updated.');
    }

    /**
     * Get usage history specifically
     */
    public function usages(TenantEquipment $equipment)
    {
        $usages = $equipment->usages()->with('user')->get();
        return response()->json($usages);
    }
}
