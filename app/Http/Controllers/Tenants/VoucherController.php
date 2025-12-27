<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Mikrotik\HotspotUserService;

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

        $vouchers = $query->paginate(10)->withQueryString();

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
        $mikrotikUser = app(HotspotUserService::class);
        $mikrotikProfile = app(\App\Services\Mikrotik\HotspotProfileService::class);
        $createdVouchers = [];

        DB::beginTransaction();
        try {
            // Ensure the profile exists on MikroTik before creating users
            // This is the "Lazy Sync" step
            $mikrotikProfile->syncFromPackage($package);

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
                    'expires_at' => now()->addDays($package->duration_in_days ?? 30),
                    'created_by' => auth()->id(),
                ]);

                // We'll use the code as username and password for simplicity as per requirements
                $mikrotikUser->create([
                    'username' => $voucher->code,
                    'password' => $voucher->code,
                    'profile'  => $package->mikrotik_profile,
                    'comment'  => "Voucher {$voucher->code}",
                    'limit-uptime' => $package->duration_unit === 'minutes' ? $package->duration_value . 'm' : 
                                     ($package->duration_unit === 'hours' ? $package->duration_value . 'h' : 
                                     ($package->duration_unit === 'days' ? $package->duration_value . 'd' : null))
                ]);
                
                $createdVouchers[] = $voucher->code;
            }
            
            DB::commit();

            return redirect()->route('vouchers.index')
                             ->with('success', "{$request->quantity} vouchers created successfully.");

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to create vouchers: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            
            // Compensation: Remove any vouchers created on MikroTik before the error
            foreach ($createdVouchers as $code) {
                try {
                    $mikrotikUser->remove($code);
                } catch (\Exception $ex) {
                    Log::warning("Failed to rollback voucher from MikroTik: {$code}");
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
                
                $mikrotik = app(HotspotUserService::class);
                
                // Sync status with MikroTik
                if (isset($validated['status'])) {
                    if (in_array($validated['status'], ['revoked', 'expired']) && $oldStatus === 'active') {
                        $mikrotik->remove($voucher->code);
                    } elseif ($validated['status'] === 'active' && in_array($oldStatus, ['revoked', 'expired'])) {
                        // Re-create user if reactivating
                         $mikrotik->create([
                            'username' => $voucher->code,
                            'password' => $voucher->code,
                            'profile'  => $voucher->package->mikrotik_profile,
                            'comment'  => "Voucher {$voucher->code}",
                        ]);
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
        $code = $voucher->code;

        try {
            DB::transaction(function() use ($voucher) {
                $voucher->delete();
            });
            
            // Remove from MikroTik
            try {
                app(HotspotUserService::class)->remove($code);
            } catch (\Exception $e) {
                 Log::warning("Failed to remove voucher from MikroTik during deletion: {$code}");
                 // Proceed without erroring the user request, but log it.
            }

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
            Log::error("Failed to send voucher: {$e->getMessage()}", ['voucher_id' => $voucher->id]);
            return redirect()->back()->with('error', 'Failed to send voucher. ' . $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:vouchers,id',
        ]);
        
        $vouchers = Voucher::whereIn('id', $request->ids)->get();
        $mikrotik = app(HotspotUserService::class);

        foreach ($vouchers as $voucher) {
            try {
                $mikrotik->remove($voucher->code);
            } catch (\Exception $e) {
                 Log::warning("Failed to remove voucher from MikroTik during bulk deletion: {$voucher->code}");
            }
        }

        Voucher::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', 'Selected vouchers deleted successfully.');
    }

    protected function authorizeAccess(Voucher $voucher): void
    {
        if ($voucher->created_by !== auth()->id()) {
            abort(403, 'Unauthorized. You do not own this voucher.');
        }
    }
}
