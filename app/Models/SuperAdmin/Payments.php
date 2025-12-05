<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    protected $table = 'all_payments';

    protected $fillable = [
        'tenant_id', //tenant id
        'paid_to', //tenants name
        'amount',
        'payer_phone', //phone number used to make the payment
        'currency', // as displayed currency in the tenants database
        'payer_name',  // the wifi subscriber who made the payment
        'paid_at',
        'status', //e.g., 'completed', 'pending', 'failed', 'cancelled'
        'checked', //boolean to indicate if payment is verified 
        'receipt_number', 
        'payment_method', //check the transfer method the wifi user used to make the payment
        'transaction_id', //from the payment gateway or mobile money platform
        'remarks',
        'created_by',
        'disbursed_at',
        'disbursement_label', // e.g., 'mpesa', 'bank transfer', 'paypal', etc.
        'disbursement_status', // e.g., 'pending', 'completed', 'failed'
        'metadata', // JSON field for any additional data  

    ];
}
