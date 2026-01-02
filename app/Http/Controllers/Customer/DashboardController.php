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
        
        // Ensure we have the latest user data
        $user = $user->fresh();
        
        $package = null;
        if ($user->type === 'hotspot') {
            $package = $user->hotspotPackage;
        } else {
            $package = $user->package;
            // Fallback: if relationship fails but ID exists, try finding it
            if (!$package && $user->package_id) {
                $package = \App\Models\Package::find($user->package_id);
            }
        }

        return Inertia::render('Customer/Dashboard', [
            'user' => $user,
            'package' => $package,
        ]);
    }
}
