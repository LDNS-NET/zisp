<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PricingPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PricingPlanController extends Controller
{
    public function index()
    {
        $plans = PricingPlan::all();
        return Inertia::render('SuperAdmin/PricingPlans/Index', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_code' => 'required|string|unique:pricing_plans,country_code,' . $request->id,
            'currency' => 'required|string|max:3',
            'pppoe_price_per_month' => 'required|numeric|min:0',
            'hotspot_price_percentage' => 'required|numeric|min:0|max:100',
            'minimum_pay' => 'required|numeric|min:0',
            'exchange_rate' => 'required|numeric|min:0.00000001',
            'is_active' => 'boolean',
        ]);

        PricingPlan::updateOrCreate(
            ['id' => $request->id],
            $validated
        );

        return back()->with('success', 'Pricing plan saved successfully.');
    }

    public function destroy($id)
    {
        PricingPlan::findOrFail($id)->delete();
        return back()->with('success', 'Pricing plan deleted successfully.');
    }
}
