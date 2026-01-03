<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingRequest extends Model
{
    protected $fillable = [
        'name',
        'email',
        'isp_name',
        'country',
        'message',
        'status',
    ];
}
