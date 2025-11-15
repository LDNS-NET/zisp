<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TenantGeneralSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class TenantGeneralSettingsController extends Controller
{
    /**
     * Display the general settings form.
     */
    public function edit(Request $request)
    {
        // Determine current tenant ID
        $tenantId = tenant('id') ?? optional($request->user())->tenant_id;

        if (!$tenantId && app()->environment('local')) {
            $tenantId = Tenant::first()?->id;
        }

        if (!$tenantId) {
            abort(400, 'No tenant context available');
        }

        // Cache tenant settings safely for 1 minute (60 seconds)
        $setting = Cache::remember("tenant_general_setting_{$tenantId}", 60, function () use ($tenantId) {
            return TenantGeneralSetting::where('tenant_id', $tenantId)->first();
        });

        $tenant = Tenant::find($tenantId);
        $settings = $setting ? $setting->toArray() : [];

        // Fill missing data from tenant model
        $settings['business_name'] = $settings['business_name'] ?? $tenant?->business_name;
        $settings['logo'] = $settings['logo'] ?? $tenant?->logo;

        return Inertia::render('Settings/General/General', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update the general settings.
     */
    public function update(Request $request)
    {
        $tenantId = tenant('id') ?? optional($request->user())->tenant_id;

        if (!$tenantId && app()->environment('local')) {
            $tenantId = Tenant::first()?->id;
        }

        if (!$tenantId) {
            abort(400, 'No tenant context available');
        }

        $validator = Validator::make($request->all(), [
            // Business Information
            'business_name'  => 'nullable|string|max:255',
            'business_type'  => 'required|in:isp,wisp,telecom,other',

            // Contact Information
            'support_email'  => 'nullable|email|max:255',
            'support_phone'  => 'nullable|string|max:20',
            'whatsapp'       => 'nullable|string|max:20',

            // Address
            'address'        => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
            'postal_code'    => 'nullable|string|max:20',
            'country'        => 'nullable|string|max:100',

            // Online Presence
            'website'        => 'nullable|url|max:255',
            'facebook'       => 'nullable|url|max:255',
            'twitter'        => 'nullable|url|max:255',
            'instagram'      => 'nullable|url|max:255',

            // Preferences
            'business_hours' => 'nullable|string|max:500',
            'timezone'       => 'required|string|max:50',
            'currency'       => 'required|string|max:10',
            'language'       => 'required|string|max:10',

            // Branding
            'logo'           => 'nullable|file|image|max:2048',
            'theme'          => 'nullable|in:light,dark,system',
            'remove_logo'    => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        // Handle logo upload/removal
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('public/logos');
            $data['logo'] = Storage::url($path);
        } elseif (!empty($data['remove_logo'])) {
            $data['logo'] = null;
        }

        // Save settings
        TenantGeneralSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            array_merge($data, [
                'created_by' => auth()->id(),
                'last_updated_by' => auth()->id(),
            ])
        );

        // Clear cache
        Cache::forget("tenant_general_setting_{$tenantId}");

        // Sync with Tenant model
        if ($tenant = Tenant::find($tenantId)) {
            if (!empty($data['business_name'])) {
                $tenant->business_name = $data['business_name'];
            }

            if (array_key_exists('logo', $data)) {
                $tenant->logo = $data['logo'];
            }

            $tenant->save();
        }

        return redirect()->back()->with('success', 'General settings updated successfully.');
    }
}
