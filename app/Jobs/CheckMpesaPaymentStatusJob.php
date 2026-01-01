<?php

namespace App\Jobs;

use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantHotspot;
use App\Models\Package;
use App\Services\MpesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckMpesaPaymentStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payment;
    protected $maxAttempts = 10; // Check up to 10 times
    protected $retryDelay = 30; // 30 seconds between checks

    /**
     * Create a new job instance.
     */
    public function __construct(TenantPayment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     */
    public function handle(MpesaService $mpesaService): void
    {
        try {
            // Skip if payment is already processed
            if ($this->payment->status === 'paid') {
                Log::info('Payment already processed, skipping job', ['payment_id' => $this->payment->id]);
                return;
            }

            // Check payment status with M-Pesa
            // We use the CheckoutRequestID which is stored in checkout_request_id (or fallback to intasend_reference)
            $checkoutRequestId = $this->payment->checkout_request_id ?? $this->payment->intasend_reference;

            if (!$checkoutRequestId) {
                Log::warning('Payment missing CheckoutRequestID, cannot check status', ['payment_id' => $this->payment->id]);
                return;
            }

            // Resolve M-Pesa credentials for this tenant
            $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $this->payment->tenant_id)
                ->where('provider', 'mpesa')
                ->where('use_own_api', true)
                ->where('is_active', true)
                ->first();

            if ($gateway) {
                $mpesaService->setCredentials([
                    'consumer_key' => $gateway->mpesa_consumer_key,
                    'consumer_secret' => $gateway->mpesa_consumer_secret,
                    'shortcode' => $gateway->mpesa_shortcode,
                    'passkey' => $gateway->mpesa_passkey,
                    'environment' => $gateway->mpesa_env,
                ]);
            }

            Log::info('Checking M-Pesa payment status', [
                'payment_id' => $this->payment->id,
                'checkout_request_id' => $checkoutRequestId,
                'using_custom_api' => (bool)$gateway
            ]);

            $response = $mpesaService->queryTransactionStatus($checkoutRequestId);
            
            Log::info('M-Pesa status query response', [
                'payment_id' => $this->payment->id,
                'response' => $response
            ]);

            if ($response['success'] && $response['status'] === 'paid') {
                // Mark payment as paid
                $this->payment->status = 'paid';
                $this->payment->checked = true;
                $this->payment->result_code = $response['result_code'];
                $this->payment->result_desc = $response['result_desc'];
                // Try to get receipt number from result desc or raw response if available
                // Note: Query response might not always have the receipt number if it's just a status check
                // But usually ResultDesc contains it or we get it from callback
                $this->payment->response = array_merge($this->payment->response ?? [], $response['response']);
                $this->payment->paid_at = now();
                $this->payment->save();

                // Trigger automatic disbursement if using default API
                if ($this->payment->disbursement_status === 'pending') {
                    \App\Jobs\ProcessDisbursementJob::dispatch($this->payment);
                }

                Log::info('Payment confirmed as paid via query, processing account update', [
                    'payment_id' => $this->payment->id
                ]);

                // Process user account update/creation
                $this->processSuccessfulPayment();

            } elseif ($response['success'] && in_array($response['status'], ['failed', 'cancelled'])) {
                // Mark payment as failed
                $this->payment->status = 'failed';
                $this->payment->result_code = $response['result_code'];
                $this->payment->result_desc = $response['result_desc'];
                $this->payment->response = array_merge($this->payment->response ?? [], $response['response']);
                $this->payment->save();

                Log::info('Payment marked as failed via query', [
                    'payment_id' => $this->payment->id,
                    'status' => $response['status']
                ]);

            } else {
                // Payment still pending or query failed, retry
                $attempts = $this->attempts();
                if ($attempts < $this->maxAttempts) {
                    Log::info('Payment status pending or query failed, retrying', [
                        'payment_id' => $this->payment->id,
                        'attempt' => $attempts + 1
                    ]);
                    
                    $this->release($this->retryDelay);
                } else {
                    Log::warning('Max retry attempts reached', ['payment_id' => $this->payment->id]);
                    // Don't mark as failed automatically, just stop retrying. Callback might still come.
                }
            }

        } catch (\Exception $e) {
            Log::error('Error checking M-Pesa payment status', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $attempts = $this->attempts();
            if ($attempts < $this->maxAttempts) {
                $this->release($this->retryDelay);
            }
        }
    }

    /**
     * Process successful payment: create or update user
     */
    private function processSuccessfulPayment(): void
    {
        try {
            $isHotspot = (bool)$this->payment->hotspot_package_id;
            
            if ($isHotspot) {
                $package = TenantHotspot::withoutGlobalScopes()
                    ->where('id', $this->payment->hotspot_package_id)
                    ->where('tenant_id', $this->payment->tenant_id)
                    ->first();
            } else {
                $package = Package::find($this->payment->package_id);
            }
            
            if (!$package) {
                Log::error('Package not found for payment', ['payment_id' => $this->payment->id]);
                return;
            }
            
            // Check if user already exists
            $userQuery = NetworkUser::withoutGlobalScopes()
                ->where('tenant_id', $this->payment->tenant_id);

            if ($this->payment->user_id) {
                $existingUser = $userQuery->where('id', $this->payment->user_id)->first();
            } else {
                $existingUser = $userQuery->where('phone', $this->payment->phone)
                    ->where('type', $isHotspot ? 'hotspot' : 'pppoe')
                    ->first();
            }
                
            if ($existingUser) {
                // Update existing user
                if ($isHotspot) {
                    $existingUser->hotspot_package_id = $package->id;
                    $existingUser->package_id = null;
                } else {
                    $existingUser->package_id = $package->id;
                    $existingUser->hotspot_package_id = null;
                }

                // Accumulate expiry if current expiry is in the future
                $baseDate = ($existingUser->expires_at && $existingUser->expires_at->isFuture()) 
                    ? $existingUser->expires_at 
                    : now();

                // Calculate expiry
                $existingUser->expires_at = $this->calculateExpiry($package, $baseDate);
                $existingUser->save();
                
                // Link user to payment if not already linked
                if (!$this->payment->user_id) {
                    $this->payment->update(['user_id' => $existingUser->id]);
                }
                
                Log::info('Updated existing user after payment', [
                    'user_id' => $existingUser->id,
                    'username' => $existingUser->username,
                    'type' => $existingUser->type,
                    'payment_id' => $this->payment->id
                ]);

                // For PPPoE, we might need to unsuspend on MikroTik
                if ($existingUser->type === 'pppoe') {
                    $this->unsuspendOnMikrotik($existingUser);
                }
                return;
            }
            
            // If it's hotspot and user doesn't exist, create new one
            if ($isHotspot) {
                $username = NetworkUser::generateHotspotUsername($this->payment->tenant_id);
                $plainPassword = Str::random(8);
                
                $user = NetworkUser::create([
                    'full_name' => $package->name,
                    'username' => $username,
                    'password' => $plainPassword,
                    'phone' => $this->payment->phone,
                    'type' => 'hotspot',
                    'package_id' => null,
                    'hotspot_package_id' => $package->id,
                    'expires_at' => $this->calculateExpiry($package),
                    'registered_at' => now(),
                    'tenant_id' => $this->payment->tenant_id,
                ]);
                
                $this->payment->update(['user_id' => $user->id]);

                Log::info('Created new hotspot user after payment', [
                    'user_id' => $user->id,
                    'username' => $username,
                    'payment_id' => $this->payment->id
                ]);
            } else {
                Log::warning('PPPoE user not found for payment, cannot auto-create PPPoE users', [
                    'phone' => $this->payment->phone,
                    'payment_id' => $this->payment->id
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to process successful payment', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Unsuspend user on MikroTik
     */
    private function unsuspendOnMikrotik(NetworkUser $user): void
    {
        try {
            $tenantMikrotik = \App\Models\Tenants\TenantMikrotik::where('tenant_id', $user->tenant_id)->first();
            if ($tenantMikrotik) {
                $mikrotik = new \App\Services\MikrotikService($tenantMikrotik);
                $mikrotik->unsuspendUser($user->type, $user->mikrotik_id ?? $user->username);
                Log::info('User unsuspended on MikroTik', ['user_id' => $user->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to unsuspend user on MikroTik', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate expiry date based on package.
     */
    private function calculateExpiry($package, $baseDate = null)
    {
        $value = $package->duration_value ?? $package->duration ?? 1;
        $unit = $package->duration_unit ?? 'days';
        $base = $baseDate ?: now();

        return match ($unit) {
            'minutes' => $base->copy()->addMinutes($value),
            'hours'   => $base->copy()->addHours($value),
            'days'    => $base->copy()->addDays($value),
            'weeks'   => $base->copy()->addWeeks($value),
            'months'  => $base->copy()->addMonths($value),
            default   => $base->copy()->addDays($value),
        };
    }

}
