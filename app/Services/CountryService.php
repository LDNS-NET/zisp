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
            'exchange_rate' => 1.0,
        ],
        'TZ' => [
            'name' => 'Tanzania',
            'currency' => 'TZS',
            'dial_code' => '255',
            'pppoe_rate' => 350,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 10000,
            'exchange_rate' => 20.0,
        ],
        'SO' => [
            'name' => 'Somalia',
            'currency' => 'SOS',
            'dial_code' => '252',
            'pppoe_rate' => 88,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 2500,
            'exchange_rate' => 4.5,
        ],
        'SS' => [
            'name' => 'South Sudan',
            'currency' => 'SSP',
            'dial_code' => '211',
            'pppoe_rate' => 680,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 5000,
            'exchange_rate' => 10.0,
        ],
        'UG' => [
            'name' => 'Uganda',
            'currency' => 'UGX',
            'dial_code' => '256',
            'pppoe_rate' => 530,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 15000,
            'exchange_rate' => 30.0,
        ],
        'GH' => [
            'name' => 'Ghana',
            'currency' => 'GHS',
            'dial_code' => '233',
            'pppoe_rate' => 2,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 50,
            'exchange_rate' => 0.1,
        ],
        'ZA' => ['name' => 'South Africa', 'currency' => 'ZAR', 'dial_code' => '27', 'pppoe_rate' => 120, 'hotspot_rate' => 0.03, 'minimum_pay' => 70, 'exchange_rate' => 0.05],
        'NG' => ['name' => 'Nigeria', 'currency' => 'NGN', 'dial_code' => '234', 'pppoe_rate' => 220, 'hotspot_rate' => 0.03, 'minimum_pay' => 5000, 'exchange_rate' => 15.0],
        'CI' => ['name' => 'Côte d\'Ivoire', 'currency' => 'XOF', 'dial_code' => '226', 'pppoe_rate' => 2, 'hotspot_rate' => 0.03, 'minimum_pay' => 50, 'exchange_rate' => 0.8],
        'RW' => ['name' => 'Rwanda', 'currency' => 'RWF', 'dial_code' => '250', 'pppoe_rate' => 220, 'hotspot_rate' => 0.03, 'minimum_pay' => 4500, 'exchange_rate' => 12.0],
        'CM' => ['name' => 'Cameroon', 'currency' => 'XAF', 'dial_code' => '237', 'pppoe_rate' => 12, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.5],
        'ZM' => ['name' => 'Zambia', 'currency' => 'ZMW', 'dial_code' => '260', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 100, 'exchange_rate' => 0.04],
        'ET' => ['name' => 'Ethiopia', 'currency' => 'ETB', 'dial_code' => '251', 'pppoe_rate' => 23, 'hotspot_rate' => 0.03, 'minimum_pay' => 250, 'exchange_rate' => 0.15],
        'EG' => ['name' => 'Egypt', 'currency' => 'EGP', 'dial_code' => '20', 'pppoe_rate' => 23, 'hotspot_rate' => 0.03, 'minimum_pay' => 150, 'exchange_rate' => 2.0],
        'ZW' => ['name' => 'Zimbabwe', 'currency' => 'ZWL', 'dial_code' => '263', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 1500, 'exchange_rate' => 0.02],
        'SN' => ['name' => 'Senegal', 'currency' => 'XOF', 'dial_code' => '221', 'pppoe_rate' => 10, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.8],
        'CD' => ['name' => 'DRC Congo', 'currency' => 'CDF', 'dial_code' => '243', 'pppoe_rate' => 20, 'hotspot_rate' => 0.03, 'minimum_pay' => 10000, 'exchange_rate' => 0.02],
        'DZ' => ['name' => 'Algeria', 'currency' => 'DZD', 'dial_code' => '213', 'pppoe_rate' => 25, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.08],
        'AO' => ['name' => 'Angola', 'currency' => 'AOA', 'dial_code' => '244', 'pppoe_rate' => 25, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.01],
        'BJ' => ['name' => 'Benin', 'currency' => 'XOF', 'dial_code' => '229', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.8],
        'BW' => ['name' => 'Botswana', 'currency' => 'BWP', 'dial_code' => '267', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 150, 'exchange_rate' => 0.07],
        'BF' => ['name' => 'Burkina Faso', 'currency' => 'XOF', 'dial_code' => '226', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.8],
        'BI' => ['name' => 'Burundi', 'currency' => 'BIF', 'dial_code' => '257', 'pppoe_rate' => 45, 'hotspot_rate' => 0.03, 'minimum_pay' => 10000, 'exchange_rate' => 0.004],
        'CV' => ['name' => 'Cape Verde', 'currency' => 'CVE', 'dial_code' => '238', 'pppoe_rate' => 12, 'hotspot_rate' => 0.03, 'minimum_pay' => 1500, 'exchange_rate' => 0.9],
        'CF' => ['name' => 'Central African Republic', 'currency' => 'XAF', 'dial_code' => '236', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 5000, 'exchange_rate' => 0.5],
        'TD' => ['name' => 'Chad', 'currency' => 'XAF', 'dial_code' => '235', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 5000, 'exchange_rate' => 0.5],
        'KM' => ['name' => 'Comoros', 'currency' => 'KMF', 'dial_code' => '269', 'pppoe_rate' => 12, 'hotspot_rate' => 0.03, 'minimum_pay' => 5000, 'exchange_rate' => 0.2],
        'CG' => ['name' => 'Congo', 'currency' => 'XAF', 'dial_code' => '242', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 5000, 'exchange_rate' => 0.5],
        'DJ' => ['name' => 'Djibouti', 'currency' => 'DJF', 'dial_code' => '253', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 3000, 'exchange_rate' => 0.05],
        'GQ' => ['name' => 'Equatorial Guinea', 'currency' => 'XAF', 'dial_code' => '240', 'pppoe_rate' => 18, 'hotspot_rate' => 0.03, 'minimum_pay' => 5000, 'exchange_rate' => 0.5],
        'ER' => ['name' => 'Eritrea', 'currency' => 'ERN', 'dial_code' => '291', 'pppoe_rate' => 20, 'hotspot_rate' => 0.03, 'minimum_pay' => 500, 'exchange_rate' => 0.06],
        'SZ' => ['name' => 'Eswatini', 'currency' => 'SZL', 'dial_code' => '268', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 200, 'exchange_rate' => 0.05],
        'GA' => ['name' => 'Gabon', 'currency' => 'XAF', 'dial_code' => '241', 'pppoe_rate' => 18, 'hotspot_rate' => 0.03, 'minimum_pay' => 5000, 'exchange_rate' => 0.5],
        'GM' => ['name' => 'Gambia', 'currency' => 'GMD', 'dial_code' => '220', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 500, 'exchange_rate' => 0.12],
        'GN' => ['name' => 'Guinea', 'currency' => 'GNF', 'dial_code' => '224', 'pppoe_rate' => 18, 'hotspot_rate' => 0.03, 'minimum_pay' => 200000, 'exchange_rate' => 0.0001],
        'GW' => ['name' => 'Guinea-Bissau', 'currency' => 'XOF', 'dial_code' => '245', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.8],
        'LS' => ['name' => 'Lesotho', 'currency' => 'LSL', 'dial_code' => '266', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 200, 'exchange_rate' => 0.05],
        'LR' => ['name' => 'Liberia', 'currency' => 'LRD', 'dial_code' => '231', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 500, 'exchange_rate' => 0.006],
        'LY' => ['name' => 'Libya', 'currency' => 'LYD', 'dial_code' => '218', 'pppoe_rate' => 25, 'hotspot_rate' => 0.03, 'minimum_pay' => 100, 'exchange_rate' => 0.2],
        'MG' => ['name' => 'Madagascar', 'currency' => 'MGA', 'dial_code' => '261', 'pppoe_rate' => 45, 'hotspot_rate' => 0.03, 'minimum_pay' => 15000, 'exchange_rate' => 0.003],
        'MW' => ['name' => 'Malawi', 'currency' => 'MWK', 'dial_code' => '265', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 5000, 'exchange_rate' => 0.008],
        'ML' => ['name' => 'Mali', 'currency' => 'XOF', 'dial_code' => '223', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.8],
        'MR' => ['name' => 'Mauritania', 'currency' => 'MRU', 'dial_code' => '222', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 500, 'exchange_rate' => 0.03],
        'MU' => ['name' => 'Mauritius', 'currency' => 'MUR', 'dial_code' => '230', 'pppoe_rate' => 12, 'hotspot_rate' => 0.03, 'minimum_pay' => 500, 'exchange_rate' => 0.45],
        'MA' => ['name' => 'Morocco', 'currency' => 'MAD', 'dial_code' => '212', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 100, 'exchange_rate' => 0.06],
        'MZ' => ['name' => 'Mozambique', 'currency' => 'MZN', 'dial_code' => '258', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 500, 'exchange_rate' => 0.02],
        'NA' => ['name' => 'Namibia', 'currency' => 'NAD', 'dial_code' => '264', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 150, 'exchange_rate' => 0.05],
        'NE' => ['name' => 'Niger', 'currency' => 'XOF', 'dial_code' => '227', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.8],
        'ST' => ['name' => 'São Tomé and Príncipe', 'currency' => 'STN', 'dial_code' => '239', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 100, 'exchange_rate' => 0.04],
        'SC' => ['name' => 'Seychelles', 'currency' => 'SCR', 'dial_code' => '248', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 200, 'exchange_rate' => 0.07],
        'SL' => ['name' => 'Sierra Leone', 'currency' => 'SLL', 'dial_code' => '232', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 100000, 'exchange_rate' => 0.00005],
        'SD' => ['name' => 'Sudan', 'currency' => 'SDG', 'dial_code' => '249', 'pppoe_rate' => 25, 'hotspot_rate' => 0.03, 'minimum_pay' => 1000, 'exchange_rate' => 0.02],
        'TG' => ['name' => 'Togo', 'currency' => 'XOF', 'dial_code' => '228', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 2500, 'exchange_rate' => 0.8],
        'TN' => ['name' => 'Tunisia', 'currency' => 'TND', 'dial_code' => '216', 'pppoe_rate' => 15, 'hotspot_rate' => 0.03, 'minimum_pay' => 30, 'exchange_rate' => 0.03],
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
            $data['exchange_rate'] = $plan->exchange_rate;
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
                $data['exchange_rate'] = $plan->exchange_rate;
                
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
