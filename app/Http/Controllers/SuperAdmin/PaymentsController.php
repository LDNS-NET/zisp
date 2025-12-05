<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenant;
use App\Models\SuperAdmin\Payments;
use App\Models\Tenants\NetworkUser;


class PaymentsController extends Controller
{
    public function index() {
        $payments = Payments::with('tenant')->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('SuperAdmin/Payments/Index', [
            'payments' => $payments,
        ]);
    }
}
