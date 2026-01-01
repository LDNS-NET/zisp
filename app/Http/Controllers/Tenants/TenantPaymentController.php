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
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $businessName = $user->tenant?->name ?? 'ISP';
        
        $payments = TenantPayment::query()
            ->where('tenant_id', $tenantId)
            ->with('user')
            ->when($request->search, function ($q) use ($request) {
                $q->where(function($sub) use ($request) {
                    $sub->whereHas(
                        'user',
                        fn($q2) =>
                        $q2->where('username', 'like', "%{$request->search}%")
                            ->orWhere('phone', 'like', "%{$request->search}%")
                    )->orWhere('phone', 'like', "%{$request->search}%");
                });
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
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(function ($payment) use ($businessName) {
                $disb = $payment->disbursement_type ?? 'pending';
                $status = $payment->disbursement_status ?? 'pending';
                $checkedBool = (bool) $payment->checked;
                $userDisplay = $payment->user?->username ?? ($payment->user_id === null ? 'System/Manual' : 'Deleted User');
                
                // Manual payments are those created by a user and not via automated methods
                $isManual = ($payment->payment_method === 'manual' || ($payment->created_by && !$payment->payment_method));
                
                return [
                    'id' => $payment->id,
                    'user' => $userDisplay,
                    'user_id' => $payment->user_id,
                    'phone' => $payment->phone ?? ($payment->user?->phone ?? 'N/A'),
                    'receipt_number' => $payment->mpesa_receipt_number ?: $payment->receipt_number,
                    'amount' => $payment->amount,
                    'checked' => $checkedBool,
                    'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                    'disbursement_type' => $disb,
                    'disbursement_status' => $status,
                    'is_manual' => $isManual,
                    'editable' => $isManual,
                    'checked_label' => $checkedBool ? 'Yes' : 'No',
                    'disbursement_label' => $status === 'testing' ? 'Testing Mode' : ucfirst($status),
                    'business_name' => $businessName,
                ];
            });

        // Get all payments for summary (no pagination, ignore pagination and filters except search/disbursement)
        $allPayments = TenantPayment::query()
            ->where('tenant_id', $tenantId)
            ->with('user')
            ->when($request->search, function ($q) use ($request) {
                $q->where(function($sub) use ($request) {
                    $sub->whereHas(
                        'user',
                        fn($q2) =>
                        $q2->where('username', 'like', "%{$request->search}%")
                            ->orWhere('phone', 'like', "%{$request->search}%")
                    )->orWhere('phone', 'like', "%{$request->search}%");
                });
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
                $status = $payment->disbursement_status ?? 'pending';
                $checkedBool = (bool) $payment->checked;
                
                $isManual = ($payment->payment_method === 'manual' || ($payment->created_by && !$payment->payment_method));
                
                return [
                    'id' => $payment->id,
                    'user' => $payment->user?->username ?? ($payment->user_id === null ? 'System/Manual' : 'Deleted User'),
                    'user_id' => $payment->user_id,
                    'phone' => $payment->phone ?? ($payment->user?->phone ?? 'N/A'),
                    'receipt_number' => $payment->mpesa_receipt_number ?: $payment->receipt_number,
                    'amount' => $payment->amount,
                    'checked' => $checkedBool,
                    'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                    'disbursement_type' => $disb,
                    'disbursement_status' => $status,
                    'is_manual' => $isManual,
                    'editable' => $isManual,
                    'checked_label' => $checkedBool ? 'Yes' : 'No',
                    'disbursement_label' => $status === 'testing' ? 'Testing Mode' : ucfirst($status),
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
            'disbursement_status' => 'required|string|in:pending,processing,completed,failed',
        ]);

        $user = NetworkUser::findOrFail($data['user_id']);

        if (Schema::hasColumn('tenant_payments', 'phone')) {
            $data['phone'] = $user->phone;
        }

        if (Schema::hasColumn('tenant_payments', 'created_by')) {
            $data['created_by'] = auth()->id();
        }

        $data['tenant_id'] = tenant('id');
        $data['payment_method'] = 'manual';
        $data['disbursement_status'] = 'completed';
        $payment = TenantPayment::create($data);

        // 🔥 Load tenant router config from DB, not strings
        $tenantMikrotik = \App\Models\Tenants\TenantMikrotik::where('created_by', auth()->id())->first();

        if ($tenantMikrotik) {
            $mikrotik = new \App\Services\MikrotikService($tenantMikrotik);

            // Suspend/unsuspend based on disbursement status
            if (in_array($data['disbursement_status'], ['pending', 'failed'])) {
                $mikrotik->suspendUser($user->type, $user->mikrotik_id ?? '');
            } elseif ($data['disbursement_status'] === 'completed') {
                $mikrotik->unsuspendUser($user->type, $user->mikrotik_id ?? '');
            }
        }

        $this->sendPaymentSMS($payment);

        return back()->with('success', 'Payment added and SMS sent.');
    }


    public function update(Request $request, $id)
    {
        $tenantPayment = TenantPayment::findOrFail($id);

        // Security check: Only manual payments can be edited
        $isManual = ($tenantPayment->payment_method === 'manual' || ($tenantPayment->created_by && !$tenantPayment->payment_method));
        abort_unless($isManual, 403, 'System payments cannot be edited.');

        $data = $request->validate([
            'user_id' => 'sometimes|exists:network_users,id',
            'receipt_number' => 'required|string|max:255|unique:tenant_payments,receipt_number,' . $id,
            'amount' => 'required|numeric|min:0',
            'checked' => 'required|boolean',
            'paid_at' => 'required|date',
            'disbursement_status' => 'required|string|in:pending,processing,completed,failed',
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

            if (in_array($data['disbursement_status'], ['pending', 'failed'])) {
                $mikrotik->suspendUser($user->type, $user->mikrotik_id ?? '');
            } elseif ($data['disbursement_status'] === 'completed') {
                $mikrotik->unsuspendUser($user->type, $user->mikrotik_id ?? '');
            }
        }

        return back()->with('success', 'Payment updated.');
    }

    public function destroy($id)
    {
        $tenantPayment = TenantPayment::findOrFail($id);

        // Security check: Only manual payments can be deleted
        $isManual = ($tenantPayment->payment_method === 'manual' || ($tenantPayment->created_by && !$tenantPayment->payment_method));
        abort_unless($isManual, 403, 'System payments cannot be deleted.');

        $tenantPayment->delete();

        return back()->with('success', 'Payment deleted.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', 'No payments selected for deletion.');
        }
        
        // Only delete manual payments
        TenantPayment::whereIn('id', $ids)
            ->where(function($q) {
                $q->where('payment_method', 'manual')
                  ->orWhere(function($q2) {
                      $q2->whereNotNull('created_by')
                         ->whereNull('payment_method');
                  });
            })
            ->delete();

        return back()->with('success', 'Selected manual payments deleted successfully.');
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

        try {
            // Create unique receipt number
            $receiptNumber = 'PK-' . strtoupper(uniqid()) . '-' . date('Ymd');

            // Resolve M-Pesa credentials for this tenant
            $tenantId = $request->tenant_id ?? tenant('id');
            $gateway = \App\Models\TenantPaymentGateway::where('tenant_id', $tenantId)
                ->where('provider', 'mpesa')
                ->where('use_own_api', true)
                ->where('is_active', true)
                ->first();

            $mpesa = app(\App\Services\MpesaService::class);
            
            if ($gateway) {
                $mpesa->setCredentials([
                    'consumer_key' => $gateway->mpesa_consumer_key,
                    'consumer_secret' => $gateway->mpesa_consumer_secret,
                    'shortcode' => $gateway->mpesa_shortcode,
                    'passkey' => $gateway->mpesa_passkey,
                    'environment' => $gateway->mpesa_env,
                ]);
            }

            // Initiate M-Pesa STK Push
            $mpesaResponse = $mpesa->stkPush(
                $phone,
                $data['amount'],
                $receiptNumber,
                "Package Payment - {$package->name}"
            );

            \Log::info('M-Pesa STK Push initiated', [
                'response' => $mpesaResponse,
                'receipt_number' => $receiptNumber,
                'using_custom_api' => (bool)$gateway,
            ]);

            if (!$mpesaResponse['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $mpesaResponse['message'] ?? 'Failed to initiate payment',
                ]);
            }

            // Create payment record immediately with pending status
            $payment = TenantPayment::create([
                'phone' => $phone,
                'package_id' => $data['package_id'],
                'hotspot_package_id' => null,
                'amount' => $data['amount'],
                'currency' => 'KES',
                'payment_method' => 'mpesa',
                'receipt_number' => $receiptNumber,
                'status' => 'pending',
                'checked' => false,
                'disbursement_type' => 'pending',
                'disbursement_status' => $gateway 
                    ? ($gateway->mpesa_env === 'sandbox' ? 'testing' : 'completed') 
                    : (config('mpesa.environment') === 'sandbox' ? 'testing' : 'pending'),
                'checkout_request_id' => $mpesaResponse['checkout_request_id'] ?? null,
                'merchant_request_id' => $mpesaResponse['merchant_request_id'] ?? null,
                'intasend_reference' => null, // Legacy field, keeping null
                'intasend_checkout_id' => null, // Legacy field, keeping null
                'response' => $mpesaResponse['response'] ?? [],
                'tenant_id' => $tenantId,
            ]);

            \Log::info('Payment record created', [
                'payment_id' => $payment->id,
                'receipt_number' => $receiptNumber,
                'checkout_request_id' => $payment->checkout_request_id,
            ]);

            // Dispatch job to check payment status (using payment model)
            \App\Jobs\CheckMpesaPaymentStatusJob::dispatch($payment)
                ->delay(now()->addSeconds(30));

            return response()->json([
                'success' => true,
                'message' => 'STK Push sent successfully. Please check your phone.',
                'payment_id' => $payment->id,
                'receipt_number' => $receiptNumber,
                'response' => $mpesaResponse,
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
