<?php

namespace App\Services;

class CountryService
{
    protected static $countries = [
        'KE' => [
            'name' => 'Kenya',
            'currency' => 'KES',
            'pppoe_rate' => 18,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 500,
        ],
        'TZ' => [
            'name' => 'Tanzania',
            'currency' => 'TZS',
            'pppoe_rate' => 350,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 10000,
        ],
        'SO' => [
            'name' => 'Somalia',
            'currency' => 'SOS',
            'pppoe_rate' => 88,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 2500,
        ],
        'SS' => [
            'name' => 'South Sudan',
            'currency' => 'SSP',
            'pppoe_rate' => 680,
            'hotspot_rate' => 0.03,
            'minimum_pay' => 5000,
        ],
    ];

    public static function getCountryData($code)
    {
        return self::$countries[strtoupper($code)] ?? self::$countries['KE'];
    }

    public static function getAll()
    {
        return self::$countries;
    }
}
