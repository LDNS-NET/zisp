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
        return self::$countries[strtoupper($code)] ?? self::$countries['KE'];
    }

    public static function getAll()
    {
        return self::$countries;
    }
}
