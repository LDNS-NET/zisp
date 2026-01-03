<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AdminController extends Controller
{
    public function index()
    {
        // Assuming SuperAdmins have a specific role or flag. 
        // For now, let's assume users without a tenant_id are SuperAdmins.
        $admins = User::whereNull('tenant_id')->get();

        return Inertia::render('SuperAdmin/Admins/Index', [
            'admins' => $admins,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => null, // SuperAdmin
        ]);

        return back()->with('success', 'Admin created successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = User::whereNull('tenant_id')->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Rules\Password::defaults()],
            ]);
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        return back()->with('success', 'Admin updated successfully.');
    }

    public function destroy($id)
    {
        $user = User::whereNull('tenant_id')->findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return back()->with('success', 'Admin deleted successfully.');
    }
}
