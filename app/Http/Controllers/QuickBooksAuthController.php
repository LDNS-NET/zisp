<?php

namespace App\Http\Controllers;

use App\Services\QuickBooksService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuickBooksAuthController extends Controller
{
    protected $quickBooksService;

    public function __construct()
    {
        // We'll initialize the service with the current tenant ID
        $this->middleware(function ($request, $next) {
            $this->quickBooksService = new QuickBooksService(tenant('id'));
            return $next($request);
        });
    }

    /**
     * Redirect the user to the QuickBooks authorization page.
     */
    public function redirect()
    {
        try {
            $authUrl = $this->quickBooksService->getAuthorizationUrl();
            return redirect()->away($authUrl);
        } catch (\Exception $e) {
            Log::error('QuickBooks Auth Redirect Failed: ' . $e->getMessage());
            return back()->with('error', 'Could not connect to QuickBooks. Please check your configuration.');
        }
    }

    /**
     * Handle the callback from QuickBooks.
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $realmId = $request->query('realmId');

        if (!$code || !$realmId) {
            return redirect()->route('tenant.settings.index')->with('error', 'QuickBooks authorization failed or was canceled.');
        }

        try {
            $this->quickBooksService->exchangeCodeForToken($code, $realmId);
            return redirect()->route('tenant.settings.index')->with('success', 'QuickBooks connected successfully!');
        } catch (\Exception $e) {
            Log::error('QuickBooks Auth Callback Failed: ' . $e->getMessage());
            return redirect()->route('tenant.settings.index')->with('error', 'Failed to exchange token with QuickBooks.');
        }
    }
    
    /**
     * Disconnect QuickBooks for the current tenant.
     */
    public function disconnect()
    {
        try {
            \App\Models\TenantSetting::where('tenant_id', tenant('id'))
                ->where('category', 'quickbooks')
                ->delete();
                
            return back()->with('success', 'QuickBooks disconnected successfully.');
        } catch (\Exception $e) {
            Log::error('QuickBooks Disconnect Failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to disconnect QuickBooks.');
        }
    }
}
