<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CountrySmsSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SmsGatewayController extends Controller
{
    public function index()
    {
        $settings = CountrySmsSetting::all();

        return Inertia::render('SuperAdmin/SmsGateways/Index', [
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

        CountrySmsSetting::updateOrCreate(
            [
                'country_code' => $request->country_code,
                'gateway' => $request->gateway,
            ],
            [
                'is_active' => $request->is_active,
            ]
        );

        return back()->with('success', 'SMS gateway setting updated successfully.');
    }
}
