<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\TenantSmsGateway;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TenantSmsGatewayController extends Controller
{
    protected function getTenantId(Request $request): string
    {
        $tenantId = tenant('id') ?? optional($request->user())->tenant_id;

        if (!$tenantId && app()->environment('local')) {
            $tenantId = Tenant::first()?->id;
        }

        abort_if(!$tenantId, 400, 'No tenant context available.');

        return $tenantId;
    }

    /**
     * Show SMS gateway settings.
     */
    /**
     * Show SMS gateway settings.
     */
    public function edit(Request $request)
    {
        // This is still used by the route to render the page component
        // The data loading now happens via JSON endpoint, but we can pre-load if needed
        return Inertia::render('Settings/SMS/SmsGateway');
    }

    /**
     * Save or update SMS gateway per provider.
     */
    public function update(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $provider = Str::lower(trim($request->input('provider', '')));

        $validated = $request->validate([
            'provider' => ['required', Rule::in([
                'talksasa', 'bytewave', 'africastalking',
                'textsms', 'mobitech', 'twilio', 'custom', 'celcom',
            ])],
            'label' => 'nullable|string|max:100',
            'api_key' => 'nullable|string|max:255',
            'api_secret' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'sender_id' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $shouldBeActive = $validated['is_active'] ?? false;

        // If setting this gateway as active, deactivate all others first
        if ($shouldBeActive) {
            TenantSmsGateway::where('tenant_id', $tenantId)
                ->update(['is_active' => false]);
        }
        
        // Update or Create the specific provider setting
        TenantSmsGateway::updateOrCreate(
            [
                'tenant_id' => $tenantId, 
                'provider' => $provider
            ],
            array_merge($validated, [
                'provider' => $provider,
                'is_active' => $shouldBeActive,
                // Ensure at least one is default if system logic requires, 
                // but usually is_active is what matters. 
                // We'll treat talksasa as system default fallback logic-wise.
            ])
        );

        return back()->with('success', 'SMS gateway settings saved successfully.');
    }

    /**
     * Return all tenant gateways as JSON.
     */
    public function index(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $gateways = TenantSmsGateway::where('tenant_id', $tenantId)
            ->get();

        return response()->json([
            'success' => true,
            'gateways' => $gateways,
        ]);
    }
}
