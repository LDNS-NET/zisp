<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemSettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::all()->groupBy('group');
        return Inertia::render('SuperAdmin/SystemSettings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            $setting = SystemSetting::where('key', $key)->first();
            if ($setting) {
                $setting->value = $value;
                $setting->save();
            }
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
