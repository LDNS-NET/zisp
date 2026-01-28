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
     * Save or update SMS gateway settings (single row per tenant with all provider configs)
     */
    public function update(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $provider = Str::lower(trim($request->input('provider', '')));

        $validated = $request->validate([
            'provider' => ['required', Rule::in([
                'talksasa', 'celcom', 'africastalking', 'twilio',
            ])],
            'label' => 'nullable|string|max:100',
            // Talksasa fields
            'talksasa_api_key' => 'nullable|string|max:255',
            'talksasa_sender_id' => 'nullable|string|max:255',
            // Celcom fields
            'celcom_partner_id' => 'nullable|string|max:255',
            'celcom_api_key' => 'nullable|string|max:255',
            'celcom_sender_id' => 'nullable|string|max:255',
            // Africa's Talking fields
            'africastalking_username' => 'nullable|string|max:255',
            'africastalking_api_key' => 'nullable|string|max:255',
            'africastalking_sender_id' => 'nullable|string|max:255',
            // Twilio fields
            'twilio_account_sid' => 'nullable|string|max:255',
            'twilio_auth_token' => 'nullable|string|max:255',
            'twilio_from_number' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        // Update or create the gateway row (one row per tenant)
        $gateway = TenantSmsGateway::firstOrNew(['tenant_id' => $tenantId]);
        
        // Update provider and label
        $gateway->provider = $provider;
        $gateway->label = $validated['label'] ?? null;
        $gateway->is_active = $validated['is_active'] ?? true;
        
        // Update non-sensitive fields (always update)
        $gateway->talksasa_sender_id = $validated['talksasa_sender_id'] ?? null;
        $gateway->celcom_sender_id = $validated['celcom_sender_id'] ?? null;
        $gateway->africastalking_username = $validated['africastalking_username'] ?? null;
        $gateway->africastalking_sender_id = $validated['africastalking_sender_id'] ?? null;
        $gateway->twilio_from_number = $validated['twilio_from_number'] ?? null;
        
        // Update sensitive fields ONLY if new non-empty values provided
        if (!empty($validated['talksasa_api_key'])) {
            $gateway->talksasa_api_key = $validated['talksasa_api_key'];
        }
        if (!empty($validated['celcom_partner_id'])) {
            $gateway->celcom_partner_id = $validated['celcom_partner_id'];
        }
        if (!empty($validated['celcom_api_key'])) {
            $gateway->celcom_api_key = $validated['celcom_api_key'];
        }
        if (!empty($validated['africastalking_api_key'])) {
            $gateway->africastalking_api_key = $validated['africastalking_api_key'];
        }
        if (!empty($validated['twilio_account_sid'])) {
            $gateway->twilio_account_sid = $validated['twilio_account_sid'];
        }
        if (!empty($validated['twilio_auth_token'])) {
            $gateway->twilio_auth_token = $validated['twilio_auth_token'];
        }
        
        $gateway->save();

        return back()->with('success', 'SMS gateway settings saved successfully.');
    }

    /**
     * Return the tenant gateway as JSON
     */
    public function getGateway(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $gateway = TenantSmsGateway::where('tenant_id', $tenantId)->first();

        return response()->json([
            'success' => true,
            'gateway' => $gateway,
        ]);
    }
}
