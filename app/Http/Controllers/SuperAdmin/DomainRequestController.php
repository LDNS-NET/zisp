<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DomainRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DomainRequestController extends Controller
{
    public function index()
    {
        $requests = DomainRequest::with('tenant')->latest()->paginate(10);
        return Inertia::render('SuperAdmin/DomainRequests/Index', [
            'requests' => $requests
        ]);
    }

    public function update(Request $request, DomainRequest $domainRequest)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,accepted,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string',
        ]);

        $domainRequest->update($validated);

        return back()->with('success', 'Domain request updated successfully.');
    }

    public function destroy(DomainRequest $domainRequest)
    {
        $domainRequest->delete();
        return back()->with('success', 'Domain request deleted successfully.');
    }
}
