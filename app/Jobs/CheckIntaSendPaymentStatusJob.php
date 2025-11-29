<?php

namespace App\Jobs;

use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\NetworkUser;
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
                'Authorization' => 'Bearer ' . config('services.intasend.secret_key'),
                'Content-Type' => 'application/json',
            ])->get(config('services.intasend.base_url') . '/mpesa/transaction-status/', [
                'invoice' => $this->payment->intasend_reference,
            ]);

            $statusData = $statusResponse->json();

            Log::info('Checking IntaSend payment status', [
                'payment_id' => $this->payment->id,
                'invoice' => $this->payment->intasend_reference,
                'status' => $statusData['status'] ?? 'unknown',
                'response' => $statusData
            ]);

            if ($statusResponse->successful() && isset($statusData['status']) && $statusData['status'] === 'PAID') {
                // Mark payment as paid
                $this->payment->status = 'paid';
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

            } elseif ($statusResponse->successful() && isset($statusData['status']) && in_array($statusData['status'], ['FAILED', 'CANCELLED'])) {
                // Mark payment as failed
                $this->payment->status = 'failed';
                $this->payment->response = array_merge($this->payment->response ?? [], $statusData);
                $this->payment->save();

                Log::info('Payment marked as failed', [
                    'payment_id' => $this->payment->id,
                    'status' => $statusData['status']
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
            $package = Package::find($this->payment->package_id);
            
            // Check if user already exists
            $existingUser = NetworkUser::where('phone', $this->payment->phone)
                ->where('type', 'hotspot')
                ->first();
                
            if ($existingUser) {
                // Update existing user
                $existingUser->package_id = $package->id;
                $existingUser->expires_at = now()->addDays($package->duration);
                $existingUser->save();
                
                Log::info('Updated existing hotspot user after payment', [
                    'user_id' => $existingUser->id,
                    'username' => $existingUser->username,
                    'payment_id' => $this->payment->id
                ]);
                return;
            }
            
            // Generate new user credentials
            $username = 'HS' . strtoupper(Str::random(6));
            $plainPassword = Str::random(8);
            
            // Create new user
            $user = NetworkUser::create([
                'account_number' => $this->generateAccountNumber(),
                'username' => $username,
                'password' => bcrypt($plainPassword),
                'phone' => $this->payment->phone,
                'type' => 'hotspot',
                'package_id' => $package->id,
                'expires_at' => now()->addDays($package->duration),
                'registered_at' => now(),
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
