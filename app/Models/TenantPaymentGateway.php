<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class TenantPaymentGateway extends Model

{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'provider',
        'payout_method',
        'bank_name',
        'bank_account',
        'phone_number',
        'till_number',
        'paybill_business_number',
        'paybill_account_number',
        'label',
        'is_active',
        'created_by',
        'last_updated_by',
    ];

    // Accessor to decrypt sensitive fields
    public function getBankAccountAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Mutator to encrypt sensitive fields
    public function setBankAccountAttribute($value)
    {
        $this->attributes['bank_account'] = $value ? Crypt::encryptString($value) : null;
    }
}
