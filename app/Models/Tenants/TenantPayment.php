<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class TenantPayment extends Model
{
    protected $table = "tenant_payments";

    protected $fillable = [
        "user_id",
        "phone",
        "receipt_number",
        "amount",
        "checked",
        "paid_at",
        "created_by",
        "disbursement_type",
        "hotspot_package_id",
        "package_id",
        "status",
        "intasend_reference",
        "intasend_checkout_id",
        "transaction_id",
        "response",
        "currency",
        "payment_method",
        "merchant_request_id",
        "checkout_request_id",
        "mpesa_receipt_number",
        "result_code",
        "result_desc",
        "paystack_reference",
        "paystack_auth_code",
        "flw_transaction_id",
        "flw_ref",
        "paypal_order_id",
        "paypal_payer_id",
        "pesapal_order_tracking_id",
        "pesapal_merchant_reference",
        "momo_reference",
        "momo_transaction_id",
        "airtel_money_reference",
        "airtel_money_transaction_id",
        "tigo_pesa_reference",
        "tigo_pesa_transaction_id",
        "orange_money_reference",
        "orange_money_transaction_id",
        "telebirr_reference",
        "telebirr_transaction_id",
        "evc_plus_reference",
        "evc_plus_transaction_id",
        "wave_reference",
        "wave_transaction_id",
        "ecocash_reference",
        "ecocash_transaction_id",
        "cbe_birr_reference",
        "cbe_birr_transaction_id",
        "zaad_reference",
        "zaad_transaction_id",
        "equitel_reference",
        "equitel_transaction_id",
        "halopesa_reference",
        "halopesa_transaction_id",
        "vodafone_cash_reference",
        "vodafone_cash_transaction_id",
        "fawry_reference",
        "fawry_transaction_id",
        "tenant_id",
        "disbursement_status",
        "disbursement_transaction_id",
        "disbursement_response",
    ];
  protected $casts = [
        'checked' => 'boolean',
        'paid_at' => 'datetime',
        'response' => 'array',
    ];
    public function user()
    {
        return $this->belongsTo(NetworkUser::class, 'user_id');
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }


    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($query) {
            if (tenant()) {
                $query->where('tenant_id', tenant()->id);
            } elseif (auth()->check()) {
                $user = auth()->user();
                if (!$user->is_super_admin && $user->tenant_id) {
                    $query->where('tenant_id', $user->tenant_id);
                }
            } else {
                // Fallback for public routes (hotspot page)
                $host = request()->getHost();
                $subdomain = explode('.', $host)[0];
                $centralDomains = config('tenancy.central_domains', []);
                
                if (!in_array($host, $centralDomains)) {
                    $tenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
                    if ($tenant) {
                        $query->where('tenant_id', $tenant->id);
                    }
                }
            }
        });
    }
}
