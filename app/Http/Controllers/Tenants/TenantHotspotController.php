<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\TenantHotspot;
use App\Http\Requests\StoreTenantHotspotRequest;
use App\Http\Requests\UpdateTenantHotspotRequest;
use App\Models\Package;
use App\Models\Tenants\TenantPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Tenants\TenantPaymentController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TenantHotspotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $packages = Package::where('type', 'hotspot')
            ->orderBy('name')
            ->get();

        return Inertia::render('Hotspot/Index', [
            'packages' => $packages,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTenantHotspotRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TenantHotspot $tenantHotspot)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TenantHotspot $tenantHotspot)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTenantHotspotRequest $request, TenantHotspot $tenantHotspot)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TenantHotspot $tenantHotspot)
    {
        //
    }

    /**
     * Process hotspot package purchase with STK Push
     */
    public function purchaseSTKPush(Request $request)
    {
        $data = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'phone' => 'required|string|regex:/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/',
        ]);

        $package = Package::findOrFail($data['package_id']);

        // Forward to TenantPaymentController for processing
        $paymentController = new TenantPaymentController();
        $request->merge([
            'phone' => $data['phone'],
            'package_id' => $data['package_id'],
            'amount' => $package->price,
        ]);

        return $paymentController->processSTKPush($request);
    }

    /**
     * Process IntaSend checkout for hotspot packages
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'phone' => 'required|string|regex:/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/',
            'email' => 'nullable|email',
        ]);

        // Get package details
        $package = Package::findOrFail($data['package_id']);
        
        // Normalize phone number to Kenya MSISDN format
        $phone = $data['phone'];
        if (str_starts_with($phone, '01')) {
            $phone = '2547' . substr($phone, 2);
        } elseif (str_starts_with($phone, '07')) {
            $phone = '2547' . substr($phone, 2);
        } elseif (str_starts_with($phone, '254')) {
            if (strlen($phone) === 12 && in_array($phone[3], ['0', '1', '7'])) {
                $phone = '2547' . substr($phone, 4);
            } elseif (strlen($phone) === 12) {
                $phone = '2547' . substr($phone, 3);
            }
        }

        // Generate order ID
        $orderId = 'HS-' . strtoupper(Str::random(8)) . '-' . time();

        try {
            // Create payment record
            $payment = TenantPayment::create([
                'phone' => $phone,
                'package_id' => $data['package_id'],
                'amount' => $package->price,
                'receipt_number' => $orderId,
                'status' => 'pending',
                'checked' => false,
                'disbursement_type' => 'pending',
                'response' => null,
            ]);

            // Prepare IntaSend API request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.intasend.secret_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.intasend.com/api/v1/checkout/', [
                'phone_number' => $phone,
                'email' => $data['email'] ?? 'customer@example.com',
                'amount' => $package->price,
                'currency' => 'KES',
                'api_ref' => $orderId,
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                // Update payment record with IntaSend response
                $payment->update([
                    'response' => $responseData,
                    'intasend_reference' => $responseData['id'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'STK Push sent successfully',
                    'order_id' => $orderId,
                    'response' => $responseData,
                ]);
            } else {
                // Update payment record with error
                $payment->update([
                    'status' => 'failed',
                    'response' => $responseData,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send STK Push: ' . ($responseData['message'] ?? 'Unknown error'),
                    'response' => $responseData,
                ], 400);
            }

        } catch (\Exception $e) {
            // Update payment record with error
            if (isset($payment)) {
                $payment->update([
                    'status' => 'failed',
                    'response' => ['error' => $e->getMessage()],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * IntaSend callback handler
     */
    public function callback(Request $request)
    {
        $data = $request->all();
        
        // Log callback for debugging
        \Log::info('IntaSend callback received', ['data' => $data]);

        // Find payment by API reference
        $apiRef = $data['api_ref'] ?? null;
        $status = $data['status'] ?? null;
        
        if (!$apiRef || !$status) {
            return response()->json(['error' => 'Missing required fields'], 400);
        }

        $payment = TenantPayment::where('receipt_number', $apiRef)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Update payment status based on callback
        if ($status === 'SUCCESS') {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'response' => array_merge($payment->response ?? [], $data),
            ]);
        } else {
            $payment->update([
                'status' => 'failed',
                'response' => array_merge($payment->response ?? [], $data),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
