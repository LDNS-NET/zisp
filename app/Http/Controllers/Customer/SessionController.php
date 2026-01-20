<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Display connection history for the customer
     */
    public function index()
    {
        $user = Auth::guard('customer')->user();

        $sessions = DB::connection('radius')
            ->table('radacct')
            ->where('username', $user->username)
            ->orderBy('acctstarttime', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->radacctid,
                    'start_time' => $session->acctstarttime,
                    'stop_time' => $session->acctstoptime,
                    'duration' => $session->acctsessiontime,
                    'upload' => round($session->acctinputoctets / (1024 * 1024), 2), // MB
                    'download' => round($session->acctoutputoctets / (1024 * 1024), 2), // MB
                    'ip_address' => $session->framedipaddress,
                    'mac_address' => $session->callingstationid,
                ];
            });

        return Inertia::render('Customer/History', [
            'sessions' => $sessions,
        ]);
    }
}
