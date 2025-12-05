<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    protected $table = 'all_tenants';

    protected $fillable = [
        'name',
        'email',
        'tenant_id',    
        'name',
        'username',
        'phone',
        'status', // active, inactive, suspended, etc.
        'domain',
        'role', // admin, user, technician etc.
        'logo',
        'all_subscribers', //the count of all subscribers under this tenant
        'address', // physical address of the tenant
        'country',
        'timezone',
        'language', // preferred language of the user, can also be fltered using country of regstration
        'suspended', // boolean to indicate if the tenant is suspended
        'created_by',
        'bank_name',
        'account_name',
        'account_number',
        'paybill_number',
        'till_number',
        'mpesa_number',
        'lifetime_traffic', // total data traffic used by the user's clients over their lifetime
        'user_value', // monetary value of the user to the business
        'mikrotik_count',
        'users_count',
        'prunning_date', // date for performing database prunning for this tenant
        'wallet_balance',
        'expiry_date',  // subscription expiry date
        'joining_date', // date when the user joined the platform
        'email_verified_at',
        'phone_verified_at',
        'last_login_ip',
        'two_factor_enabled',
        'two_factor_secret',
        'account_locked_until', // timestamp until which the account is locked due to security reasons
        'business_registration_number', // official registration number of the tenant
        'metadata', // JSON field for any additional data
    ];
}
