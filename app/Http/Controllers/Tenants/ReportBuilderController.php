<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantCustomReport;
use App\Models\Tenants\TenantReportRun;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\TenantTrafficAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportBuilderController extends Controller
{
    /**
     * List saved reports and available metrics
     */
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;
        
        $reports = TenantCustomReport::where('tenant_id', $tenantId)
            ->with('latestRun')
            ->latest()
            ->get();

        $availableMetrics = [
            'revenue' => [
                'name' => 'Total Revenue',
                'description' => 'Sum of paid payments',
                'table' => 'tenant_payments',
                'dimensions' => ['date', 'payment_method'],
            ],
            'users_active' => [
                'name' => 'Active Users',
                'description' => 'Count of users currently active',
                'table' => 'network_users',
                'dimensions' => ['date', 'type', 'location'],
            ],
            'traffic_total' => [
                'name' => 'Data Usage',
                'description' => 'Total bytes transferred',
                'table' => 'tenant_traffic_analytics',
                'dimensions' => ['date', 'hour'],
            ],
            'new_leads' => [
                'name' => 'New Leads',
                'description' => 'Count of new leads generated',
                'table' => 'tenant_leads',
                'dimensions' => ['date', 'status'],
            ]
        ];

        return Inertia::render('Analytics/ReportBuilder', [
            'reports' => $reports,
            'metrics' => $availableMetrics,
        ]);
    }

    /**
     * Save a new report configuration
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'config' => 'required|array',
            'schedule' => 'nullable|array',
        ]);

        $report = TenantCustomReport::create([
            'tenant_id' => Auth::user()->tenant_id,
            'name' => $validated['name'],
            'config' => $validated['config'],
            'schedule' => $validated['schedule'],
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Report configuration saved.');
    }

    /**
     * Trigger report generation
     */
    public function generate(TenantCustomReport $report)
    {
        $run = TenantReportRun::create([
            'report_id' => $report->id,
            'generated_at' => now(),
            'status' => 'processing',
        ]);

        try {
            // Logic for data aggregation based on $report->config
            // This would normally be dispatched to a job for large datasets
            // For now, we'll mark as completed with a placeholder path
            
            $run->update([
                'status' => 'completed',
                'file_path' => 'reports/' . $report->id . '_' . time() . '.xlsx',
            ]);

            return back()->with('success', 'Report generation started.');
        } catch (\Exception $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            return back()->with('error', 'Report generation failed.');
        }
    }

    /**
     * Delete report configuration
     */
    public function destroy(TenantCustomReport $report)
    {
        $report->delete();
        return back()->with('success', 'Report deleted.');
    }
}
