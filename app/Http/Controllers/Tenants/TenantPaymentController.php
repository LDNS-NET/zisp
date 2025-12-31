<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantPayment;
use App\Models\Tenants\NetworkUser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Exports\TenantPaymentsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use IntaSend\IntaSendPHP\Collection;
use App\Jobs\CheckIntaSendPaymentStatus;

class TenantPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = TenantPayment::query()->with('user');

        // 🔍 Search filter
        if ($request->search) {
            $query->whereHas(
                'user',
                fn($q) =>
                $q->where('username', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%")
            )->orWhere('phone', 'like', "%{$request->search}%");
        }

        // ✅ Disbursement filter
        if ($request->disbursement) {
            if ($request->disbursement === 'pending') {
                $query->where(function ($q) {
                    $q->whereNull('disbursement_type')
                        ->orWhere('disbursement_type', '')
                        ->orWhere('disbursement_type', 'pending');
                });
            } else {
                $query->where('disbursement_type', $request->disbursement);
            }
        }

        $businessName = \App\Models\Tenant::first()?->business_name ?? '';
        $payments = $query->latest()->paginate(10)->through(function ($payment) use ($businessName) {
            $disb = $payment->disbursement_type ?? 'pending';
            $checkedBool = (bool) $payment->checked;
            $userDisplay = $payment->user_id === null ? 'Deleted User' : ($payment->user?->username ?? 'Unknown');
            return [
                'id' => $payment->id,
                'user' => $userDisplay,
                'user_id' => $payment->user_id,
                'phone' => $payment->phone ?? ($payment->user?->phone ?? 'N/A'),
                'receipt_number' => $payment->receipt_number,
                'amount' => $payment->amount,
                'checked' => $checkedBool,
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                'disbursement_type' => $disb,
                'checked_label' => $checkedBool ? 'Yes' : 'No',
                'disbursement_label' => ucfirst($disb),
                'business_name' => $businessName,
            ];
        });

        // Get all payments for summary (no pagination)
        // Get all payments for summary (no pagination, ignore pagination and filters except search/disbursement)
        $allPayments = TenantPayment::query()->with('user')
            ->when($request->search, function ($q) use ($request) {
                $q->whereHas(
                    'user',
                    fn($q2) =>
                    $q2->where('username', 'like', "%{$request->search}%")
                        ->orWhere('phone', 'like', "%{$request->search}%")
                )->orWhere('phone', 'like', "%{$request->search}%");
            })
            ->when($request->disbursement, function ($q) use ($request) {
                if ($request->disbursement === 'pending') {
                    $q->where(function ($q2) {
                        $q2->whereNull('disbursement_type')
                            ->orWhere('disbursement_type', '')
                            ->orWhere('disbursement_type', 'pending');
                    });
                } else {
                    $q->where('disbursement_type', $request->disbursement);
                }
            })
            ->get()->map(function ($payment) use ($businessName) {
                $disb = $payment->disbursement_type ?? 'pending';
                $checkedBool = (bool) $payment->checked;
                return [
                    'id' => $payment->id,
                    'user' => $payment->user?->username ?? 'Unknown',
                    'user_id' => $payment->user_id,
                    'phone' => $payment->phone ?? ($payment->user?->phone ?? 'N/A'),
                    'receipt_number' => $payment->receipt_number,
                    'amount' => $payment->amount,
                    'checked' => $checkedBool,
                    'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                    'disbursement_type' => $disb,
                    'checked_label' => $checkedBool ? 'Yes' : 'No',
                    'disbursement_label' => ucfirst($disb),
                    'business_name' => $businessName,
                ];
            });

        return Inertia::render('Payments/Index', [
            'payments' => array_merge($payments->toArray(), ['allData' => $allPayments]),
            'filters' => $request->only('search', 'disbursement'),
            'users' => NetworkUser::select('id', 'username', 'phone')->get(),
            'currency' => auth()->user()?->tenant?->currency ?? 'KES',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:network_users,id',
            'receipt_number' => 'required|string|max:255|unique:tenant_payments,receipt_number',
            'amount' => 'required|numeric|min:0',
            'checked' => 'required|boolean',
            'paid_at' => 'required|date',
            'disbursement_type' => 'required|string|in:pending,disbursed,withheld',
        ]);

        $user = NetworkUser::findOrFail($data['user_id']);

        if (Schema::hasColumn('tenant_payments', 'phone')) {
            $data['phone'] = $user->phone;
        }

        if (Schema::hasColumn('tenant_payments', 'created_by')) {
            $data['created_by'] = auth()->id();
        }

        $payment = TenantPayment::create($data);

        // 🔥 Load tenant router config from DB, not strings
        $tenantMikrotik = \App\Models\Tenants\TenantMikrotik::where('created_by', auth()->id())->first();

        if ($tenantMikrotik) {
            $mikrotik = new \App\Services\MikrotikService($tenantMikrotik);

            // Suspend/unsuspend based on disbursement status
            if (in_array($data['disbursement_type'], ['pending', 'withheld'])) {
                $mikrotik->suspendUser($user->type, $user->mikrotik_id ?? '');
            } elseif ($data['disbursement_type'] === 'disbursed') {
                $mikrotik->unsuspendUser($user->type, $user->mikrotik_id ?? '');
            }
        }

        $this->sendPaymentSMS($payment);

        return back()->with('success', 'Payment added and SMS sent.');
    }


    public function update(Request $request, $id)
    {
        $tenantPayment = TenantPayment::findOrFail($id);

        $data = $request->validate([
            'user_id' => 'sometimes|exists:network_users,id',
            'receipt_number' => 'required|string|max:255|unique:tenant_payments,receipt_number,' . $id,
            'amount' => 'required|numeric|min:0',
            'checked' => 'required|boolean',
            'paid_at' => 'required|date',
            'disbursement_type' => 'required|string|in:pending,disbursed,withheld',
        ]);

        if (isset($data['user_id'])) {
            $user = NetworkUser::findOrFail($data['user_id']);
            if (Schema::hasColumn('tenant_payments', 'phone')) {
                $data['phone'] = $user->phone;
            }
        }

        $tenantPayment->update($data);

        // Mikrotik suspend/unsuspend logic
        $user = isset($data['user_id']) ? NetworkUser::findOrFail($data['user_id']) : $tenantPayment->user;

        // Load tenant router config from DB
        $tenantMikrotik = \App\Models\Tenants\TenantMikrotik::where('created_by', auth()->id())->first();

        if ($tenantMikrotik) {
            $mikrotik = new \App\Services\MikrotikService($tenantMikrotik);

            if (in_array($data['disbursement_type'], ['pending', 'withheld'])) {
                $mikrotik->suspendUser($user->type, $user->mikrotik_id ?? '');
            } elseif ($data['disbursement_type'] === 'disbursed') {
                $mikrotik->unsuspendUser($user->type, $user->mikrotik_id ?? '');
            }
        }

        return back()->with('success', 'Payment updated.');
    }

    public function destroy($id)
    {
        $tenantPayment = TenantPayment::findOrFail($id);
        $tenantPayment->delete();

        return back()->with('success', 'Payment deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', 'No payments selected for deletion.');
        }
        TenantPayment::whereIn('id', $ids)->delete();
        return back()->with('success', 'Selected payments deleted successfully.');
    }

    public function export(Request $request)
    {
        $format = $request->get('format', 'excel');

        return match ($format) {
            default => Excel::download(new TenantPaymentsExport, 'tenant_payments.xlsx'),
        };
    }

    private function sendPaymentSMS($payment)
    {
        $to = $payment->phone ?? $payment->user?->phone;

        if ($to) {
            try {
                Http::post('https://api.talksasa.com/send', [
                    'to' => $to,
                    'message' => "Payment received: Receipt #{$payment->receipt_number}. Thank you.",
                    'apiKey' => env('TALKSASA_API_KEY'),
                    'from' => config('app.name'),
                ]);
            } catch (\Exception $e) {
                logger()->error('SMS failed: ' . $e->getMessage());
            }
        }
    }

    public function processSTKPush(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|regex:/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8})$/',
            'package_id' => 'required|exists:packages,id',
            'amount' => 'required|numeric|min:1',
        ]);

        // Normalize phone number to 2547 format
        $phone = $data['phone'];
        if (str_starts_with($phone, '01')) {
            $phone = '2547' . substr($phone, 2);
        } elseif (str_starts_with($phone, '07')) {
            $phone = '2547' . substr($phone, 2);
        } elseif (str_starts_with($phone, '254')) {
            if (strlen($phone) === 12 && $phone[3] === '7') {
                // Already 2547XXXXXXXX format - no transformation needed
            } elseif (strlen($phone) === 12) {
                // 254XXXXXXXX format, convert to 2547XXXXXXXX
                $phone = '2547' . substr($phone, 3);
            }
        }

        $package = \App\Models\Package::findOrFail($data['package_id']);

        // Generate unique receipt number
        $receiptNumber = 'HS-' . strtoupper(uniqid()) . '-' . date('Ymd');

        try {
            // Log the attempt
            \Log::info('IntaSend STK Push attempt', [
                'phone' => $phone,
                'amount' => $data['amount'],
                'api_ref' => $receiptNumber
            ]);

            $collection = new Collection();
            $collection->init([
                'token' => env('INTASEND_SECRET_KEY'),
                'publishable_key' => env('INTASEND_PUBLISHABLE_KEY'),
                'test' => env('INTASEND_TEST_ENV', false),
            ]);

            \Log::info('IntaSend collection initialized', [
                'token_set' => !empty(env('INTASEND_SECRET_KEY')),
                'publishable_key_set' => !empty(env('INTASEND_PUBLISHABLE_KEY')),
                'test_env' => env('INTASEND_TEST_ENV', false)
            ]);

            $response = $collection->create(
                amount: $data['amount'],
                phone_number: $phone,
                currency: 'KES',
                method: 'MPESA_STK_PUSH',
                api_ref: $receiptNumber
            );

            $resp = json_decode(json_encode($response), true);

            \Log::info('IntaSend response received', ['response' => $resp]);

            // Create payment record with pending status ONLY after successful request
            $payment = TenantPayment::create([
                'phone' => $phone, // Store normalized format
                'package_id' => $data['package_id'],
                'amount' => $data['amount'],
                'receipt_number' => $receiptNumber,
                'status' => 'pending',
                'checked' => false,
                'disbursement_type' => 'pending',
                'intasend_reference' => $resp['invoice']['id'] ?? $resp['id'] ?? null,
                'intasend_checkout_id' => $resp['invoice']['checkout_id'] ?? $resp['checkout_id'] ?? null,
                'response' => $resp,
            ]);

            // Dispatch job to check payment status
            if (!empty($resp['invoice']['id']) || !empty($resp['id'])) {
                $invoiceId = $resp['invoice']['id'] ?? $resp['id'];
                CheckIntaSendPaymentStatus::dispatch($invoiceId)
                    ->delay(now()->addSeconds(30));
            }

            return response()->json([
                'success' => true,
                'message' => 'STK Push sent successfully. Please check your phone.',
                'payment_id' => $payment->id,
                'receipt_number' => $receiptNumber,
                'response' => $resp,
            ]);

        } catch (\Exception $e) {
            \Log::error('IntaSend STK Push failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'phone' => $phone,
                'amount' => $data['amount']
            ]);

            // Do NOT create payment record on failure
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate STK Push: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update payment status from IntaSend callback
     */
    public function updatePaymentStatus($receiptNumber, $status, $responseData = [])
    {
        $payment = TenantPayment::where('receipt_number', $receiptNumber)->first();

        if (!$payment) {
            return false;
        }

        $updateData = [
            'response' => array_merge($payment->response ?? [], $responseData),
        ];

        if ($status === 'SUCCESS') {
            $updateData['status'] = 'paid';
            $updateData['paid_at'] = now();
        } else {
            $updateData['status'] = 'failed';
        }

        return $payment->update($updateData);
    }
}
