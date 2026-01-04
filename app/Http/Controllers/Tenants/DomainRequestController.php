<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\DomainRequest;
use App\Models\User;
use App\Notifications\NewDomainRequestNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DomainRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $requests = DomainRequest::where('tenant_id', $tenantId)->latest()->paginate(10);
        
        // Mark related notifications as read
        $user->unreadNotifications()
            ->where('type', \App\Notifications\DomainRequestStatusNotification::class)
            ->get()
            ->markAsRead();

        return Inertia::render('Tenants/DomainRequests/Index', [
            'requests' => $requests
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:transfer,custom',
            'requested_domain' => 'required|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;
        $validated['status'] = 'pending';

        if (!$validated['tenant_id']) {
            return back()->with('error', 'Unable to identify tenant. Please try again.');
        }

        $domainRequest = DomainRequest::create($validated);

        // Notify SuperAdmins
        $superAdmins = User::where('is_super_admin', true)->get();
        foreach ($superAdmins as $admin) {
            $admin->notify(new NewDomainRequestNotification($domainRequest));
        }

        return back()->with('success', 'Domain request submitted successfully.');
    }
}
