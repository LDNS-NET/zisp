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

    protected $identifier;
    protected $maxAttempts = 10; // Check up to 10 times
    protected $retryDelay = 30; // 30 seconds between checks

    /**
     * Create a new job instance.
     */
    public function __construct($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $identifier = $this->identifier;
            $payment = null;

            if ($identifier instanceof TenantPayment) {
                $payment = $identifier;
            } elseif (is_numeric($identifier)) {
                $payment = TenantPayment::find($identifier);
            } else {
                $payment = TenantPayment::where('intasend_reference', $identifier)
                    ->orWhere('receipt_number', $identifier)
                    ->first();
            }

            // Skip if payment is already processed
            if ($payment && $payment->status === 'paid') {
                Log::info('Payment already processed, skipping job', ['identifier' => $identifier]);
                return;
            }

            // Check payment status with IntaSend
            $statusResponse = Http::withHeaders([
                'Authorization' => config('services.intasend.secret_key') ? 'Bearer ' . config('services.intasend.secret_key') : 'Bearer ' . env('INTASEND_SECRET_KEY'),
                'Content-Type' => 'application/json',
            ])->get((config('services.intasend.base_url') ?? 'https://payment.intasend.com/api/v1/payment') . '/mpesa/transaction-status/', [
                'invoice' => $payment ? $payment->intasend_reference : $identifier,
            ]);

            $statusData = $statusResponse->json();

            // Robust status extraction
            $status = $statusData['invoice']['state'] ?? $statusData['status'] ?? $statusData['state'] ?? null;
            $status = $status ? strtoupper($status) : null;

            Log::info('Checking IntaSend payment status', [
                'identifier' => $identifier,
                'status' => $status,
                'response' => $statusData
            ]);

            if ($statusResponse->successful() && ($status === 'PAID' || $status === 'COMPLETE' || $status === 'SUCCESS')) {
                if (!$payment) {
                    $apiRef = $statusData['invoice']['api_ref'] ?? $identifier;
                    $payment = $this->createPaymentFromApiRef($apiRef, $statusData);
                } else {
                    $payment->status = 'paid';
                    $payment->checked = true;
                    $payment->transaction_id = $statusData['invoice']['mpesa_reference'] ?? $statusData['id'] ?? $statusData['transaction_id'] ?? $payment->transaction_id;
                    $payment->response = array_merge($payment->response ?? [], $statusData);
                    $payment->paid_at = now();
                    $payment->save();
                }

                if ($payment) {
                    Log::info('Payment confirmed as paid, creating user account', [
                        'payment_id' => $payment->id,
                        'phone' => $payment->phone,
                        'amount' => $payment->amount
                    ]);
                    $this->payment = $payment; // Set for createHotspotUser
                    $this->createHotspotUser();
                }

            } elseif ($statusResponse->successful() && in_array($status, ['FAILED', 'CANCELLED', 'REJECTED'])) {
                if (!$payment) {
                    $apiRef = $statusData['invoice']['api_ref'] ?? $identifier;
                    $this->createPaymentFromApiRef($apiRef, $statusData, 'failed');
                } else {
                    $payment->status = 'failed';
                    $payment->response = array_merge($payment->response ?? [], $statusData);
                    $payment->save();
                }

                Log::info('Payment marked as failed', [
                    'identifier' => $identifier,
                    'status' => $status
                ]);

            } else {
                // Payment still pending, retry if we haven't exceeded max attempts
                $attempts = $this->attempts();
                if ($attempts < $this->maxAttempts) {
                    Log::info('Payment still pending, retrying', [
                        'identifier' => $identifier,
                        'attempt' => $attempts + 1,
                        'max_attempts' => $this->maxAttempts
                    ]);
                    
                    $this->release($this->retryDelay);
                } else {
                    Log::warning('Max retry attempts reached, marking as failed', [
                        'identifier' => $identifier,
                        'attempts' => $attempts
                    ]);
                    
                    if ($payment) {
                        $payment->status = 'failed';
                        $payment->response = array_merge($payment->response ?? [], ['error' => 'Max retry attempts reached']);
                        $payment->save();
                    } else {
                        $apiRef = $statusData['invoice']['api_ref'] ?? $identifier;
                        $this->createPaymentFromApiRef($apiRef, $statusData, 'failed');
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Error checking IntaSend payment status', [
                'identifier' => $this->identifier,
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
     * Create a payment record from encoded api_ref.
     */
    private function createPaymentFromApiRef($apiRef, $statusData, $status = 'paid')
    {
        // Format: HS|{package_id}|{phone}|{uniqid} or PK|{package_id}|{phone}|{uniqid}
        $parts = explode('|', $apiRef);
        if (count($parts) >= 3) {
            $prefix = $parts[0];
            $packageId = $parts[1];
            $phone = $parts[2];
            
            $data = [
                'phone' => $phone,
                'amount' => $statusData['invoice']['amount'] ?? $statusData['amount'] ?? 0,
                'receipt_number' => $apiRef,
                'status' => $status,
                'checked' => ($status === 'paid'),
                'paid_at' => ($status === 'paid') ? now() : null,
                'transaction_id' => $statusData['invoice']['mpesa_reference'] ?? $statusData['mpesa_reference'] ?? $statusData['id'] ?? null,
                'intasend_reference' => $statusData['invoice']['id'] ?? $statusData['invoice_id'] ?? $statusData['id'] ?? null,
                'intasend_checkout_id' => $statusData['invoice']['checkout_id'] ?? $statusData['checkout_id'] ?? null,
                'response' => $statusData,
                'created_by' => \App\Models\User::first()?->id,
            ];

            if ($prefix === 'HS') {
                $data['hotspot_package_id'] = $packageId;
                $data['package_id'] = null;
            } else {
                $data['package_id'] = $packageId;
                $data['hotspot_package_id'] = null;
            }
            
            return TenantPayment::create($data);
        }
        
        return null;
    }

    /**
     * Create hotspot user after successful payment
     */
    private function createHotspotUser(): void
    {
        try {
            if ($this->payment->hotspot_package_id) {
                $package = TenantHotspot::find($this->payment->hotspot_package_id);
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
            $username = 'HS' . strtoupper(Str::random(6));
            $plainPassword = Str::random(8);
            
            // Create new user
            $user = NetworkUser::create([
                'account_number' => $this->generateAccountNumber(),
                'username' => $username,
                'password' => bcrypt($plainPassword),
                'phone' => $this->payment->phone,
                'type' => 'hotspot',
                'package_id' => $this->payment->package_id,
                'hotspot_package_id' => $this->payment->hotspot_package_id,
                'expires_at' => $this->calculateExpiry($package),
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
