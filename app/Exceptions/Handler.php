<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     */
    protected array $dontReport = [
        //
    ];

    /**
     * Register any exception handling callbacks for the application.
     */
    public function register(): void
    {
        // 1️⃣ Handle unknown tenant domains → redirect to central registration page
        $this->renderable(function (TenantCouldNotBeIdentifiedException $e, Request $request) {
            $centralRegisterUrl = rtrim(config('app.url'), '/').'/register';
            return redirect()->away($centralRegisterUrl);
        });

        // 2️⃣ Handle unauthenticated access on tenant sub-domains → redirect to tenant login
        $this->renderable(function (AuthenticationException $e, Request $request) {
            $host           = $request->getHost();
            $centralDomains = config('tenancy.central_domains', []);

            // If we are already on a central domain, use the default behaviour
            if (in_array($host, $centralDomains, true)) {
                return redirect()->guest(route('login'));
            }

            // Otherwise, send unauthenticated users on tenant subdomains to /login on that subdomain
            return redirect()->guest('/login');
        });
    }
}
