<?php

namespace App\Jobs;

use App\Models\Tenants\TenantSMS;
use App\Services\SmsGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $smsLog;
    protected $phoneNumber;
    protected $message;

    /**
     * Create a new job instance.
     *
     * @param TenantSMS $smsLog
     * @param string $phoneNumber
     * @param string $message
     */
    public function __construct(TenantSMS $smsLog, string $phoneNumber, string $message)
    {
        $this->smsLog = $smsLog;
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(SmsGatewayService $smsGatewayService)
    {
        try {
            Log::info("[Jobs\SendSmsJob] Dispatching SMS for log ID: {$this->smsLog->id}");

            $result = $smsGatewayService->sendSMS(
                $this->smsLog->tenant_id,
                $this->phoneNumber,
                $this->message
            );

            if ($result['success']) {
                $this->smsLog->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'provider_message_id' => $result['message_id'] ?? null,
                ]);
                Log::info("[Jobs\SendSmsJob] SMS sent successfully for log ID: {$this->smsLog->id}");
                return;
            }

            $this->smsLog->update([
                'status' => 'failed',
                'error_message' => $result['message'] ?? 'Unknown gateway error',
            ]);
            Log::error("[Jobs\SendSmsJob] SMS failed for log ID: {$this->smsLog->id} - ERROR: " . ($result['message'] ?? 'Unknown'));
        } catch (\Throwable $e) {
            // Never leave an SMS log stuck in 'pending' due to an unhandled exception.
            try {
                $this->smsLog->update([
                    'status' => 'failed',
                    'error_message' => get_class($e) . ': ' . $e->getMessage(),
                ]);
            } catch (\Throwable $inner) {
                // If we can't update the row, still rethrow to surface in failed_jobs.
            }

            Log::error("[Jobs\SendSmsJob] Unhandled exception for log ID: {$this->smsLog->id}", [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
