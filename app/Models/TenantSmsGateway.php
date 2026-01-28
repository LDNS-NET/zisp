<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class TenantSmsGateway extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'tenant_id', 
        'provider', 
        'talksasa_api_key',
        'talksasa_sender_id',
        'celcom_partner_id',
        'celcom_api_key',
        'celcom_sender_id',
        'africastalking_username',
        'africastalking_api_key',
        'africastalking_sender_id',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_from_number',
        'is_active', 
        'label', 
        'is_default'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];
    
    protected $hidden = [
        'talksasa_api_key',
        'celcom_partner_id',
        'celcom_api_key',
        'africastalking_api_key',
        'twilio_account_sid',
        'twilio_auth_token',
    ];
    
    // Append flags to indicate if sensitive fields have values (without exposing them)
    protected $appends = [
        'has_talksasa_api_key',
        'has_celcom_partner_id',
        'has_celcom_api_key',
        'has_africastalking_api_key',
        'has_twilio_account_sid',
        'has_twilio_auth_token',
    ];
    
    // Talksasa Mutators/Accessors
    public function setTalksasaApiKeyAttribute($value) 
    { 
        $this->attributes['talksasa_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getTalksasaApiKeyAttribute($value) 
    { 
        return $value ? Crypt::decryptString($value) : null; 
    }
    
    // Celcom Mutators/Accessors
    public function setCelcomPartnerIdAttribute($value) 
    { 
        $this->attributes['celcom_partner_id'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getCelcomPartnerIdAttribute($value) 
    { 
        return $value ? Crypt::decryptString($value) : null; 
    }
    
    public function setCelcomApiKeyAttribute($value) 
    { 
        $this->attributes['celcom_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getCelcomApiKeyAttribute($value) 
    { 
        return $value ? Crypt::decryptString($value) : null; 
    }
    
    // Africa's Talking Mutators/Accessors
    public function setAfricastalkingApiKeyAttribute($value) 
    { 
        $this->attributes['africastalking_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getAfricastalkingApiKeyAttribute($value) 
    { 
        return $value ? Crypt::decryptString($value) : null; 
    }
    
    // Twilio Mutators/Accessors
    public function setTwilioAccountSidAttribute($value) 
    { 
        $this->attributes['twilio_account_sid'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getTwilioAccountSidAttribute($value) 
    { 
        return $value ? Crypt::decryptString($value) : null; 
    }
    
    public function setTwilioAuthTokenAttribute($value) 
    { 
        $this->attributes['twilio_auth_token'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getTwilioAuthTokenAttribute($value) 
    { 
        return $value ? Crypt::decryptString($value) : null; 
    }
    
    
    // Accessors for has_* flags (return boolean without exposing values)
    // These check the raw encrypted values in the database
    public function getHasTalksasaApiKeyAttribute()
    {
        return !empty($this->getAttributeFromArray('talksasa_api_key'));
    }
    
    public function getHasCelcomPartnerIdAttribute()
    {
        return !empty($this->getAttributeFromArray('celcom_partner_id'));
    }
    
    public function getHasCelcomApiKeyAttribute()
    {
        return !empty($this->getAttributeFromArray('celcom_api_key'));
    }
    
    public function getHasAfricastalkingApiKeyAttribute()
    {
        return !empty($this->getAttributeFromArray('africastalking_api_key'));
    }
    
    public function getHasTwilioAccountSidAttribute()
    {
        return !empty($this->getAttributeFromArray('twilio_account_sid'));
    }
    
    public function getHasTwilioAuthTokenAttribute()
    {
        return !empty($this->getAttributeFromArray('twilio_auth_token'));
    }
    
    public function tenant() 
    { 
        return $this->belongsTo(Tenant::class, 'tenant_id'); 
    }
    
    public function scopeActive($query) 
    { 
        return $query->where('is_active', true); 
    }
}
