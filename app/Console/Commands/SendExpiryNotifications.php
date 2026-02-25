<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantSMSTemplate;
use App\Models\Tenants\TenantSMS;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SendExpiryNotifications extends Command
{
    protected $signature = 'sms:send-expiry-notifications';
    protected $description = 'Send SMS notifications to users whose internet has expired.';

    public function handle()
    {
        $users = NetworkUser::withoutGlobalScopes()
            ->with('tenant')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('expiry_notified_at')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No expired users to notify.');
            return 0;
        }

        $this->info("Found {$users->count()} expired users to notify.");

        foreach ($users as $user) {
            // Get or create tenant-specific template
            $template = TenantSMSTemplate::withoutGlobalScopes()
                ->where('tenant_id', $user->tenant_id)
                ->where('name', 'Internet Expiry')
                ->first();

            if (!$template) {
                // Auto-create default template
                $template = TenantSMSTemplate::create([
                    'tenant_id' => $user->tenant_id,
                    'name' => 'Internet Expiry',
                    'content' => 'Hello {full_name}, your internet (Account: {account_number}) has expired on {expiry_date}. To renew, login to {portal_url} using Username: {username}, Password: {password}. Contact: {support_number}',
                    'created_by' => 1, // System user
                ]);
                
                Log::info("Auto-created 'Internet Expiry' template for tenant {$user->tenant_id}");
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
                '{portal_url}' => $user->tenant?->portal_url ?? 'https://zimaradius.net/customer/login',
            ];
            
            $message = $template->content;
            foreach ($replacements as $key => $value) {
                $message = str_replace($key, $value, $message);
            }

            $smsLog = TenantSMS::create([
                'tenant_id' => $user->tenant_id,
                'recipient_name' => ($user->full_name ?: $user->username) ?: 'Customer',
                'phone_number' => $user->phone ?? $user->phone_number ?? null,
                'message' => $message,
                'status' => 'pending',
            ]);

            $phoneNumbers = preg_replace('/^0/', '254', trim($user->phone ?? $user->phone_number ?? ''));

            // Dispatch background job
            \App\Jobs\SendSmsJob::dispatch($smsLog, $phoneNumbers, $message);

            $user->expiry_notified_at = now();
            $user->save();
            Log::info('Processed expiry SMS for user', ['user_id' => $user->id, 'tenant_id' => $user->tenant_id]);
        }

        $this->info('Expiry notifications sent.');
        return 0;
    }
}
