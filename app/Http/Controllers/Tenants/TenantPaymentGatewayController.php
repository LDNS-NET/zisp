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

        $tenant = Tenant::find($tenantId);
        $gateways = Cache::remember("tenant_payment_gateways_{$tenantId}", 60, function () use ($tenantId) {
            return TenantPaymentGateway::where('tenant_id', $tenantId)->get();
        });

        $countryCode = $tenant?->country_code ?? 'KE';

        // Get disabled gateways for this country from SuperAdmin settings
        $disabledGateways = \App\Models\CountryGatewaySetting::where('country_code', $countryCode)
            ->where('is_active', false)
            ->pluck('gateway')
            ->toArray();
        
        return Inertia::render('Settings/Payment/Payment', [
            'gateways' => $gateways,
            'phone_number' => $request->user()?->phone ?? '',
            'country' => $countryCode,
            'disabled_gateways' => $disabledGateways,
        ]);
    }

    /**
     * Update or create the tenant’s payment gateway record.
     */
    public function update(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        $validated = $request->validate([
            'provider' => 'required|in:intasend,mpesa,tinypesa,paystack,flutterwave,momo,airtel_money,bank,custom',
            'payout_method' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_paybill' => 'nullable|string|max:20',
            'phone_number' => 'nullable|string|max:20',
            'till_number' => 'nullable|string|max:20',
            'paybill_business_number' => 'nullable|string|max:20',
            'paybill_account_number' => 'nullable|string|max:20',
            'mpesa_consumer_key' => 'nullable|string',
            'mpesa_consumer_secret' => 'nullable|string',
            'mpesa_shortcode' => 'nullable|string|max:20',
            'mpesa_passkey' => 'nullable|string',
            'mpesa_env' => 'nullable|in:sandbox,production',
            'paystack_public_key' => 'nullable|string',
            'paystack_secret_key' => 'nullable|string',
            'flutterwave_public_key' => 'nullable|string',
            'flutterwave_secret_key' => 'nullable|string',
            'momo_api_user' => 'nullable|string',
            'momo_api_key' => 'nullable|string',
            'momo_subscription_key' => 'nullable|string',
            'momo_env' => 'nullable|in:sandbox,production',
            'airtel_client_id' => 'nullable|string',
            'airtel_client_secret' => 'nullable|string',
            'airtel_env' => 'nullable|in:sandbox,production',
            'equitel_client_id' => 'nullable|string',
            'equitel_client_secret' => 'nullable|string',
            'tigo_pesa_client_id' => 'nullable|string',
            'tigo_pesa_client_secret' => 'nullable|string',
            'halopesa_client_id' => 'nullable|string',
            'halopesa_client_secret' => 'nullable|string',
            'hormuud_api_key' => 'nullable|string',
            'hormuud_merchant_id' => 'nullable|string',
            'zaad_api_key' => 'nullable|string',
            'zaad_merchant_id' => 'nullable|string',
            'vodafone_cash_client_id' => 'nullable|string',
            'vodafone_cash_client_secret' => 'nullable|string',
            'orange_money_client_id' => 'nullable|string',
            'orange_money_client_secret' => 'nullable|string',
            'telebirr_app_id' => 'nullable|string',
            'telebirr_app_key' => 'nullable|string',
            'telebirr_public_key' => 'nullable|string',
            'cbe_birr_client_id' => 'nullable|string',
            'cbe_birr_client_secret' => 'nullable|string',
            'fawry_merchant_code' => 'nullable|string',
            'fawry_security_key' => 'nullable|string',
            'ecocash_client_id' => 'nullable|string',
            'ecocash_client_secret' => 'nullable|string',
            'tinypesa_api_key' => 'nullable|string',
            'tinypesa_account_number' => 'nullable|string',
            'wave_api_key' => 'nullable|string',
            'use_own_api' => 'nullable|boolean',
            'label' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        // ✅ Ensure one record per provider per tenant
        $isActive = $validated['is_active'] ?? true;

        if ($isActive) {
            // Deactivate all other gateways for this tenant
            TenantPaymentGateway::where('tenant_id', $tenantId)
                ->where('provider', '!=', $validated['provider'])
                ->update(['is_active' => false]);
        }

        TenantPaymentGateway::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $validated['provider'],
            ],
            array_merge($validated, [
                'created_by' => auth()->id(),
                'last_updated_by' => auth()->id(),
                'is_active' => $isActive,
            ])
        );

        Cache::forget("tenant_payment_gateways_{$tenantId}");

        return back()->with('success', 'Payment gateway updated successfully.');
    }
}
