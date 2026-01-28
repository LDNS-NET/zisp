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
        'advanta_partner_id',
        'advanta_api_key',
        'advanta_shortcode',
        'is_active', 
        'label', 
        'is_default'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
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
    
    // Advanta SMS Mutators/Accessors
    public function setAdvantaPartnerIdAttribute($value) 
    { 
        $this->attributes['advanta_partner_id'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getAdvantaPartnerIdAttribute($value) 
    { 
        return $value ? Crypt::decryptString($value) : null; 
    }
    
    public function setAdvantaApiKeyAttribute($value) 
    { 
        $this->attributes['advanta_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getAdvantaApiKeyAttribute($value) 
    { 
        return $value ? Crypt::decryptString($value) : null; 
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
