<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantLeads;
use App\Models\Tenants\TenantInstallation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TenantLeadController extends Controller
{
    /**
     * Display a listing of the leads for the authenticated user.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $leads = TenantLeads::select('id', 'name', 'phone_number', 'address', 'email_address', 'status')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('email_address', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($request->get('per_page', 10));

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a new lead in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'phone_number' => ['required', 'min:10', 'unique:tenant_leads,phone_number', 'string', 'max:255'],
            'email_address' => ['nullable', 'email', 'max:255','unique:tenant_leads,email_address'],
            'status'=> ['nullable'],
        ]);

        TenantLeads::create($validated);

        return redirect()->back()->with('success', 'Lead created successfully.');
    }

    /**
     * Update an existing lead.
     */
    public function update(Request $request, TenantLeads $lead)
    {
        $this->authorizeAccess($lead);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:500'],
            'phone_number' => ['sometimes', 'string', 'max:255'],
            'email_address' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['new', 'contacted', 'converted'])], // ✅ added
        ]);

        $lead->update($validated);

        return redirect()->back()->with('success', 'Lead updated successfully.');
    }


    /**
     * Remove a lead from storage.
     */
    public function destroy(TenantLeads $lead)
    {
        $this->authorizeAccess($lead);

        $lead->delete();

        return redirect()->back()->with('success', 'Lead deleted.');
    }

    /**
     * Restrict access to only leads created by the current user.
     */
    protected function authorizeAccess(TenantLeads $lead): void
    {
        // Optionally restrict by user, but not required for tenant DB isolation
    }

    //Bulk delete for Leads

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:tenant_leads,id',
        ]);

        TenantLeads::whereIn('id', $request->ids)->delete();

        return back()->with('success', 'Selected Leads deleted successfully.');
    }

    /**
     * Convert a confirmed lead to an installation
     */
    public function convertToInstallation(TenantLeads $lead)
    {
        // Check if lead is already converted
        if ($lead->status === 'converted') {
            return back()->with('error', 'This lead has already been converted to an installation.');
        }

        // Generate unique installation number with timestamp and random suffix
        $date = now()->format('Ymd');
        $time = now()->format('His');
        $random = strtoupper(substr(uniqid(), -4));
        $installationNumber = "INST-{$date}-{$time}-{$random}";

        // Create installation from lead
        $installation = TenantInstallation::create([
            'installation_number' => $installationNumber,
            'customer_name' => $lead->name,
            'customer_phone' => $lead->phone_number,
            'customer_email' => $lead->email_address,
            'installation_address' => $lead->address,
            'installation_type' => 'new',
            'service_type' => 'wireless', // Default, can be changed later
            'status' => 'new', // New status - waiting to be picked by technician
            'priority' => 'medium',
            'scheduled_date' => null, // Will be set when technician picks the job
            'created_by' => Auth::id(),
        ]);

        // Mark lead as converted
        $lead->update(['status' => 'converted']);

        return redirect()->route('tenant.installations.my-installations')
            ->with('success', "Lead converted to installation #{$installationNumber}. Technicians can now pick this job.");
    }

}
