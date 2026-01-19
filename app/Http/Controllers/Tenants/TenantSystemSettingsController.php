<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TenantSetting;
use Inertia\Inertia;

class TenantSystemSettingsController extends Controller
{
    /**
     * Display the system settings form.
     */
    public function edit(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        
        // Fetch existing system settings or defaults
        $setting = TenantSetting::forTenant($tenantId, 'system');
        $settings = $setting ? $setting->settings : [];

        // Define defaults
        $defaults = [
            'require_password_for_user_management' => true,
        ];

        // Merge defaults with saved settings
        $settings = array_merge($defaults, $settings);

        return Inertia::render('Settings/System/Edit', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update the system settings.
     */
    public function update(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'require_password_for_user_management' => 'required|boolean',
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenantId, 'category' => 'system'],
            [
                'settings' => $validated,
                'created_by' => auth()->id(),
            ]
        );

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }
}
