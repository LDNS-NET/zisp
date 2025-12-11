<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use App\Services\Mikrotik\WinboxConfigService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class WinboxController extends Controller
{
    protected WinboxConfigService $winboxService;

    public function __construct(WinboxConfigService $winboxService)
    {
        $this->winboxService = $winboxService;
    }

    /**
     * Get RouterOS configuration script for enabling Winbox.
     *
     * @param int $id Router ID
     * @return JsonResponse
     */
    public function getConfig(int $id): JsonResponse
    {
        $mikrotik = TenantMikrotik::findOrFail($id);
        
        $script = $this->winboxService->generateWinboxConfig($mikrotik);

        return response()->json([
            'success' => true,
            'script' => $script,
            'router' => [
                'id' => $mikrotik->id,
                'name' => $mikrotik->name,
                'wg_ip' => $mikrotik->wireguard_address,
                'winbox_url' => "winbox://{$mikrotik->wireguard_address}:8291",
            ],
        ]);
    }

    /**
     * Push Winbox configuration to router via API.
     *
     * @param int $id Router ID
     * @return JsonResponse
     */
    public function enableWinbox(int $id): JsonResponse
    {
        $mikrotik = TenantMikrotik::findOrFail($id);
        
        $result = $this->winboxService->enableWinboxOverWireGuard($mikrotik);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Ping router to check online status.
     *
     * @param int $id Router ID
     * @return JsonResponse
     */
    public function ping(int $id): JsonResponse
    {
        $mikrotik = TenantMikrotik::findOrFail($id);
        
        $result = $this->winboxService->pingRouter($mikrotik->wireguard_address);

        return response()->json([
            'success' => true,
            'router_id' => $mikrotik->id,
            'router_name' => $mikrotik->name,
            'online' => $result['online'],
            'wg_ip' => $result['wg_ip'],
            'message' => $result['message'],
            'winbox_url' => "winbox://{$mikrotik->wireguard_address}:8291",
        ]);
    }
}
