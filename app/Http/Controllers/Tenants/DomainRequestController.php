<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\DomainRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DomainRequestController extends Controller
{
    public function index()
    {
        $tenantId = tenant('id');
        $requests = DomainRequest::where('tenant_id', $tenantId)->latest()->paginate(10);
        
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

        $validated['tenant_id'] = tenant('id');
        $validated['status'] = 'pending';

        DomainRequest::create($validated);

        return back()->with('success', 'Domain request submitted successfully.');
    }
}
