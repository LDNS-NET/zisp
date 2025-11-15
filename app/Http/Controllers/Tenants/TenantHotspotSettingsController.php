<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\TenantHotspotSetting;
use Inertia\Inertia;

class TenantHotspotSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $tenantId = tenant('id') ?? ($request->user()?->tenant_id);

        if (!$tenantId && app()->environment('local')) {
            $tenantId = Tenant::first()?->id;
        }

        if (!$tenantId) {
            abort(400, 'No tenant context available');
        }

        $setting = TenantHotspotSetting::where('tenant_id', $tenantId)->first();

        // return as plain array for clean JSON props
        return Inertia::render('Settings/Hotspot/Hotspot', [
            'settings' => $setting ? $setting->toArray() : null,
        ]);
    }

    public function update(Request $request)
    {
        $tenantId = tenant('id') ?? ($request->user()?->tenant_id);

        if (!$tenantId && app()->environment('local')) {
            $tenantId = \App\Models\Tenant::first()?->id;
        }

        if (!$tenantId) {
            abort(400, 'No tenant context available');
        }

        $data = $request->validate([
            'portal_template' => 'required|string',
            'logo_url' => 'nullable|url',
            'user_prefix' => 'nullable|string|max:30',
            'prune_inactive_days' => 'nullable|integer|min:1',
        ]);

        $setting = TenantHotspotSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            array_merge($data, [
                'created_by' => auth()->id(),
                // optionally set created_by only when creating:
                'created_by' => TenantHotspotSetting::where('tenant_id', $tenantId)->exists()
                    ? null
                    : auth()->id(),
            ])
        );

        // clear cache if you cache settings elsewhere
        cache()->forget("tenant_hotspot_setting_{$tenantId}");

        // IMPORTANT: redirect to edit route (this causes Inertia to re-fetch props)
        return redirect()->route('settings.hotspot.edit')->with('success', 'Hotspot settings updated.');
    }
}
