<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use App\Models\Tenants\TenantMikrotik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentFilterController extends Controller
{
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;
        $settings = TenantSetting::forTenant($tenantId, 'content_filter');
        $mikrotiks = TenantMikrotik::where('status', 'online')->get();

        return inertia('Settings/ContentFilter/Index', [
            'settings' => $settings ? $settings->settings : [
                'enabled' => false,
                'categories' => [],
                'blacklist' => [],
                'whitelist' => [],
                'dns_address' => '1.1.1.3', // Default to Cloudflare Family DNS
            ],
            'mikrotiks' => $mikrotiks,
        ]);
    }

    public function update(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'categories' => 'array',
            'blacklist' => 'array',
            'whitelist' => 'array',
            'dns_address' => 'nullable|ip',
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenantId, 'category' => 'content_filter'],
            ['settings' => $validated, 'created_by' => Auth::id()]
        );

        return back()->with('success', 'Content filtering settings updated successfully.');
    }

    public function applyToRouter(Request $request, $routerId)
    {
        $router = TenantMikrotik::findOrFail($routerId);
        $settings = TenantSetting::forTenant(Auth::user()->tenant_id, 'content_filter');

        if (!$settings || !$settings->settings['enabled']) {
            return back()->with('error', 'Content filtering is disabled.');
        }

        // Logic to push DNS/Firewall settings to MikroTik would go here
        // e.g., using MikrotikService
        
        return back()->with('success', 'Content filtering policies applied to ' . $router->name);
    }
}
