<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DomainRequest;
use App\Models\TenantGeneralSetting;
use App\Models\User;
use App\Notifications\DomainRequestStatusNotification;
use App\Notifications\NewDomainRequestNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DomainRequestController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Mark related notifications as read
        if ($user) {
            $user->unreadNotifications()
                ->where('type', NewDomainRequestNotification::class)
                ->get()
                ->markAsRead();
        }

        $requests = DomainRequest::with('tenant')->latest()->paginate(10);
        return Inertia::render('SuperAdmin/DomainRequests/Index', [
            'requests' => $requests
        ]);
    }

    public function update(Request $request, DomainRequest $domainRequest)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,accepted,rejected,revoked',
            'admin_message' => 'required_if:status,rejected,revoked|nullable|string',
        ]);

        $domainRequest->update($validated);

        // Perform side effects based on status
        $tenant = \App\Models\Tenant::find($domainRequest->tenant_id);

        if ($validated['status'] === 'accepted') {
            $oldDomain = TenantGeneralSetting::where('tenant_id', $domainRequest->tenant_id)->value('website');

            TenantGeneralSetting::updateOrCreate(
                ['tenant_id' => $domainRequest->tenant_id],
                ['website' => $domainRequest->requested_domain]
            );

            // Sync domains table
            if ($tenant) {
                if ($oldDomain && $oldDomain !== $domainRequest->requested_domain) {
                    $tenant->domains()->where('domain', $oldDomain)->delete();
                }
                
                if (!$tenant->domains()->where('domain', $domainRequest->requested_domain)->exists()) {
                    $tenant->domains()->create(['domain' => $domainRequest->requested_domain]);
                }
            }
        } elseif ($validated['status'] === 'revoked') {
            // Clear the website if revoked
            $setting = TenantGeneralSetting::where('tenant_id', $domainRequest->tenant_id)->first();
            if ($setting && $setting->website === $domainRequest->requested_domain) {
                $setting->update(['website' => null]);
            }

            // Remove from domains table
            if ($tenant) {
                $tenant->domains()->where('domain', $domainRequest->requested_domain)->delete();
            }
        }

        // Send notification to tenant admin
        $tenantAdmin = User::where('tenant_id', $domainRequest->tenant_id)->first();
        if ($tenantAdmin) {
            $tenantAdmin->notify(new DomainRequestStatusNotification($domainRequest));
        }

        return back()->with('success', 'Domain request status updated successfully.');
    }

    public function destroy(DomainRequest $domainRequest)
    {
        $domainRequest->delete();
        return back()->with('success', 'Domain request deleted successfully.');
    }
}
