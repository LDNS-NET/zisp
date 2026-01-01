<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Services\SubscriptionService;
use App\Services\CountryService;

class BillingCalculationTest
{
    public function test_billing_calculation()
    {
        // Mock CountryData
        $countryData = CountryService::getCountryData('KE');

        echo "Testing billing calculation for Kenya...\n";
        
        // Scenario 1: Minimum pay
        $pppoeUserCount = 10;
        $hotspotIncome = 5000;
        
        $pppoeAmount = $pppoeUserCount * $countryData['pppoe_rate'];
        $hotspotAmount = $hotspotIncome * $countryData['hotspot_rate'];
        $totalAmount = $pppoeAmount + $hotspotAmount;
        $finalAmount = max($totalAmount, $countryData['minimum_pay']);
        
        echo "Scenario 1: 10 PPPoE users, 5000 Hotspot income\n";
        echo "PPPoE Amount: $pppoeAmount, Hotspot Amount: $hotspotAmount\n";
        echo "Total Calculated: $totalAmount, Minimum: {$countryData['minimum_pay']}, Final: $finalAmount\n";
        
        if ($finalAmount == 500) {
            echo "SUCCESS: Minimum pay applied correctly.\n";
        } else {
            echo "FAILURE: Minimum pay not applied correctly.\n";
        }

        // Scenario 2: Above minimum
        $pppoeUserCount = 100;
        $pppoeAmount = $pppoeUserCount * $countryData['pppoe_rate'];
        $totalAmount = $pppoeAmount + $hotspotAmount;
        $finalAmount = max($totalAmount, $countryData['minimum_pay']);
        
        echo "\nScenario 2: 100 PPPoE users, 5000 Hotspot income\n";
        echo "Final Amount: $finalAmount\n";
        
        if ($finalAmount == 1800 + 150) {
            echo "SUCCESS: Calculation correct.\n";
        } else {
            echo "FAILURE: Calculation incorrect. Expected " . (1800 + 150) . " but got $finalAmount\n";
        }
    }
}
