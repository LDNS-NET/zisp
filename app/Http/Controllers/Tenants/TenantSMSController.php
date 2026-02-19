<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantSMS;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantSMSTemplate;
use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendSmsJob;
use Carbon\Carbon;

class TenantSMSController extends Controller
{
    public function index(Request $request)
    {
        $smsLogs = TenantSMS::latest()
            ->when($request->search, function ($q) use ($request) {
                $q->where('phone_number', 'like', "%{$request->search}%")
                  ->orWhere('message', 'like', "%{$request->search}%")
                  ->orWhere('recipient_name', 'like', "%{$request->search}%");
            })
            ->paginate($request->input('per_page', 10))
            ->withQueryString();
            
        $renters = NetworkUser::all(['id', 'full_name', 'phone']);
        $templates = TenantSMSTemplate::orderBy('name')->get(['id', 'name', 'content']);
        $packages = Package::all(['id', 'name']);
        $locations = NetworkUser::whereNotNull('location')->distinct()->pluck('location');

        $tenant = \App\Models\Tenant::find(Auth::user()->tenant_id);

        return Inertia::render('SMS/Index', [
            'smsLogs' => $smsLogs,
            'renters' => $renters,
            'templates' => $templates,
            'packages' => $packages,
            'locations' => $locations,
            'sms_balance' => $tenant->sms_balance ?? 0,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }


    public function create()
    {
        $renters = NetworkUser::all();
        $templates = TenantSMSTemplate::orderBy('name')->get(['id', 'name', 'content']);

        return Inertia::render('SMS/Create', [
            'renters' => $renters,
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        // ...existing code...

        $validated = $request->validate([
            'recipients' => 'nullable|array',
            'recipients.*' => 'exists:network_users,id',
            'filters' => 'nullable|array',
            'message' => 'required|string|max:500',
        ]);

        if (!empty($validated['filters'])) {
            $query = NetworkUser::query();
            $filters = $validated['filters'];

            if (!empty($filters['location'])) {
                $query->where('location', $filters['location']);
            }
            if (!empty($filters['package_id'])) {
                $query->where('package_id', $filters['package_id']);
            }
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'active') {
                   $query->where('status', 'active'); // Assuming 'status' column or online check
                } elseif ($filters['status'] === 'expired') {
                   $query->where('expires_at', '<', now());
                } elseif ($filters['status'] === 'expiring_soon') {
                   $query->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDays(3));
                }
            }
             if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $renters = $query->get();
        } else {
            $renters = NetworkUser::whereIn('id', $validated['recipients'] ?? [])->get();
        }

        if ($renters->isEmpty()) {
            return redirect()->back()->withErrors(['recipients' => 'No valid renters selected.']);
        }

        $tenant = \App\Models\Tenant::find(Auth::user()->tenant_id);

        $supportNumber = Auth::user()->phone ?? '';
        
        // Use SmsGatewayService for sending
        $smsGatewayService = app(\App\Services\SmsGatewayService::class);
        
        foreach ($renters as $renter) {
            $personalizedMessage = $validated['message'];
            $packageName = '';
            if ($renter->package) {
                $packageName = $renter->package->name ?? '';
            }
            $replacements = [
                '{expiry_date}' => $renter->expires_at ? $renter->expires_at->format('Y-m-d') : 'N/A',
                '{full_name}' => $renter->full_name ?? '',
                '{phone}' => $renter->phone ?? '',
                '{account_number}' => $renter->account_number ?? '',
                '{package}' => $packageName,
                '{username}' => $renter->username ?? '',
                '{password}' => $renter->password ?? '',
                '{support_number}' => $supportNumber,
            ];
            foreach ($replacements as $key => $value) {
                $personalizedMessage = str_replace($key, $value, $personalizedMessage);
            }
            
            $smsLog = TenantSMS::create([
                'recipient_name' => $renter->full_name,
                'phone_number' => $renter->phone ?? $renter->phone_number ?? null,
                'message' => $personalizedMessage,
                'status' => 'pending',
                'tenant_id' => $tenant->id,
            ]);
            
            $rawPhone = $renter->phone ?? $renter->phone_number ?? '';
            $phoneNumber = preg_replace('/^0/', '254', trim($rawPhone));

            // Dispatch background job
            SendSmsJob::dispatch($smsLog, $phoneNumber, $personalizedMessage);
        }

        return redirect()->route('sms.index')
            ->with('success', 'SMS batch queued for sending.');
    }

    public function resendFailed(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'required|string|in:1h,3h,6h,12h,24h',
        ]);

        $hours = (int) str_replace('h', '', $validated['duration']);
        $threshold = now()->subHours($hours);

        $pendingSms = TenantSMS::where(function ($q) {
            $q->where('status', 'failed')
              ->orWhere('status', 'pending');
        })
        ->where('created_at', '>=', $threshold)
        ->get();

        if ($pendingSms->isEmpty()) {
            return redirect()->back()->with('info', 'No messages found to resend for the selected duration.');
        }

        foreach ($pendingSms as $sms) {
            // Reset status for resending
            $sms->update(['status' => 'pending', 'error_message' => null]);
            
            SendSmsJob::dispatch($sms, $sms->phone_number, $sms->message);
        }

        return redirect()->route('sms.index')
            ->with('success', count($pendingSms) . ' messages have been requeued.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:tenant_sms,id',
        ]);

        TenantSMS::whereIn('id', $request->ids)->delete();

        return redirect()->route('sms.index')
            ->with('success', 'Selected SMS logs deleted successfully.');
    }

    public function destroy(TenantSMS $sms)
    {
        $sms->delete();

        return redirect()->route('sms.index')
            ->with('success', 'SMS log deleted successfully.');
    }

    public function count(Request $request)
    {
        $query = NetworkUser::query();
        
        if ($request->location) {
            $query->where('location', $request->location);
        }
        if ($request->package_id) {
            $query->where('package_id', $request->package_id);
        }
        if ($request->status) {
            if ($request->status === 'active') {
                $query->where('expires_at', '>', now());
            } elseif ($request->status === 'expired') {
                $query->where('expires_at', '<', now());
            } elseif ($request->status === 'expiring_soon') {
                $query->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDays(3));
            }
        }
        if ($request->search) {
             $search = $request->search;
             $query->where(function($q) use ($search) {
                 $q->where('full_name', 'like', "%{$search}%")
                   ->orWhere('username', 'like', "%{$search}%")
                   ->orWhere('phone', 'like', "%{$search}%");
             });
        }

        return response()->json(['count' => $query->count()]);
    }
}
