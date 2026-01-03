<?php

namespace App\Http\Controllers;

use App\Models\OnboardingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class OnboardingRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'isp_name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'message' => 'nullable|string',
        ]);

        OnboardingRequest::create($validated);

        return Redirect::back()->with('success', 'Your request has been submitted successfully. We will contact you soon!');
    }

    public function index()
    {
        $requests = OnboardingRequest::latest()->paginate(10);
        return inertia('SuperAdmin/OnboardingRequests/Index', [
            'requests' => $requests
        ]);
    }

    public function update(Request $request, OnboardingRequest $onboardingRequest)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,contacted,closed',
        ]);

        $onboardingRequest->update($validated);

        return Redirect::back()->with('success', 'Request status updated successfully.');
    }
}
