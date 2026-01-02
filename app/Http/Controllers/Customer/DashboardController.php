<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::guard('customer')->user();
        $user->load(['package', 'hotspotPackage']);

        return Inertia::render('Customer/Dashboard', [
            'user' => $user,
            'package' => $user->type === 'hotspot' ? $user->hotspotPackage : $user->package,
        ]);
    }
}
