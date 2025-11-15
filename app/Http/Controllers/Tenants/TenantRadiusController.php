<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\NetworkUser;
use Illuminate\Support\Facades\Log;

class TenantRadiusController extends Controller
{
    public function authenticate(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $radius = new Radius();

            $radius->setServer('127.0.0.1'); // your FreeRADIUS server
            $radius->setSecret('testing123'); // shared secret (must match clients.conf)
            $radius->setNasIpAddress('127.0.0.1');
            $radius->setAuthPort(1812);
            $radius->setAcctPort(1813);

            $success = $radius->accessRequest($validated['username'], $validated['password']);

            if ($success) {
                return response()->json([
                    'status' => 'accepted',
                    'message' => 'Access-Accept from RADIUS',
                ]);
            } else {
                return response()->json([
                    'status' => 'rejected',
                    'message' => 'Access-Reject from RADIUS',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}