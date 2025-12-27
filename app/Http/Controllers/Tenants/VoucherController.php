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
        $mikrotik = app(HotspotUserService::class);

        DB::transaction(function () use ($request, $package, $mikrotik) {
            for ($i = 0; $i < $request->quantity; $i++) {
                $code = strtoupper(($request->prefix ?? '') . Str::random($request->length - strlen($request->prefix ?? '')));

                $voucher = Voucher::create([
                    'code'       => $code,
                    'username'   => $code,
                    'password'   => $code,
                    'package_id' => $package->id,
                    'profile'    => $package->mikrotik_profile,
                    'value'      => $package->price,
                    'expires_at' => now()->addDays($package->duration_in_days ?? 30),
                    'created_by' => auth()->id(),
                ]);

                $mikrotik->createUser([
                    'username' => $voucher->username,
                    'password' => $voucher->password,
                    'profile'  => $voucher->profile,
                ]);
            }
        });

        return redirect()->route('vouchers.index')
                         ->with('success', "{$request->quantity} vouchers created successfully.");
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
            'name'        => ['sometimes', 'string', 'max:255'],
            'type'        => ['sometimes', Rule::in(['percentage', 'fixed'])],
            'value'       => ['sometimes', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at'  => ['nullable', 'date'],
            'status'      => ['sometimes', Rule::in(['active', 'used', 'expired', 'revoked'])],
            'is_active'   => ['sometimes', 'boolean'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $voucher->update($validated);
            return redirect()->route('vouchers.index')->with('success', 'Voucher updated successfully.');
        } catch (\Throwable $e) {
            Log::error("Failed to update voucher: {$e->getMessage()}", ['trace' => $e->getTraceAsString(), 'voucher_id' => $voucher->id]);
            return redirect()->route('vouchers.index', ['edit' => true, 'voucher_id' => $voucher->id])
                             ->with('error', 'Failed to update voucher. ' . $e->getMessage())
                             ->withInput();
        }
    }

    public function destroy(Voucher $voucher)
    {
        $this->authorizeAccess($voucher);

        try {
            $voucher->delete();
            return redirect()->route('vouchers.index')->with('success', 'Voucher deleted successfully.');
        } catch (\Throwable $e) {
            Log::error("Failed to delete voucher: {$e->getMessage()}", ['trace' => $e->getTraceAsString(), 'voucher_id' => $voucher->id]);
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

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:vouchers,id',
        ]);

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
