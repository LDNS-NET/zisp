<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantSMS;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantSMSTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class TenantSMSController extends Controller
{
    public function index(Request $request)
{
    $perPage = (int) $request->input('perPage', 10);

    $smsLogs = TenantSMS::latest()->paginate($perPage)->withQueryString();

    return Inertia::render('SMS/Index', [
        'smsLogs' => $smsLogs,
        'perPage' => (int) $perPage,
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
            'recipients' => 'required|array',
            'recipients.*' => 'exists:network_users,id',
            'message' => 'required|string|max:500',
        ]);

        $renters = NetworkUser::whereIn('id', $validated['recipients'])->get();

        if ($renters->isEmpty()) {
            return redirect()->back()->withErrors(['recipients' => 'No valid renters selected.']);
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
            ]);
            $rawPhone = $renter->phone ?? $renter->phone_number ?? '';
            $phoneNumber = preg_replace('/^0/', '254', trim($rawPhone));
            // Send SMS immediately for each recipient
            $apiKey = env('TALKSASA_API_KEY');
            $senderId = env('TALKSASA_SENDER_ID');
            if ($apiKey && $senderId && $phoneNumber) {
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
            } else {
                $smsLog->update([
                    'status' => 'failed',
                    'error_message' => 'Missing TalkSasa API credentials or phone number',
                ]);
            }
        }

        return redirect()->route('sms.index')
            ->with('success', 'SMS batch is being processed.');
    }

    public function destroy(TenantSMS $smsLog)
    {
        $smsLog->delete();

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

}
