<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MikrotikController extends Controller
{
    /**
     * Respond with a simple Mikrotik service health status.
     */
    public function status(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
