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
        'flutterwave_public_key',
        'flutterwave_secret_key',
        'momo_api_user',
        'momo_api_key',
        'momo_subscription_key',
        'momo_env',
        'airtel_client_id',
        'airtel_client_secret',
        'airtel_env',
        'equitel_client_id',
        'equitel_client_secret',
        'tigo_pesa_client_id',
        'tigo_pesa_client_secret',
        'halopesa_client_id',
        'halopesa_client_secret',
        'hormuud_api_key',
        'hormuud_merchant_id',
        'zaad_api_key',
        'zaad_merchant_id',
        'vodafone_cash_client_id',
        'vodafone_cash_client_secret',
        'orange_money_client_id',
        'orange_money_client_secret',
        'telebirr_app_id',
        'telebirr_app_key',
        'telebirr_public_key',
        'cbe_birr_client_id',
        'cbe_birr_client_secret',
        'fawry_merchant_code',
        'fawry_security_key',
        'ecocash_client_id',
        'ecocash_client_secret',
        'wave_api_key',
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

    public function getFlutterwavePublicKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getFlutterwaveSecretKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getMomoApiUserAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getMomoApiKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getMomoSubscriptionKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getAirtelClientIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getAirtelClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getEquitelClientIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getEquitelClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTigoPesaClientIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTigoPesaClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getHalopesaClientIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getHalopesaClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getHormuudApiKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getHormuudMerchantIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getZaadApiKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getZaadMerchantIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getVodafoneCashClientIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getVodafoneCashClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getOrangeMoneyClientIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getOrangeMoneyClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTelebirrAppIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTelebirrAppKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getTelebirrPublicKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getCbeBirrClientIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getCbeBirrClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getFawryMerchantCodeAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getFawrySecurityKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getEcocashClientIdAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getEcocashClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function getWaveApiKeyAttribute($value)
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

    public function setFlutterwavePublicKeyAttribute($value)
    {
        $this->attributes['flutterwave_public_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setFlutterwaveSecretKeyAttribute($value)
    {
        $this->attributes['flutterwave_secret_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setMomoApiUserAttribute($value)
    {
        $this->attributes['momo_api_user'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setMomoApiKeyAttribute($value)
    {
        $this->attributes['momo_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setMomoSubscriptionKeyAttribute($value)
    {
        $this->attributes['momo_subscription_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setAirtelClientIdAttribute($value)
    {
        $this->attributes['airtel_client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setAirtelClientSecretAttribute($value)
    {
        $this->attributes['airtel_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setEquitelClientIdAttribute($value)
    {
        $this->attributes['equitel_client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setEquitelClientSecretAttribute($value)
    {
        $this->attributes['equitel_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTigoPesaClientIdAttribute($value)
    {
        $this->attributes['tigo_pesa_client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTigoPesaClientSecretAttribute($value)
    {
        $this->attributes['tigo_pesa_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setHalopesaClientIdAttribute($value)
    {
        $this->attributes['halopesa_client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setHalopesaClientSecretAttribute($value)
    {
        $this->attributes['halopesa_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setHormuudApiKeyAttribute($value)
    {
        $this->attributes['hormuud_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setHormuudMerchantIdAttribute($value)
    {
        $this->attributes['hormuud_merchant_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setZaadApiKeyAttribute($value)
    {
        $this->attributes['zaad_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setZaadMerchantIdAttribute($value)
    {
        $this->attributes['zaad_merchant_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setVodafoneCashClientIdAttribute($value)
    {
        $this->attributes['vodafone_cash_client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setVodafoneCashClientSecretAttribute($value)
    {
        $this->attributes['vodafone_cash_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setOrangeMoneyClientIdAttribute($value)
    {
        $this->attributes['orange_money_client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setOrangeMoneyClientSecretAttribute($value)
    {
        $this->attributes['orange_money_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTelebirrAppIdAttribute($value)
    {
        $this->attributes['telebirr_app_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTelebirrAppKeyAttribute($value)
    {
        $this->attributes['telebirr_app_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setTelebirrPublicKeyAttribute($value)
    {
        $this->attributes['telebirr_public_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setCbeBirrClientIdAttribute($value)
    {
        $this->attributes['cbe_birr_client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setCbeBirrClientSecretAttribute($value)
    {
        $this->attributes['cbe_birr_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setFawryMerchantCodeAttribute($value)
    {
        $this->attributes['fawry_merchant_code'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setFawrySecurityKeyAttribute($value)
    {
        $this->attributes['fawry_security_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setEcocashClientIdAttribute($value)
    {
        $this->attributes['ecocash_client_id'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setEcocashClientSecretAttribute($value)
    {
        $this->attributes['ecocash_client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setWaveApiKeyAttribute($value)
    {
        $this->attributes['wave_api_key'] = $value ? Crypt::encryptString($value) : null;
    }
}
