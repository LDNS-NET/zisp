<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\Package;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $query = Voucher::query()->where('created_by', auth()->id())->latest();

        if ($search = $request->query('search')) {
            $query->where(fn($q) => $q->where('code', 'like', "%{$search}%")
                                        ->orWhere('name', 'like', "%{$search}%"));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $vouchers = $query->with('package')->paginate(10)->withQueryString();

        return Inertia::render('Vouchers/Index', [
            'vouchers'      => $vouchers,
            'voucherCount'  => Voucher::count(),
            'filters'       => $request->only('search', 'status', 'page'),
            'creating'      => $request->boolean('create'),
            'editing'       => $request->boolean('edit'),
            'voucherToEdit' => $request->boolean('edit') && $request->has('voucher_id')
                               ? Voucher::find($request->query('voucher_id'))
                               : null,
            'packages'      => Package::where('type', 'hotspot')->get(),
            'currency'      => auth()->user()?->tenant?->currency ?? 'KES',
            'flash'         => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'prefix'     => ['nullable', 'string', 'max:4'],
            'length'     => ['required', 'integer', 'min:6'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:1000'],
            'package_id' => ['required', 'exists:packages,id'],
        ]);

        $package = Package::where('type', 'hotspot')->findOrFail($validated['package_id']);
        
        // Calculate RADIUS attributes from Package
        $rateLimit = "{$package->upload_speed}M/{$package->download_speed}M";
        $sessionTimeout = $this->toSeconds($package->duration_value, $package->duration_unit);
        $deviceLimit = $package->device_limit ?? 1;

        $createdVouchers = [];

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $request->quantity; $i++) {
                $code = strtoupper(($request->prefix ?? '') . Str::random($request->length - strlen($request->prefix ?? '')));
                
                // Ensure uniqueness in DB just in case
                while (Voucher::where('code', $code)->exists()) {
                    $code = strtoupper(($request->prefix ?? '') . Str::random($request->length - strlen($request->prefix ?? '')));
                }

                $voucher = Voucher::create([
                    'code'       => $code,
                    'package_id' => $package->id,
                    'value'      => $package->price,
                    'status'     => 'active',
                    'expires_at' => null, // Stays valid until used, then Access-Period starts
                    'created_by' => auth()->id(),
                ]);

                Log::info("Voucher created in transaction: {$voucher->id} - Code: {$voucher->code}");

                // --- RADIUS Creation ---
                
                // 1. Authentication (Radcheck)
                \App\Models\Radius\Radcheck::create([
                    'username'  => $voucher->code,
                    'attribute' => 'Cleartext-Password',
                    'op'        => ':=',
                    'value'     => $voucher->code,
                ]);

                // 2. Limits (Radreply)
                // Rate Limit
                \App\Models\Radius\Radreply::create([
                    'username'  => $voucher->code,
                    'attribute' => 'Mikrotik-Rate-Limit',
                    'op'        => ':=',
                    'value'     => $rateLimit,
                ]);

                // Session Timeout (Duration)
                \App\Models\Radius\Radreply::create([
                    'username'  => $voucher->code,
                    'attribute' => 'Session-Timeout',
                    'op'        => ':=',
                    'value'     => (string)$sessionTimeout,
                ]);

                // Simultaneous Use (Devices)
                \App\Models\Radius\Radreply::create([
                    'username'  => $voucher->code,
                    'attribute' => 'Simultaneous-Use',
                    'op'        => ':=',
                    'value'     => (string)$deviceLimit,
                ]);
                
                $createdVouchers[] = $voucher->code;
            }
            
            DB::commit();
            Log::info("Voucher transaction committed. Count: " . count($createdVouchers));

            return redirect()->route('vouchers.index')
                             ->with('success', "{$request->quantity} vouchers created successfully.");

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to create vouchers: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            
            // Cleanup RADIUS if transaction failed
            foreach ($createdVouchers as $code) {
                try {
                    \App\Models\Radius\Radcheck::where('username', $code)->delete();
                    \App\Models\Radius\Radreply::where('username', $code)->delete();
                } catch (\Exception $ex) {
                    Log::warning("Failed to rollback voucher from RADIUS: {$code}");
                }
            }

            return redirect()->back()
                             ->with('error', "Failed to create vouchers. Error: {$e->getMessage()}");
        }
    }

    public function edit(Voucher $voucher)
    {
        $this->authorizeAccess($voucher);
        return redirect()->route('vouchers.index', ['edit' => true, 'voucher_id' => $voucher->id])
                         ->with('voucherToEdit', $voucher);
    }

    public function update(Request $request, Voucher $voucher)
    {
        $this->authorizeAccess($voucher);

        $validated = $request->validate([
            'code'        => ['sometimes', 'string', 'max:255', Rule::unique('vouchers', 'code')->ignore($voucher->id)],
            'status'      => ['sometimes', Rule::in(['active', 'used', 'expired', 'revoked'])],
            'note'        => ['nullable', 'string', 'max:1000'],
        ]);
        
        $oldStatus = $voucher->status;

        try {
            DB::transaction(function() use ($voucher, $validated, $oldStatus) {
                $voucher->update($validated);
                
                // Sync status with RADIUS
                if (isset($validated['status'])) {
                    if (in_array($validated['status'], ['revoked', 'expired']) && $oldStatus === 'active') {
                        // Disable user in RADIUS
                        \App\Models\Radius\Radcheck::where('username', $voucher->code)
                            ->where('attribute', 'Cleartext-Password')
                            ->delete();
                            
                    } elseif ($validated['status'] === 'active' && in_array($oldStatus, ['revoked', 'expired'])) {
                        // Re-enable user in RADIUS
                         $exists = \App\Models\Radius\Radcheck::where('username', $voucher->code)
                            ->where('attribute', 'Cleartext-Password')
                            ->exists();
                            
                         if (!$exists) {
                            \App\Models\Radius\Radcheck::create([
                                'username'  => $voucher->code,
                                'attribute' => 'Cleartext-Password',
                                'op'        => ':=',
                                'value'     => $voucher->code,
                            ]);
                         }
                    }
                }
            });

            return redirect()->route('vouchers.index')->with('success', 'Voucher updated successfully.');

        } catch (\Throwable $e) {
            Log::error("Failed to update voucher: {$e->getMessage()}", ['voucher_id' => $voucher->id]);
            return redirect()->route('vouchers.index', ['edit' => true, 'voucher_id' => $voucher->id])
                             ->with('error', 'Failed to update voucher. ' . $e->getMessage())
                             ->withInput();
        }
    }

    public function destroy(Voucher $voucher)
    {
        $this->authorizeAccess($voucher);
        
        // Check if used and not yet expired
        if ($voucher->status === 'used') {
            $networkUser = NetworkUser::where('username', $voucher->code)->first();
            if ($networkUser && $networkUser->expires_at && now()->lessThan($networkUser->expires_at)) {
                return redirect()->back()->with('error', 'This voucher is currently in use and has not yet expired. It cannot be deleted.');
            }
        }

        $code = $voucher->code;

        try {
            DB::transaction(function() use ($voucher, $code) {
                $voucher->delete();
                
                // Remove from RADIUS
                 \App\Models\Radius\Radcheck::where('username', $code)->delete();
                 \App\Models\Radius\Radreply::where('username', $code)->delete();
            });

            return redirect()->route('vouchers.index')->with('success', 'Voucher deleted successfully.');
        } catch (\Throwable $e) {
            Log::error("Failed to delete voucher: {$e->getMessage()}", ['voucher_id' => $voucher->id]);
            return redirect()->route('vouchers.index')->with('error', 'Failed to delete voucher. ' . $e->getMessage());
        }
    }

    public function send(Request $request, Voucher $voucher)
    {
        $this->authorizeAccess($voucher);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $voucher->update([
                'sent_to' => $validated['user_id'],
                'sent_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Voucher sent successfully.');
        } catch (\Throwable $e) {
            Log::error("Failed to send voucher: {$e->getMessage()}", ['trace' => $e->getTraceAsString(), 'voucher_id' => $voucher->id]);
            return redirect()->back()->with('error', 'Failed to send voucher. ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete vouchers
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:vouchers,id',
        ]);

        $vouchers = Voucher::whereIn('id', $request->ids)->get();
        
        // Filter out vouchers that are used and not expired
        $vouchersToDelete = $vouchers->filter(function ($voucher) {
            if ($voucher->status === 'used') {
                $networkUser = NetworkUser::where('username', $voucher->code)->first();
                if ($networkUser && $networkUser->expires_at && now()->lessThan($networkUser->expires_at)) {
                    return false;
                }
            }
            return true;
        });

        if ($vouchersToDelete->isEmpty()) {
            return redirect()->back()->with('error', 'None of the selected vouchers could be deleted (they are all currently in use and not yet expired).');
        }

        $idsToDelete = $vouchersToDelete->pluck('id')->toArray();
        $codesToDelete = $vouchersToDelete->pluck('code')->toArray();

        try {
            DB::beginTransaction();

            // Delete from RADIUS
            \App\Models\Radius\Radcheck::whereIn('username', $codesToDelete)->delete();
            \App\Models\Radius\Radreply::whereIn('username', $codesToDelete)->delete();

            // Delete from DB
            Voucher::whereIn('id', $idsToDelete)->delete();

            DB::commit();

            $skippedCount = $vouchers->count() - $vouchersToDelete->count();
            $message = $vouchersToDelete->count() . ' vouchers deleted successfully.';
            if ($skippedCount > 0) {
                $message .= " $skippedCount vouchers were skipped because they are active and unexpired.";
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Bulk delete vouchers failed: " . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete some vouchers.');
        }
    }

    protected function authorizeAccess(Voucher $voucher): void
    {
        if ($voucher->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this voucher.');
        }
    }

    protected function toSeconds(int $value, string $unit): int
    {
        return match ($unit) {
            'minutes' => $value * 60,
            'hours'   => $value * 3600,
            'days'    => $value * 86400,
            'weeks'   => $value * 604800,
            'months'  => $value * 2592000,
            default   => 0
        };
    }

    /**
     * Authenticate user with voucher code
     */
    public function authenticate(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'login_link' => ['required', 'string'], // Ensure user is connected to hotspot
        ]);

        try {
            // Extract subdomain to get current tenant
            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];
            
            // Find current tenant
            $currentTenant = \App\Models\Tenant::where('subdomain', $subdomain)->first();
            
            if (!$currentTenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid tenant domain.'
                ], 404);
            }

            // Find voucher
            $voucher = Voucher::where('code', $validated['code'])->first();

            if (!$voucher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid voucher code.'
                ], 404);
            }

            // CRITICAL: Verify voucher belongs to current tenant
            // Get the user who created the voucher and check their tenant
            $voucherCreator = \App\Models\User::find($voucher->created_by);
            
            if (!$voucherCreator || $voucherCreator->tenant_id !== $currentTenant->id) {
                Log::warning("Voucher cross-tenant usage attempt", [
                    'voucher_code' => $voucher->code,
                    'voucher_tenant_id' => $voucherCreator?->tenant_id,
                    'current_tenant_id' => $currentTenant->id,
                    'current_subdomain' => $subdomain
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'This voucher is not valid for this network.'
                ], 403);
            }

            // Handle Re-authentication for "used" vouchers
            if ($voucher->status === 'used') {
                $existingNetworkUser = NetworkUser::where('username', $voucher->code)
                    ->where('status', 'active')
                    ->first();

                if ($existingNetworkUser) {
                    // Check if the session is still valid
                    if ($existingNetworkUser->expires_at && now()->greaterThan($existingNetworkUser->expires_at)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This session has expired.'
                        ], 400);
                    }

                    Log::info("Voucher re-authentication successful", [
                        'code' => $voucher->code,
                        'user_id' => $existingNetworkUser->id,
                        'tenant_id' => $currentTenant->id
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Welcome back! You can reconnect to the hotspot.',
                        'user' => [
                            'username' => $existingNetworkUser->username,
                            'password' => $existingNetworkUser->password,
                            'expires_at' => $existingNetworkUser->expires_at,
                            'package_name' => $existingNetworkUser->package?->name ?? 'Hotspot Package',
                        ]
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'This voucher has already been used and no active session was found.'
                ], 400);
            }

            // Check voucher status for first-time use
            if ($voucher->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'This voucher is no longer active.'
                ], 400);
            }

            // Check if expired
            if ($voucher->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This voucher has expired.'
                ], 400);
            }

            // Verify voucher exists in RADIUS
            $radcheckExists = \App\Models\Radius\Radcheck::where('username', $voucher->code)
                ->where('attribute', 'Cleartext-Password')
                ->exists();

            if (!$radcheckExists) {
                Log::error("Voucher exists in DB but not in RADIUS", ['code' => $voucher->code]);
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher authentication failed. Please contact support.'
                ], 500);
            }

            // Get package details
            $package = $voucher->package;
            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher package not found.'
                ], 404);
            }

            // Calculate expiry based on package
            $expiresAt = now();
            if ($package->duration_unit && $package->duration_value) {
                $seconds = $this->toSeconds($package->duration_value, $package->duration_unit);
                $expiresAt = now()->addSeconds($seconds);
            }

            DB::beginTransaction();
            try {
                // Create network user with voucher code as username/password
                $networkUser = NetworkUser::create([
                    'username' => $voucher->code,
                    'password' => $voucher->code,
                    'full_name' => 'Voucher User - ' . $voucher->code,
                    'phone' => $request->input('phone', 'N/A'),
                    'type' => 'hotspot',
                    'package_id' => $package->id,
                    'status' => 'active',
                    'registered_at' => now(),
                    'expires_at' => $expiresAt,
                    'created_by' => $voucher->created_by,
                ]);

                // Mark voucher as used
                $voucher->update([
                    'status' => 'used',
                    'is_used' => true,
                    'used_by' => $networkUser->created_by,
                ]);

                // Record payment for tracking
                TenantPayment::create([
                    'user_id' => $networkUser->id,
                    'phone' => $request->input('phone', 'Voucher-Usage'),
                    'amount' => $voucher->value ?? 0,
                    'receipt_number' => 'VOUCHER-' . $voucher->code,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'checked' => true,
                    'disbursement_type' => 'true',
                    'created_by' => $voucher->created_by,
                    'package_id' => $package->id,
                ]);

                DB::commit();

                Log::info("Voucher authenticated successfully", [
                    'code' => $voucher->code,
                    'user_id' => $networkUser->id,
                    'package' => $package->name,
                    'tenant_id' => $currentTenant->id,
                    'subdomain' => $subdomain
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Voucher authenticated successfully! You can now connect to the hotspot.',
                    'user' => [
                        'username' => $networkUser->username,
                        'password' => $networkUser->password,
                        'expires_at' => $networkUser->expires_at,
                        'package_name' => $package->name,
                    ]
                ]);

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("Failed to authenticate voucher: {$e->getMessage()}", [
                    'code' => $voucher->code,
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to authenticate voucher. Please try again.'
                ], 500);
            }

        } catch (\Throwable $e) {
            Log::error("Voucher authentication error: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.'
            ], 500);
        }
    }



    /**
     * Print active vouchers (batch of 20)
     */
    public function print()
    {
        $vouchers = Voucher::where('status', 'active')
            ->latest()
            ->limit(20)
            ->with('package')
            ->get();

        $tenant = auth()->user()->tenant;
        $settings = \App\Models\TenantGeneralSetting::where('created_by', auth()->id())->first();
        $businessName = $settings?->business_name ?? $tenant?->name ?? 'Internet Service Provider';

        return view('vouchers.print', [
            'vouchers' => $vouchers,
            'businessName' => $businessName,
        ]);
    }
}
