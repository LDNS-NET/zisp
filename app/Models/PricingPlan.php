<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingPlan extends Model
{
    protected $fillable = [
        'country_code',
        'currency',
        'pppoe_price_per_month',
        'hotspot_price_percentage',
        'minimum_pay',
        'is_active',
    ];

    protected $casts = [
        'pppoe_price_per_month' => 'decimal:2',
        'hotspot_price_percentage' => 'decimal:2',
        'minimum_pay' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
