<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CountryGatewaySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        $settings = CountryGatewaySetting::all();

        return Inertia::render('SuperAdmin/PaymentGateways/Index', [
            'settings' => $settings,
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'country_code' => 'required|string',
            'gateway' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        CountryGatewaySetting::updateOrCreate(
            [
                'country_code' => $request->country_code,
                'gateway' => $request->gateway,
            ],
            [
                'is_active' => $request->is_active,
            ]
        );

        return back()->with('success', 'Gateway setting updated successfully.');
    }
}
