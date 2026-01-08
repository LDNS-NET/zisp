<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantSMSTemplate;
use App\Models\Tenants\TenantSMS;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendExpiryWarningNotifications extends Command
{
    protected $signature = 'sms:send-expiry-warnings';
    protected $description = 'Send SMS warning notifications to users whose internet will expire in 3 days.';

    public function handle()
    {
        $this->info('Checking for users whose internet will expire in 3 days...');
        
        // Find users whose internet will expire between now and 3 days from now
        // and who haven't been warned yet
        $users = NetworkUser::withoutGlobalScopes()
            ->with('tenant')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(3))
            ->whereNull('expiry_warning_sent_at')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users to warn.');
            return 0;
        }

        $this->info("Found {$users->count()} users to notify.");

        foreach ($users as $user) {
            // Get tenant-specific template
            $template = TenantSMSTemplate::withoutGlobalScopes()
                ->where('tenant_id', $user->tenant_id)
                ->where('name', 'Internet Expiry Warning')
                ->first();

            if (!$template) {
                Log::warning("No Internet Expiry Warning template found for tenant {$user->tenant_id}");
                continue;
            }

            $supportNumber = $user->tenant?->phone ?? '';

            $packageName = $user->package ? $user->package->name : '';
            $replacements = [
                '{expiry_date}' => $user->expires_at ? $user->expires_at->format('Y-m-d') : 'N/A',
                '{full_name}' => $user->full_name ?? $user->username ?? 'Customer',
                '{phone}' => $user->phone ?? '',
                '{account_number}' => $user->account_number ?? '',
                '{package}' => $packageName,
                '{username}' => $user->username ?? '',
                '{password}' => $user->password ?? '',
                '{support_number}' => $supportNumber,
                '{portal_url}' => $user->tenant?->portal_url ?? 'https://zyraaf.cloud/customer/login',
            ];
            
            $message = $template->content;
            foreach ($replacements as $key => $value) {
                $message = str_replace($key, $value, $message);
            }

            $smsLog = TenantSMS::create([
                'tenant_id' => $user->tenant_id,
                'tenant_id' => $user->tenant_id,
                'recipient_name' => ($user->full_name ?: $user->username) ?: 'Customer',
                'phone_number' => $user->phone ?? $user->phone_number ?? null,
                'message' => $message,
                'status' => 'pending',
            ]);

            // Send SMS immediately
            $apiKey = env('TALKSASA_API_KEY');
            $senderId = env('TALKSASA_SENDER_ID');
            $phoneNumbers = preg_replace('/^0/', '254', trim($user->phone ?? $user->phone_number ?? ''));
            
            if ($apiKey && $senderId && $phoneNumbers) {
                try {
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
                } catch (\Exception $e) {
                    $smsLog->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            } else {
                $smsLog->update([
                    'status' => 'failed',
                    'error_message' => 'Missing TalkSasa API credentials or phone number',
                ]);
            }

            $user->expiry_warning_sent_at = now();
            $user->save();
            Log::info('Sent expiry warning SMS to user', ['user_id' => $user->id, 'tenant_id' => $user->tenant_id]);
        }

        $this->info('Expiry warning notifications sent.');
        return 0;
    }
}
