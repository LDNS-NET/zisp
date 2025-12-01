<?php

namespace App\Jobs;

use App\Models\Tenants\TenantMikrotik;
use App\Services\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SetupMikrotikApiUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mikrotik;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantMikrotik $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if API credentials are already set (idempotency)
            if ($this->mikrotik->api_username && $this->mikrotik->api_password) {
                Log::info('API user already configured for router', [
                    'router_id' => $this->mikrotik->id,
                    'router_name' => $this->mikrotik->name,
                ]);
                return;
            }

            // Generate strong random password (18-24 characters)
            $passwordLength = rand(18, 24);
            $password = Str::random($passwordLength);

            // Create service instance - will use admin credentials since api_username is not set yet
            $service = MikrotikService::forMikrotik($this->mikrotik);

            // Create or update zisp_user with full permissions
            $success = $service->createSystemUser('zisp_user', $password, 'full');

            if (!$success) {
                Log::error('Failed to create API user on router', [
                    'router_id' => $this->mikrotik->id,
                    'router_name' => $this->mikrotik->name,
                ]);

                // Log to router logs
                $this->mikrotik->logs()->create([
                    'action' => 'setup_api_user',
                    'message' => 'Failed to create dedicated API user (zisp_user)',
                    'status' => 'failed',
                ]);

                // Retry the job
                $this->release(60); // Retry after 60 seconds
                return;
            }

            // Save API credentials to database
            $this->mikrotik->api_username = 'zisp_user';
            $this->mikrotik->api_password = $password;
            $this->mikrotik->save();

            Log::info('API user successfully configured for router', [
                'router_id' => $this->mikrotik->id,
                'router_name' => $this->mikrotik->name,
                'api_username' => 'zisp_user',
            ]);

            // Log to router logs
            $this->mikrotik->logs()->create([
                'action' => 'setup_api_user',
                'message' => 'Dedicated API user (zisp_user) created successfully. System will now use this user for all API calls.',
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            Log::error('SetupMikrotikApiUser job failed', [
                'router_id' => $this->mikrotik->id,
                'router_name' => $this->mikrotik->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Log to router logs
            $this->mikrotik->logs()->create([
                'action' => 'setup_api_user',
                'message' => 'Error setting up API user: ' . $e->getMessage(),
                'status' => 'failed',
            ]);

            // Retry the job
            $this->release(60); // Retry after 60 seconds
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SetupMikrotikApiUser job permanently failed', [
            'router_id' => $this->mikrotik->id,
            'router_name' => $this->mikrotik->name,
            'error' => $exception->getMessage(),
        ]);

        // Log to router logs
        $this->mikrotik->logs()->create([
            'action' => 'setup_api_user',
            'message' => 'API user setup permanently failed after all retries: ' . $exception->getMessage(),
            'status' => 'failed',
        ]);
    }
}
