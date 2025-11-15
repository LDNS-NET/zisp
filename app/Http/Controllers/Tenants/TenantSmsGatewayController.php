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
    public function edit(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $gateway = TenantSmsGateway::where('tenant_id', $tenantId)->first();

        return Inertia::render('Settings/SMS/SmsGateway', [
            'gateway' => $gateway,
        ]);
    }

    /**
     * Save or update SMS gateway.
     */
    public function update(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $provider = Str::lower(trim($request->input('provider', '')));

        $validated = $request->validate([
            'provider' => ['required', Rule::in([
                'talksasa', 'bytewave', 'africastalking',
                'textsms', 'mobitech', 'twilio', 'custom',
            ])],
            'label' => 'nullable|string|max:100',
            'api_key' => 'nullable|string|max:255',
            'api_secret' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'sender_id' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        TenantSmsGateway::updateOrCreate(
            ['tenant_id' => $tenantId],
            array_merge($validated, [
                'provider' => $provider,
                'is_default' => true,
                'is_active' => $validated['is_active'] ?? true,
            ])
        );

        return back()->with('success', 'SMS gateway settings saved successfully.');
    }

    /**
     * Return the current tenant gateway as JSON.
     */
    public function getGateway(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $gateway = TenantSmsGateway::where('tenant_id', $tenantId)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'gateway' => $gateway,
        ]);
    }
}
