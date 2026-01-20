<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantMikrotik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NetworkTopologyController extends Controller
{
    /**
     * Display network topology map
     */
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;

        return Inertia::render('Analytics/NetworkTopology', [
            'topology' => $this->getTopology($tenantId),
        ]);
    }

    /**
     * Get topology data as JSON (for real-time updates)
     */
    public function getTopology($tenantId = null)
    {
        if (!$tenantId) {
            $tenantId = Auth::user()->tenant_id;
        }

        // Fetch all routers with their latest status
        $routers = TenantMikrotik::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->get();

        $nodes = [];
        $edges = [];

        foreach ($routers as $router) {
            // Determine status based on last_seen
            $status = 'offline';
            if ($router->last_seen) {
                $minutesSinceLastSeen = now()->diffInMinutes($router->last_seen);
                if ($minutesSinceLastSeen < 5) {
                    $status = 'online';
                } elseif ($minutesSinceLastSeen < 15) {
                    $status = 'warning';
                }
            }

            // Get resource usage if available
            $cpu = null;
            $memory = null;
            $uptime = null;

            if ($router->resource_data) {
                $resourceData = is_string($router->resource_data) 
                    ? json_decode($router->resource_data, true) 
                    : $router->resource_data;

                $cpu = $resourceData['cpu-load'] ?? null;
                $memory = isset($resourceData['free-memory'], $resourceData['total-memory']) 
                    ? round((1 - ($resourceData['free-memory'] / $resourceData['total-memory'])) * 100, 1)
                    : null;
                $uptime = $resourceData['uptime'] ?? null;
            }

            $nodes[] = [
                'id' => $router->id,
                'name' => $router->identity ?? $router->name,
                'type' => 'router',
                'status' => $status,
                'ip' => $router->host,
                'cpu' => $cpu,
                'memory' => $memory,
                'uptime' => $uptime,
                'last_seen' => $router->last_seen?->diffForHumans(),
                'active_users' => $router->active_users_count ?? 0,
                'location' => $router->location,
            ];
        }

        // For now, we'll create a simple hub-and-spoke topology
        // In the future, this could be enhanced to detect actual network connections
        if (count($nodes) > 1) {
            // Find the "main" router (first one or one with most users)
            $mainRouter = collect($nodes)->sortByDesc('active_users')->first();
            
            foreach ($nodes as $node) {
                if ($node['id'] !== $mainRouter['id']) {
                    $edges[] = [
                        'from' => $mainRouter['id'],
                        'to' => $node['id'],
                        'bandwidth' => '1Gbps', // Placeholder
                    ];
                }
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'summary' => [
                'total' => count($nodes),
                'online' => collect($nodes)->where('status', 'online')->count(),
                'warning' => collect($nodes)->where('status', 'warning')->count(),
                'offline' => collect($nodes)->where('status', 'offline')->count(),
            ],
        ];
    }

    /**
     * Get detailed device information
     */
    public function getDeviceDetails($id)
    {
        $router = TenantMikrotik::findOrFail($id);

        // Get more detailed stats
        $details = [
            'id' => $router->id,
            'name' => $router->identity ?? $router->name,
            'ip' => $router->host,
            'port' => $router->port,
            'location' => $router->location,
            'created_at' => $router->created_at->format('Y-m-d H:i:s'),
            'last_seen' => $router->last_seen?->diffForHumans(),
            'resource_data' => $router->resource_data,
            'active_users_count' => $router->active_users_count ?? 0,
        ];

        return response()->json($details);
    }

    /**
     * Get real-time topology updates (for polling)
     */
    public function getTopologyUpdates()
    {
        $tenantId = Auth::user()->tenant_id;
        return response()->json($this->getTopology($tenantId));
    }
}
