<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\SuperAdminActivity;

class ImpersonationController extends Controller
{
    public function leave()
    {
        if (!session()->has('impersonated_by')) {
            return redirect()->route('dashboard');
        }

        $originalAdminId = session()->get('impersonated_by');
        $originalAdmin = User::find($originalAdminId);

        if (!$originalAdmin || $originalAdmin->role !== 'superadmin') {
            session()->forget('impersonated_by');
            return redirect()->route('login');
        }

        $impersonatedUser = auth()->user();

        // Log the activity
        SuperAdminActivity::log(
            'user.impersonation_ended',
            "Stopped impersonating tenant: {$impersonatedUser->name}",
            $impersonatedUser,
            ['admin_id' => $originalAdminId]
        );

        // Log back in as the superadmin
        Auth::login($originalAdmin);

        // Clear the session
        session()->forget('impersonated_by');

        return redirect()->route('superadmin.users.index')->with('success', 'Back to SuperAdmin area.');
    }
}
