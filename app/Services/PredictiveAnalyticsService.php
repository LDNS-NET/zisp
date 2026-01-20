<?php

namespace App\Services;

use App\Models\Tenants\TenantTrafficAnalytics;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPrediction;
use App\Models\Tenants\TenantPayment;
use App\Models\TenantInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PredictiveAnalyticsService
{
    /**
     * Run churn prediction for all users in a tenant
     */
    public function predictChurn($tenantId)
    {
        // 1. Get all active users
        $users = NetworkUser::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();

        $predictions = [];

        foreach ($users as $user) {
            $riskScore = 0;
            $factors = [];

            // Factor 1: Usage Trend (Last 7 days vs Previous 23 days)
            $recentUsage = TenantTrafficAnalytics::where('user_id', $user->id)
                ->where('date', '>=', Carbon::now()->subDays(7))
                ->sum('total_bytes');

            $previousUsage = TenantTrafficAnalytics::where('user_id', $user->id)
                ->whereBetween('date', [Carbon::now()->subDays(30), Carbon::now()->subDays(8)])
                ->sum('total_bytes');

            // Normalize previous usage to 7-day equivalent
            $normalizedPrevious = ($previousUsage / 23) * 7;

            if ($normalizedPrevious > 1024 * 1024 * 10) { // Only count if they had >10MB usage
                $usageDrop = (($normalizedPrevious - $recentUsage) / $normalizedPrevious) * 100;
                if ($usageDrop > 50) {
                    $riskScore += 40;
                    $factors[] = "Usage dropped by " . round($usageDrop) . "% in the last week";
                }
            } elseif ($recentUsage < 1024 * 512 && $user->registered_at < Carbon::now()->subDays(7)) {
                // If they've been around >7 days but had <512KB usage in the last 7 days
                $riskScore += 20;
                $factors[] = "Very low activity detected in the last 7 days";
            }

            // Factor 2: Payment Gap
            $lastPayment = TenantPayment::where('user_id', $user->id)
                ->where('status', 'paid')
                ->latest('paid_at')
                ->first();

            if (!$lastPayment) {
                if ($user->expires_at && $user->expires_at->diffInDays(now()) < 7) {
                    $riskScore += 40;
                    $factors[] = "No payment records found, package expiring soon";
                }
            } elseif ($lastPayment->paid_at->diffInDays(now()) > 30) {
                if ($user->expires_at && $user->expires_at->diffInDays(now()) < 5) {
                    $riskScore += 30;
                    $factors[] = "Package expiring soon with no recent payment";
                }
            }

            // Factor 3: Expiry Proximity (Absolute)
            if ($user->expires_at && $user->expires_at->isPast()) {
                $riskScore += 40;
                $factors[] = "Account expired on " . $user->expires_at->format('Y-m-d');
            }

            if ($riskScore > 0) {
                $predictions[] = TenantPrediction::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'prediction_type' => 'churn',
                        'entity_id' => $user->id,
                    ],
                    [
                        'prediction_value' => min($riskScore, 100),
                        'confidence' => 70.00,
                        'factors' => $factors,
                        'predicted_at' => now(),
                        'valid_until' => now()->addDays(7),
                    ]
                );
            }
        }

        return $predictions;
    }

    /**
     * Forecast revenue for the next 6 months
     */
    public function forecastRevenue($tenantId)
    {
        // Get last 6 months of actual revenue from paid payments
        $historicalRevenue = TenantPayment::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->where('paid_at', '>=', Carbon::now()->subMonths(6))
            ->select(
                DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as month"),
                DB::raw("SUM(amount) as total")
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        if ($historicalRevenue->isEmpty()) {
            return null; 
        }

        $data = $historicalRevenue->pluck('total')->toArray();
        $n = count($data);
        
        $slope = 0;
        $intercept = 0;

        if ($n >= 2) {
            // Simple linear regression to find growth trend
            $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;

            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $data[$i];
                $sumXY += ($i * $data[$i]);
                $sumX2 += ($i * $i);
            }

            $denom = ($n * $sumX2 - $sumX * $sumX);
            if ($denom != 0) {
                $slope = ($n * $sumXY - $sumX * $sumY) / $denom;
                $intercept = ($sumY - $slope * $sumX) / $n;
            } else {
                $intercept = $data[0];
            }
        } else {
            // Only 1 month of data, assume stable revenue
            $intercept = $data[0];
            $slope = 0;
        }

        $forecast = [];
        for ($j = 1; $j <= 6; $j++) {
            $predictedValue = $slope * ($n + $j - 1) + $intercept;
            $forecast[] = [
                'month' => Carbon::now()->addMonths($j)->format('Y-m'),
                'predicted_value' => max(0, round($predictedValue, 2)),
            ];
        }

        // Store tenant-wide revenue forecast
        TenantPrediction::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'prediction_type' => 'revenue',
                'entity_id' => null,
            ],
            [
                'prediction_value' => count($forecast) > 0 ? $forecast[count($forecast)-1]['predicted_value'] : 0, 
                'confidence' => ($n >= 3) ? 80.00 : 50.00,
                'factors' => [
                    'historical_growth' => round($slope, 2),
                    'forecast_data' => $forecast,
                    'historical_data' => $historicalRevenue
                ],
                'predicted_at' => now(),
                'valid_until' => now()->addDays(30),
            ]
        );

        return $forecast;
    }
}
