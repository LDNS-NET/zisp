<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TenantSystemSettingsController extends Controller
{
    public function edit()
    {
        $tenantId = Auth::user()->tenant_id;
        
        // Fetch or create system settings
        $systemSettings = TenantSetting::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'category' => 'system'
            ],
            [
                'settings' => [
                    'require_password_for_user_management' => true, // Default: enabled
                ],
                'created_by' => Auth::id(),
            ]
        );

        return Inertia::render('Settings/System/Edit', [
            'settings' => $systemSettings->settings,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'require_password_for_user_management' => 'required|boolean',
        ]);

        $tenantId = Auth::user()->tenant_id;

        $systemSettings = TenantSetting::where('tenant_id', $tenantId)
            ->where('category', 'system')
            ->firstOrFail();

        $systemSettings->update([
            'settings' => $validated,
        ]);

        return back()->with('success', 'System settings updated successfully.');
    }
}
