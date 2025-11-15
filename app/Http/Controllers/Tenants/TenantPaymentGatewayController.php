<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TenantPaymentGateway;
use App\Models\Tenant;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;

class TenantPaymentGatewayController extends Controller
{
    /**
     * Resolve the current tenant ID, including fallback for local dev.
     */
    protected function resolveTenantId(Request $request): string
    {
        $tenantId = tenant('id') ?? $request->user()?->tenant_id;

        if (!$tenantId && app()->environment('local')) {
            $tenantId = Tenant::first()?->id;
        }

        abort_if(!$tenantId, 400, 'No tenant context available.');

        return $tenantId;
    }

    /**
     * Show the tenant’s payment gateway configuration page.
     */
    public function edit(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        $gateways = Cache::remember("tenant_payment_gateways_{$tenantId}", 60, function () use ($tenantId) {
            return TenantPaymentGateway::where('tenant_id', $tenantId)->get();
        });

        return Inertia::render('Settings/Payment/Payment', [
            'gateways' => $gateways,
            'phone_number' => $request->user()?->phone ?? '',
        ]);
    }

    /**
     * Update or create the tenant’s payment gateway record.
     */
    public function update(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        $validated = $request->validate([
            'provider' => 'required|in:intasend,mpesa,bank,custom',
            'payout_method' => 'required|in:bank,mpesa_phone,till,paybill',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:20',
            'till_number' => 'nullable|string|max:20',
            'paybill_business_number' => 'nullable|string|max:20',
            'paybill_account_number' => 'nullable|string|max:20',
            'label' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // ✅ Ensure one record per tenant + provider + payout method
        TenantPaymentGateway::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $validated['provider'],
                'payout_method' => $validated['payout_method'],
            ],
            array_merge($validated, [
                'created_by' => auth()->id(),
                'last_updated_by' => auth()->id(),
                'is_active' => $validated['is_active'] ?? true,
            ])
        );

        Cache::forget("tenant_payment_gateways_{$tenantId}");

        return back()->with('success', 'Payment gateway updated successfully.');
    }
}
