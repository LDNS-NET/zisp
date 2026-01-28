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

        return Inertia::render('SMS/Index', [
            'smsLogs' => $smsLogs,
            'renters' => $renters,
            'templates' => $templates,
            'packages' => $packages,
            'locations' => $locations,
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
        $countryCode = $tenant->country_code ?? 'KE';

        // 1. Get Tenant Gateway Settings
        $gatewaySettings = \App\Models\TenantSmsGateway::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();

        // Default to TalkSasa if no gateway configured (Legacy/System Fallback)
        $provider = $gatewaySettings ? $gatewaySettings->provider : 'talksasa';

        // 2. Check SuperAdmin Restrictions (CountrySmsSetting)
        // We assume enabled by default unless explicitly disabled
        $countrySetting = \App\Models\CountrySmsSetting::where('country_code', $countryCode)
            ->where('gateway', $provider)
            ->first();

        if ($countrySetting && !$countrySetting->is_active) {
             return redirect()->back()->withErrors(['message' => "The SMS gateway ($provider) is currently disabled for your country."]);
        }

        // 3. Determine Credentials
        $apiKey = null;
        $senderId = null;

        if ($gatewaySettings && $gatewaySettings->api_key) {
            $apiKey = $gatewaySettings->api_key;
            $senderId = $gatewaySettings->sender_id;
        } 
        
        // Fallback or System Default for TalkSasa
        if ((!$apiKey) && $provider === 'talksasa') {
            $apiKey = env('TALKSASA_API_KEY');
            $senderId = env('TALKSASA_SENDER_ID');
        }

        if (!$apiKey || !$senderId) {
             return redirect()->back()->withErrors(['message' => 'SMS Gateway not configured properly.']);
        }

        $supportNumber = Auth::user()->phone ?? '';
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
                'tenant_id' => $tenant->id, // Ensure tenant_id is set
            ]);
            
            $rawPhone = $renter->phone ?? $renter->phone_number ?? '';
            $phoneNumber = preg_replace('/^0/', '254', trim($rawPhone)); // Basic formatting, should use CountryService or lib

            // Send SMS based on provider
            if ($provider === 'talksasa') {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->post('https://bulksms.talksasa.com/api/v3/sms/send', [
                            'recipient' => $phoneNumber,
                            'sender_id' => $senderId,
                            'type' => 'plain',
                            'message' => $personalizedMessage,
                        ]);
                
                $data = $response->json();
                if ($response->successful() && isset($data['status']) && $data['status'] === 'success') {
                    $smsLog->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                } else {
                    $smsLog->update([
                        'status' => 'failed',
                        'error_message' => $data['message'] ?? $response->body(),
                    ]);
                }
            } elseif ($provider === 'celcom') {
                // Celcom SMS Implementation
                // Assumption: specific endpoint and structure based on common gateways in the region
                // If the user provides a specific endpoint, this should be updated.
                
                $response = \Illuminate\Support\Facades\Http::post('https://isms.celcomafrica.com/api/services/sendsms', [
                    'partnerID' => $gatewaySettings->username, // Mapped from username/partnerID field
                    'apikey' => $apiKey,
                    'shortcode' => $senderId,
                    'mobile' => $phoneNumber,
                    'message' => $personalizedMessage,
                ]);

                $data = $response->json();
                
                // Flexible success check as APIs vary
                // Assuming status 200 and some success indicator
                if ($response->successful() && (isset($data['status']) && ($data['status'] == '200' || $data['status'] === 'success'))) {
                     $smsLog->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                } else {
                     $smsLog->update([
                        'status' => 'failed',
                        'error_message' => $data['message'] ?? $data['responses'][0]['response-description'] ?? $response->body(),
                    ]);
                }

            } else {
                // Placeholder for other providers
                $smsLog->update([
                    'status' => 'failed',
                    'error_message' => "Provider $provider not yet implemented in this controller.",
                ]);
            }
        }

        return redirect()->route('sms.index')
            ->with('success', 'SMS batch processed.');
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

    private function sendSms(array $logIds, string $phoneNumbers, string $message)
    {
        try {
            $apiKey = env('TALKSASA_API_KEY');
            $senderId = env('TALKSASA_SENDER_ID');

            if (!$apiKey || !$senderId) {
                TenantSMS::whereIn('id', $logIds)->update([
                    'status' => 'failed',
                    'error_message' => 'Missing TalkSasa API credentials'
                ]);
                return;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post('https://bulksms.talksasa.com/api/v3/sms/send', [
                        'recipient' => $phoneNumbers,
                        'sender_id' => $senderId,
                        'type' => 'plain',
                        'message' => $message,
                    ]);

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && $data['status'] === 'success') {
                TenantSMS::whereIn('id', $logIds)->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                TenantSMS::whereIn('id', $logIds)->update([
                    'status' => 'failed',
                    'error_message' => $data['message'] ?? $response->body(),
                ]);
            }

        } catch (\Exception $e) {
            TenantSMS::whereIn('id', $logIds)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
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
