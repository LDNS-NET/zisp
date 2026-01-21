<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\TenantActivity;

class AuditStaffActions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = Auth::user();

        // Only log actions for staff members (exclude superadmins and tenant admins if desired)
        // Usually, we want to log EVERYTHING for accountability.
        if ($user && ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH') || $request->isMethod('DELETE'))) {
            
            // Skip sensitive routes like login/logout to prevent clutter
            if ($request->routeIs('login') || $request->routeIs('logout')) {
                return $response;
            }

            $action = $this->determineAction($request);
            $description = $this->generateDescription($request, $user);

            TenantActivity::log($action, $description, $user, [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'input' => $request->except(['password', 'password_confirmation', '_token']),
                'status' => $response->getStatusCode()
            ]);
        }

        return $response;
    }

    private function determineAction(Request $request) {
        $name = $request->route()?->getName() ?? 'unknown.action';
        return str_replace('.', '_', $name);
    }

    private function generateDescription(Request $request, $user) {
        $method = $request->method();
        $path = $request->path();
        return "{$user->name} performed {$method} on {$path}";
    }
}
