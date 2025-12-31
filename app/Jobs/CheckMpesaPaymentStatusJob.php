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

            Log::info('Checking M-Pesa payment status', [
                'payment_id' => $this->payment->id,
                'checkout_request_id' => $checkoutRequestId
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

                Log::info('Payment confirmed as paid via query, creating user account', [
                    'payment_id' => $this->payment->id
                ]);

                // Create hotspot user
                $this->createHotspotUser();

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
     * Create hotspot user after successful payment
     */
    private function createHotspotUser(): void
    {
        try {
            if ($this->payment->hotspot_package_id) {
                $package = TenantHotspot::withoutGlobalScopes()->find($this->payment->hotspot_package_id);
            } else {
                $package = Package::find($this->payment->package_id);
            }
            
            if (!$package) {
                Log::error('Package not found for payment', ['payment_id' => $this->payment->id]);
                return;
            }
            
            // Check if user already exists
            $existingUser = NetworkUser::where('phone', $this->payment->phone)
                ->where('type', 'hotspot')
                ->first();
                
            if ($existingUser) {
                // Update existing user
                if ($this->payment->hotspot_package_id) {
                    $existingUser->hotspot_package_id = $package->id;
                    $existingUser->package_id = null;
                } else {
                    $existingUser->package_id = $package->id;
                    $existingUser->hotspot_package_id = null;
                }

                // Calculate expiry
                $existingUser->expires_at = $this->calculateExpiry($package);
                $existingUser->save();
                
                Log::info('Updated existing hotspot user after payment', [
                    'user_id' => $existingUser->id,
                    'username' => $existingUser->username,
                    'payment_id' => $this->payment->id
                ]);
                return;
            }
            
            // Generate new user credentials
            $username = NetworkUser::generateUsername();
            $plainPassword = NetworkUser::generatePassword();
            
            Log::info('Generated credentials for new user', [
                'payment_id' => $this->payment->id,
                'username' => $username
            ]);

            // Create new user
            $user = NetworkUser::create([
                'account_number' => $this->generateAccountNumber(),
                'username' => $username,
                'password' => $plainPassword, // Storing plain text for Cleartext-Password compatibility
                'phone' => $this->payment->phone,
                'type' => 'hotspot',
                'package_id' => $this->payment->package_id,
                'hotspot_package_id' => $this->payment->hotspot_package_id,
                'status' => 'active', // Set status to active
                'expires_at' => $this->calculateExpiry($package),
                'registered_at' => now(),
                'created_by' => $this->payment->created_by, // Assign to same owner as payment
            ]);
            
            Log::info('Created new hotspot user after payment', [
                'user_id' => $user->id,
                'username' => $username,
                'phone' => $this->payment->phone,
                'payment_id' => $this->payment->id,
                'package' => $package->name
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create hotspot user after payment', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Calculate expiry date based on package.
     */
    private function calculateExpiry($package)
    {
        $value = $package->duration_value ?? $package->duration ?? 1;
        $unit = $package->duration_unit ?? 'days';

        return match ($unit) {
            'minutes' => now()->addMinutes($value),
            'hours'   => now()->addHours($value),
            'days'    => now()->addDays($value),
            'weeks'   => now()->addWeeks($value),
            'months'  => now()->addMonths($value),
            default   => now()->addDays($value),
        };
    }

    /**
     * Generate a system-wide unique account number for NetworkUser.
     */
    private function generateAccountNumber(): string
    {
        do {
            $accountNumber = 'NU' . mt_rand(1000000000, 9999999999);
        } while (NetworkUser::where('account_number', $accountNumber)->exists());
        return $accountNumber;
    }
}
