<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantSMS;
use App\Models\Tenants\NetworkUser;
use App\Models\User;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\TenantSMSTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $totalTenants = User::count();
        $totalEndUsers = NetworkUser::count();
        $totalPayments = TenantPayment::sum('amount');
        $totalSMS = TenantSMS::count();

        $recentTenants = User::latest()->take(10)->get(['name', 'email', 'phone', 'created_at']);
        $recentSMS = TenantSMS::latest()->take(10)->get(['recipient_name', 'phone_number', 'message', 'status', 'created_at']);

        $recentActivity = collect()
            ->merge($recentTenants->map(fn($u)=>[
                'type' => 'tenant',
                'message' => "New User <b>{$u->name}</b> ({$u->email}, {$u->phone}) registered.",
                'time' => $u->created_at->diffForHumans(),
                'created_at' => $u->created_at,
            ]))
            ->merge($recentSMS->map(fn($s)=>[
                'type' => 'sms',
                'message' => "SMS sent to <b>{$s->recipient_name}</b> ({$s->phone_number}): {$s->message}",
                'time' => $s->created_at->diffForHumans(),
                'date' => $s->created_at,
            ]))
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        return Inertia::render('SuperAdmin/Dashboard/Index', [
            'totalTenants' => $totalTenants,
            'totalEndUsers' => $totalEndUsers,
            'totalPayments' => $totalPayments,
            'totalSMS' => $totalSMS,
            'recentActivity' => $recentActivity,
        ]);
    }
}