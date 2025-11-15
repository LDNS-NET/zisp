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
        // Global scope to show only the current user's records
        static::addGlobalScope('created_by', function ($query) {
            if (Auth::check()) {
                $query->where('created_by', Auth::id());
            }
        });

        // Automatically set created_by on create
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });
    }
}


