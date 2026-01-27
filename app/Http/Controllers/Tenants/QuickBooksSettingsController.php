<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use Inertia\Inertia;
use Illuminate\Http\Request;

class QuickBooksSettingsController extends Controller
{
    /**
     * Show the QuickBooks settings page.
     */
    public function edit()
    {
        $tenantId = tenant('id');
        $setting = TenantSetting::where('tenant_id', $tenantId)
            ->where('category', 'quickbooks')
            ->first();

        return Inertia::render('Settings/QuickBooks', [
            'connected' => !empty($setting?->settings['access_token']),
            'settings' => $setting?->settings ?? [],
        ]);
    }
}
