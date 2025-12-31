<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TenantSMSTemplate extends Model
{
    protected $table = "tenant_sms_templates";

    protected $fillable = [
        'name',
        'content',
        'created_by',
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
