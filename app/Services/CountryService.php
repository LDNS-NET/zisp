<?php

namespace App\Services;

class CountryService
{
    protected static $countries = [
        'KE' => [
            'name' => 'Kenya',
            'currency' => 'KES',
            'dial_code' => '254',
            'pppoe_rate' => 18,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 500,
        ],
        'TZ' => [
            'name' => 'Tanzania',
            'currency' => 'TZS',
            'dial_code' => '255',
            'pppoe_rate' => 350,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 10000,
        ],
        'SO' => [
            'name' => 'Somalia',
            'currency' => 'SOS',
            'dial_code' => '252',
            'pppoe_rate' => 88,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 2500,
        ],
        'SS' => [
            'name' => 'South Sudan',
            'currency' => 'SSP',
            'dial_code' => '211',
            'pppoe_rate' => 680,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 5000,
        ],
        'UG' => [
            'name' => 'Uganda',
            'currency' => 'UGX',
            'dial_code' => '256',
            'pppoe_rate' => 530,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 15000,
        ],
        'GH' => [
            'name' => 'Ghana',
            'currency' => 'GHS',
            'dial_code' => '233',
            'pppoe_rate' => 2,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 50,
        ],
    ];

    public static function getCountryData($code)
    {
        $data = self::$countries[strtoupper($code)] ?? self::$countries['KE'];
        
        // Merge with database pricing plans
        $plan = \App\Models\PricingPlan::where('country_code', $data['code'] ?? strtoupper($code))
            ->where('is_active', true)
            ->first();

        if ($plan) {
            $data['pppoe_rate'] = $plan->pppoe_price_per_month;
            $data['hotspot_rate'] = $plan->hotspot_price_percentage / 100; // Convert percentage to decimal
            $data['minimum_pay'] = $plan->minimum_pay;
            $data['currency'] = $plan->currency;
        }

        return $data;
    }

    public static function getAll()
    {
        $countries = self::$countries;
        $plans = \App\Models\PricingPlan::where('is_active', true)->get()->keyBy('country_code');

        foreach ($countries as $code => &$data) {
            // Add code if missing (it was missing in the array keys but good to have in data)
            $data['code'] = $code; 

            if (isset($plans[$code])) {
                $plan = $plans[$code];
                $data['pppoe_rate'] = $plan->pppoe_price_per_month;
                $data['hotspot_rate'] = $plan->hotspot_price_percentage / 100;
                $data['minimum_pay'] = $plan->minimum_pay;
                $data['currency'] = $plan->currency;
                
                // Also add formatted values for frontend if needed
                $data['pppoePricePerMonth'] = $plan->pppoe_price_per_month;
                $data['hotspotPricePerMonth'] = $plan->hotspot_price_percentage . '%';
            } else {
                // Ensure frontend compatible keys exist
                $data['pppoePricePerMonth'] = $data['pppoe_rate'];
                $data['hotspotPricePerMonth'] = ($data['hotspot_rate'] * 100) . '%';
            }
        }

        return array_values($countries);
    }
}
