<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantPrediction;
use App\Services\PredictiveAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PredictiveAnalyticsController extends Controller
{
    /**
     * Display predictive analytics dashboard
     */
    public function index(PredictiveAnalyticsService $service)
    {
        $tenantId = Auth::user()->tenant_id;

        // Fetch predictions
        $predictions = TenantPrediction::where('tenant_id', $tenantId)
            ->valid()
            ->get();

        $churnPredictions = $predictions->where('prediction_type', 'churn')
            ->load('user:id,username,full_name,phone,expires_at,status')
            ->sortByDesc('prediction_value')
            ->values();

        $revenuePredictions = $predictions->where('prediction_type', 'revenue')->first();

        return Inertia::render('Analytics/Predictions', [
            'churnRisk' => $churnPredictions,
            'revenueForecast' => $revenuePredictions,
        ]);
    }

    /**
     * Trigger a refresh of predictions
     */
    public function refresh(PredictiveAnalyticsService $service)
    {
        $tenantId = Auth::user()->tenant_id;

        $service->predictChurn($tenantId);
        $service->forecastRevenue($tenantId);

        return back()->with('success', 'Predictions updated based on latest data.');
    }
}
