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
        'bank_paybill',
        'phone_number',
        'till_number',
        'paybill_business_number',
        'paybill_account_number',
        'mpesa_consumer_key',
        'mpesa_consumer_secret',
        'mpesa_shortcode',
        'mpesa_passkey',
        'mpesa_env',
        'use_own_api',
        'paystack_public_key',
        'paystack_secret_key',
        'label',
        'is_active',
        'created_by',
        'last_updated_by',
    ];

    // Accessors to decrypt sensitive fields
    public function getBankAccountAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getMpesaConsumerKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getMpesaConsumerSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getMpesaPasskeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getPaystackPublicKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getPaystackSecretKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Mutators to encrypt sensitive fields
    public function setBankAccountAttribute($value)
    {
        $this->attributes['bank_account'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setMpesaConsumerKeyAttribute($value)
    {
        $this->attributes['mpesa_consumer_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setMpesaConsumerSecretAttribute($value)
    {
        $this->attributes['mpesa_consumer_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setMpesaPasskeyAttribute($value)
    {
        $this->attributes['mpesa_passkey'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setPaystackPublicKeyAttribute($value)
    {
        $this->attributes['paystack_public_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setPaystackSecretKeyAttribute($value)
    {
        $this->attributes['paystack_secret_key'] = $value ? Crypt::encryptString($value) : null;
    }
}
