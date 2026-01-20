<?php

namespace App\Services;

use App\Models\Tenants\TenantMikrotik;
use App\Models\Tenants\TenantRouterAlert;
use Illuminate\Support\Facades\Log;

class AutoRemediationService
{
    /**
     * Check router health and perform remediation if necessary
     */
    public function checkAndRemediate(TenantMikrotik $router, array $currentResources = [])
    {
        $tenantId = $router->tenant_id;
        
        // 1. High Resource Usage Check
        if (!empty($currentResources)) {
            $this->checkResources($router, $currentResources);
        }

        // 2. Connectivity Check & Auto-Reboot logic
        // If the router status was just updated to 'offline' in SyncRoutersCommand,
        // we might want to wait a few cycles before rebooting.
        if ($router->status === 'offline') {
            $this->handleOfflineRouter($router);
        }
    }

    protected function checkResources(TenantMikrotik $router, array $resources)
    {
        $cpuLoad = $resources['cpu-load'] ?? 0;
        
        // High CPU Alert (e.g., > 95%)
        if ($cpuLoad > 95) {
            $this->createAlert($router, 'critical', "Extremely high CPU usage detected: {$cpuLoad}%");
            
            // Logic for auto-remediation of high CPU could go here if persistent
        }
    }

    protected function handleOfflineRouter(TenantMikrotik $router)
    {
        // Simple logic: if offline, log an alert
        // A more complex logic would check history and trigger a reboot via a different channel (if possible)
        // or wait for it to come back and then check logs.
        
        // For actual "Auto-Remediation", we might try to reboot it if it's reachable but API fails
        try {
            // Check if we should auto-reboot (maybe a setting in tenant_settings)
            // For now, let's just log and create a critical alert
            $this->createAlert($router, 'critical', "Router is offline. Self-healing check initiated.");
        } catch (\Exception $e) {
            Log::error("Auto-Remediation failed for router {$router->id}: " . $e->getMessage());
        }
    }

    protected function createAlert(TenantMikrotik $router, $severity, $message)
    {
        TenantRouterAlert::create([
            'tenant_id' => $router->tenant_id,
            'router_id' => $router->id,
            'severity' => $severity,
            'message' => $message,
            'is_resolved' => false,
        ]);
    }

    /**
     * Attempt automated recovery action
     */
    public function attemptReboot(TenantMikrotik $router)
    {
        try {
            $service = MikrotikService::forMikrotik($router);
            return $service->reboot();
        } catch (\Exception $e) {
            Log::error("Failed to auto-reboot router {$router->id}: " . $e->getMessage());
            return false;
        }
    }
}
