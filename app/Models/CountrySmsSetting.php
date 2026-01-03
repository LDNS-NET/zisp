<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountrySmsSetting extends Model
{
    protected $fillable = [
        'country_code',
        'gateway',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
