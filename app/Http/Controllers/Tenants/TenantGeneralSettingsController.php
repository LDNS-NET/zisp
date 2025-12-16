<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TenantGeneralSetting;
use App\Models\Tenant;
use App\Models\User;
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
        $user = $request->user();
        
        // Get country and currency details from the user
        $country = $user?->country;
        $countryDetails = [
            'code' => $user?->country_code,
            'dialCode' => $user?->dial_code,
        ];
        
        $currency = $user?->currency;
        $currencyDetails = [
            'name' => $user?->currency_name,
        ];

        // Determine current tenant ID
        $tenantId = tenant('id') ?? optional($user)->tenant_id;

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

        // Fill missing data from tenant model and user preferences
        $settings['business_name'] = $settings['business_name'] ?? $tenant?->business_name;
        
        // Convert logo relative path to full URL for display
        if (!empty($settings['logo'])) {
            // If it's not already a full URL, convert it
            if (!str_starts_with($settings['logo'], 'http')) {
                $settings['logo'] = Storage::disk('public')->url($settings['logo']);
            }
        } elseif ($tenant?->logo) {
            // Fallback to tenant logo if available
            if (!str_starts_with($tenant->logo, 'http')) {
                $settings['logo'] = Storage::disk('public')->url($tenant->logo);
            } else {
                $settings['logo'] = $tenant->logo;
            }
        }
        
        // Use user's country and currency (read-only, not editable from settings)
        $userCountryDisplay = $country ? "{$country} ({$user->country_code})" : 'Not set';
        $userCurrencyDisplay = $currency ? "{$currency}" . ($user->currency_name ? " - {$user->currency_name}" : '') : 'Not set';

        return Inertia::render('Settings/General/General', [
            'settings' => $settings,
            'userCountry' => $userCountryDisplay,
            'userCurrency' => $userCurrencyDisplay,
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

            // Online Presence
            'website'        => 'nullable|url|max:255',
            'facebook'       => 'nullable|url|max:255',
            'twitter'        => 'nullable|url|max:255',
            'instagram'      => 'nullable|url|max:255',

            // Preferences
            'business_hours' => 'nullable|string|max:500',
            'timezone'       => 'required|string|max:50',
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
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo'] = $path; // Store relative path, not full URL
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
