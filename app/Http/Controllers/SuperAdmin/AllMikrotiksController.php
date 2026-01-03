<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\TenantMikroTik;
use inertia\Inertia;

class AllMikrotiksController extends Controller
{
    public function index(Request $request)
    {
        $query = TenantMikroTik::withoutGlobalScopes();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('public_ip', 'like', "%{$search}%")
                  ->orWhere('wireguard_address', 'like', "%{$search}%");
            });
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $mikrotiks = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('SuperAdmin/Allmikrotiks/Index', [
            'mikrotiks' => $mikrotiks,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    //This is the Show function
    public function show($id) {
        $mikrotik = TenantMikroTik::withoutGlobalScopes()->with('tenant')->findOrFail($id);

        return Inertia::render('SuperAdmin/Allmikrotiks/Show', [
            'mikrotik' => $mikrotik,
        ]);
    }

    public function destroy($id)
    {
        $mikrotik = TenantMikroTik::withoutGlobalScopes()->findOrFail($id);
        $mikrotik->delete();

        return redirect()->route('superadmin.allmikrotiks.index')->with('success', 'Router deleted successfully.');
    }
}
