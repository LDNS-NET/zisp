<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class TenantSmsGateway extends Model
{
    use SoftDeletes;

    /**
     * Always use the central DB connection for this model.
     * This is critical for queued jobs where the DB context may have been
     * switched to a tenant DB by QueueTenancyBootstrapper.
     * The central_connection defaults to the app's default DB if not explicitly set.
     */
    protected $connection;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        // Dynamically resolve the central connection so config changes are respected
        $this->connection = config('tenancy.database.central_connection', config('database.default'));
    }

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
        'africastalking_environment',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_from_number',
        'advanta_partner_id',
        'advanta_api_key',
        'advanta_shortcode',
        'bulksms_username',
        'bulksms_password',
        'clicksend_username',
        'clicksend_api_key',
        'infobip_api_key',
        'infobip_base_url',
        'infobip_sender_id',
        'is_active', 
        'label', 
        'is_default'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Safely decrypt an attribute value.
     * If the ciphertext was encrypted with an old APP_KEY (or is otherwise invalid),
     * we log the error and return null instead of throwing, so the UI can still load
     * and the user can re-enter credentials.
     */
    protected function safeDecrypt(?string $value, string $attribute)
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            Log::warning('TenantSmsGateway: failed to decrypt attribute', [
                'attribute' => $attribute,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
    
    // Talksasa Mutators/Accessors
    public function setTalksasaApiKeyAttribute($value) 
    { 
        $this->attributes['talksasa_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getTalksasaApiKeyAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'talksasa_api_key');
    }
    
    // Celcom Mutators/Accessors
    public function setCelcomPartnerIdAttribute($value) 
    { 
        $this->attributes['celcom_partner_id'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getCelcomPartnerIdAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'celcom_partner_id');
    }
    
    public function setCelcomApiKeyAttribute($value) 
    { 
        $this->attributes['celcom_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getCelcomApiKeyAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'celcom_api_key');
    }
    
    // Africa's Talking Mutators/Accessors
    public function setAfricastalkingApiKeyAttribute($value) 
    { 
        $this->attributes['africastalking_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getAfricastalkingApiKeyAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'africastalking_api_key');
    }
    
    // Twilio Mutators/Accessors
    public function setTwilioAccountSidAttribute($value) 
    { 
        $this->attributes['twilio_account_sid'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getTwilioAccountSidAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'twilio_account_sid');
    }
    
    public function setTwilioAuthTokenAttribute($value) 
    { 
        $this->attributes['twilio_auth_token'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getTwilioAuthTokenAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'twilio_auth_token');
    }
    
    // Advanta SMS Mutators/Accessors
    public function setAdvantaPartnerIdAttribute($value) 
    { 
        $this->attributes['advanta_partner_id'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getAdvantaPartnerIdAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'advanta_partner_id');
    }
    
    public function setAdvantaApiKeyAttribute($value) 
    { 
        $this->attributes['advanta_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getAdvantaApiKeyAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'advanta_api_key');
    }
    
    // BulkSMS Mutators/Accessors
    public function setBulksmsUsernameAttribute($value) 
    { 
        $this->attributes['bulksms_username'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getBulksmsUsernameAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'bulksms_username');
    }
    
    public function setBulksmsPasswordAttribute($value) 
    { 
        $this->attributes['bulksms_password'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getBulksmsPasswordAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'bulksms_password');
    }
    
    // ClickSend Mutators/Accessors
    public function setClicksendUsernameAttribute($value) 
    { 
        $this->attributes['clicksend_username'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getClicksendUsernameAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'clicksend_username');
    }
    
    public function setClicksendApiKeyAttribute($value) 
    { 
        $this->attributes['clicksend_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getClicksendApiKeyAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'clicksend_api_key');
    }
    
    // Infobip Mutators/Accessors
    public function setInfobipApiKeyAttribute($value) 
    { 
        $this->attributes['infobip_api_key'] = $value ? Crypt::encryptString($value) : null; 
    }
    
    public function getInfobipApiKeyAttribute($value) 
    { 
        return $this->safeDecrypt($value, 'infobip_api_key');
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
