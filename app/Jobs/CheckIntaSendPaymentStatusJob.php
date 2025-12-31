<?php

namespace App\Jobs;

use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantHotspot;
use App\Models\Package;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckIntaSendPaymentStatusJob implements ShouldQueue
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
    public function handle(): void
    {
        try {
            // Skip if payment is already processed
            if ($this->payment->status === 'paid') {
                Log::info('Payment already processed, skipping job', ['payment_id' => $this->payment->id]);
                return;
            }

            // Check payment status with IntaSend
            $statusResponse = Http::withHeaders([
                'Authorization' => config('services.intasend.secret_key') ? 'Bearer ' . config('services.intasend.secret_key') : 'Bearer ' . env('INTASEND_SECRET_KEY'),
                'Content-Type' => 'application/json',
            ])->get((config('services.intasend.base_url') ?? 'https://payment.intasend.com/api/v1/payment') . '/mpesa/transaction-status/', [
                'invoice' => $this->payment->intasend_reference,
            ]);

            $statusData = $statusResponse->json();

            // Robust status extraction
            $status = $statusData['invoice']['state'] ?? $statusData['status'] ?? $statusData['state'] ?? null;
            $status = $status ? strtoupper($status) : null;

            Log::info('Checking IntaSend payment status', [
                'payment_id' => $this->payment->id,
                'invoice' => $this->payment->intasend_reference,
                'status' => $status,
                'response' => $statusData
            ]);

            if ($statusResponse->successful() && ($status === 'PAID' || $status === 'COMPLETE' || $status === 'SUCCESS')) {
                // Mark payment as paid
                $this->payment->status = 'paid';
                $this->payment->checked = true;
                $this->payment->transaction_id = $statusData['invoice']['mpesa_reference'] ?? $statusData['id'] ?? $statusData['transaction_id'] ?? $this->payment->transaction_id;
                $this->payment->response = array_merge($this->payment->response ?? [], $statusData);
                $this->payment->paid_at = now();
                $this->payment->save();

                Log::info('Payment confirmed as paid, creating user account', [
                    'payment_id' => $this->payment->id,
                    'phone' => $this->payment->phone,
                    'amount' => $this->payment->amount
                ]);

                // Create hotspot user
                $this->createHotspotUser();

            } elseif ($statusResponse->successful() && in_array($status, ['FAILED', 'CANCELLED', 'REJECTED'])) {
                // Mark payment as failed
                $this->payment->status = 'failed';
                $this->payment->response = array_merge($this->payment->response ?? [], $statusData);
                $this->payment->save();

                Log::info('Payment marked as failed', [
                    'payment_id' => $this->payment->id,
                    'status' => $status
                ]);

            } else {
                // Payment still pending, retry if we haven't exceeded max attempts
                $attempts = $this->attempts();
                if ($attempts < $this->maxAttempts) {
                    Log::info('Payment still pending, retrying', [
                        'payment_id' => $this->payment->id,
                        'attempt' => $attempts + 1,
                        'max_attempts' => $this->maxAttempts
                    ]);
                    
                    $this->release($this->retryDelay);
                } else {
                    Log::warning('Max retry attempts reached, marking as failed', [
                        'payment_id' => $this->payment->id,
                        'attempts' => $attempts
                    ]);
                    
                    $this->payment->status = 'failed';
                    $this->payment->response = array_merge($this->payment->response ?? [], ['error' => 'Max retry attempts reached']);
                    $this->payment->save();
                }
            }

        } catch (\Exception $e) {
            Log::error('Error checking IntaSend payment status', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Retry on exception if we haven't exceeded max attempts
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
            $existingUser = NetworkUser::withoutGlobalScopes()
                ->where('tenant_id', $this->payment->tenant_id)
                ->where('phone', $this->payment->phone)
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

                // Accumulate expiry if current expiry is in the future
                $baseDate = ($existingUser->expires_at && $existingUser->expires_at->isFuture()) 
                    ? $existingUser->expires_at 
                    : now();

                // Calculate expiry
                $existingUser->expires_at = $this->calculateExpiry($package, $baseDate);
                $existingUser->save();
                
                // Link user to payment
                $this->payment->update(['user_id' => $existingUser->id]);
                
                Log::info('Updated existing hotspot user after payment', [
                    'user_id' => $existingUser->id,
                    'username' => $existingUser->username,
                    'payment_id' => $this->payment->id
                ]);
                return;
            }
            
            // Generate new user credentials
            $username = NetworkUser::generateHotspotUsername($this->payment->tenant_id);
            $plainPassword = Str::random(8);
            
            // Create new user
            $user = NetworkUser::create([
                'account_number' => $this->generateAccountNumber(),
                'full_name' => $package->name,
                'username' => $username,
                'password' => $plainPassword,
                'phone' => $this->payment->phone,
                'type' => 'hotspot',
                'package_id' => $this->payment->package_id,
                'hotspot_package_id' => $this->payment->hotspot_package_id,
                'expires_at' => $this->calculateExpiry($package),
                'registered_at' => now(),
                'tenant_id' => $this->payment->tenant_id,
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

    /**
     * Generate a system-wide unique account number for NetworkUser.
     */
    private function generateAccountNumber(): string
    {
        do {
            $accountNumber = 'NU' . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (NetworkUser::withoutGlobalScopes()->where('account_number', $accountNumber)->exists());
        return $accountNumber;
    }
}
