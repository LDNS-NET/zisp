<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantEquipment;
use App\Models\Tenants\TenantEquipmentUsage;
use App\Models\Tenants\TenantEquipmentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Str;

class TenantEquipmentUsageController extends Controller
{
    public function create()
    {
        $equipment = TenantEquipment::where('quantity', '>', 0)
            ->whereIn('status', ['in_stock', 'assigned'])
            ->get(['id', 'name', 'brand', 'model', 'quantity', 'unit', 'price']);

        return Inertia::render('Equipment/Usage', [
            'equipment' => $equipment
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.equipment_id' => 'required|exists:tenant_equipments,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.details' => 'nullable|string|max:255',
            'details' => 'nullable|string|max:255', // Global details for the session
        ]);

        $referenceNo = 'USAGE-' . strtoupper(Str::random(8));

        DB::transaction(function () use ($validated, $referenceNo) {
            foreach ($validated['items'] as $itemData) {
                $equipment = TenantEquipment::lockForUpdate()->find($itemData['equipment_id']);

                if ($equipment->quantity < $itemData['quantity']) {
                    throw new \Exception("Insufficient stock for {$equipment->name}. Available: {$equipment->quantity}");
                }

                $unitCost = $equipment->price ?? 0;
                $totalCost = $unitCost * $itemData['quantity'];

                // Deduct stock
                $equipment->decrement('quantity', $itemData['quantity']);

                // Record usage
                TenantEquipmentUsage::create([
                    'equipment_id' => $equipment->id,
                    'user_id' => auth()->id(),
                    'quantity' => $itemData['quantity'],
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'reference_no' => $referenceNo,
                    'details' => $itemData['details'] ?? $validated['details'],
                    'used_at' => now(),
                ]);

                // Log the action
                TenantEquipmentLog::create([
                    'equipment_id' => $equipment->id,
                    'action' => 'usage_logged',
                    'old_status' => $equipment->status,
                    'new_status' => $equipment->status,
                    'performed_by' => auth()->id(),
                    'description' => "Used {$itemData['quantity']} {$equipment->unit}. Ref: {$referenceNo}",
                ]);
            }
        });

        return redirect()->route('equipment.index')->with('success', 'Equipment usage logged successfully. Ref: ' . $referenceNo);
    }
}
