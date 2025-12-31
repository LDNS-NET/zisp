<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TenantSMS extends Model
{
    protected $table = "tenant_sms";

    protected $fillable = [
        'recipient_name',
        'phone_number',
        'message',
        'status',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Automatically set created_by on create
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });
    }
}


