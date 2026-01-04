<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // Multi-layer verification
        if (!$user || 
            $user->role !== 'superadmin' || 
            $user->tenant_id !== null || // SuperAdmins should have null tenant_id
            !$user->hasVerifiedEmail()) {
            
            // Log unauthorized access attempt
            Log::warning('Unauthorized SuperAdmin access attempt', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->path(),
                'method' => $request->method(),
                'timestamp' => now()->toDateTimeString(),
            ]);
            
            abort(403, 'Access denied.');
        }
        
        // Log successful access for audit trail
        Log::info('SuperAdmin access', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip' => $request->ip(),
            'route' => $request->path(),
            'method' => $request->method(),
            'timestamp' => now()->toDateTimeString(),
        ]);
        
        return $next($request);
    }
}
