<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Package;
use App\Models\Tenants\TenantPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\Radius\Radacct;
class TenantUserController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->get('type', 'all');

        $query = NetworkUser::query()
            ->with('package')
            ->when($type !== 'all', function ($q) use ($type) {
                return $q->where('type', $type);
            })
            ->latest();

        $users = $query->paginate(10);

        // Determine currently online usernames from RADIUS accounting and persist to DB
        $onlineUsernames = Radacct::whereNull('acctstoptime')
            ->pluck('username')
            ->map(fn($u) => strtolower(trim($u)))
            ->unique()
            ->toArray();

        // Sync 'online' column (0 = offline, 1 = online)
        // Set online=1 for active usernames
        NetworkUser::whereIn(\DB::raw('lower(trim(username))'), $onlineUsernames)
            ->where('online', false)
            ->update(['online' => true]);

        // Set online=0 for others previously marked online but not in list
        NetworkUser::whereNotIn(\DB::raw('lower(trim(username))'), $onlineUsernames)
            ->where('online', true)
            ->update(['online' => false]);

        // Get available packages for the form
        $packages = [
            'hotspot' => Package::where('type', 'hotspot')->get(),
            'pppoe' => Package::where('type', 'pppoe')->get(),
            'static' => Package::where('type', 'static')->get(),
        ];

        // Get user counts by type for filters
        $counts = [
            'all' => NetworkUser::count(),
            'active' => NetworkUser::where('status', 'active')->count(),
            'inactive' => NetworkUser::where('status', 'inactive')->count(),
            'online' => count($onlineUsernames),
            'expired' => NetworkUser::where('expires_at', '<', now())->count(),
        ];

        return inertia('Users/index', [
            'users' => $users->through(fn($user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'account_number' => $user->account_number,
                'phone' => $user->phone,
                'email' => $user->email,
                'location' => $user->location,
                'type' => $user->type,
                'is_online' => (bool) $user->online,
                'expires_at' => $user->expires_at,
                'expiry_human' => optional($user->expires_at)->diffForHumans(),
                'package' => $user->package ? [
                    'id' => $user->package->id,
                    'name' => $user->package->name,
                ] : null,
            ]),
            'filters' => compact('type'),
            'counts' => $counts,
            'packages' => $packages,
        ]);
    }

    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:network_users',
            'password' => 'nullable|string|min:6',
            'phone' => 'required|string|max:15',
            /*'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('network_users', 'email')->whereNotNull('email')
            ],*/
            'location' => 'nullable|string|max:255',
            'type' => 'required|in:hotspot,pppoe,static',
            'package_id' => 'nullable|exists:packages,id',
            'expires_at' => 'nullable|date',
        ]);

        // Generate account number
        $accountNumber = 'NU' . str_pad(NetworkUser::max('id') + 1, 6, '0', STR_PAD_LEFT);

        try {
            // Create the user in a database transaction
            $user = \DB::transaction(function () use ($validated, $accountNumber) {
                return NetworkUser::create([
                    'full_name' => $validated['full_name'],
                    'username' => $validated['username'],
                    'password' => $validated['password'],
                    'account_number' => $accountNumber,
                    'phone' => $validated['phone'],
                    //'email' => $validated['email'],
                    'location' => $validated['location'],
                    'type' => $validated['type'],
                    'package_id' => $validated['package_id'],
                    'expires_at' => $validated['expires_at'],
                    'registered_at' => now(),
                    'created_by' => Auth::id(),
                ]);
            });

            return back()->with([
                'success' => 'User created successfully.',
                'user_id' => $user->id
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Handle duplicate entries gracefully
            if //(str_contains($e->getMessage(), 'email')) {
                //return back()->withErrors(['email' => 'This email address is already registered.'])->withInput();
            /*} elseif */(str_contains($e->getMessage(), 'username')) {
                return back()->withErrors(['username' => 'This username is already taken.'])->withInput();
            }

            return back()->withErrors(['error' => 'A user with this information already exists.'])->withInput();
        } catch (\Exception $e) {
            \Log::error('Failed to create user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['error' => 'Failed to create user. Please try again.'])->withInput();
        }
    }

    /*public function show($id)
{
    $userDetails = NetworkUser::with('package')->findOrFail($id);

    $payments = TenantPayment::where('user_id', $id)
        ->orderBy('paid_at', 'desc')
        ->get();

    return inertia('Tenants/Users/Details', [
        'user' => $userDetails,
        'payments' => $payments, // 👈 now Vue receives this


    ]);
}*/

    public function show($id)
    {
        $user = NetworkUser::with('package')->findOrFail($id);

        // Fetch user payments
        $userPayments = TenantPayment::where('user_id', $id)
            ->orderBy('paid_at', 'desc')
            ->get();

        // Lifetime Total
        $lifetimeTotal = $userPayments->sum('amount');

        // Payment Reliability Score
        $now = now();
        $delays = [];

        foreach ($userPayments as $payment) {
            if ($payment->due_date) {
                if ($payment->paid_at) {
                    // days late = paid_at - due_date
                    $delay = $payment->paid_at->greaterThan($payment->due_date)
                        ? $payment->due_date->diffInDays($payment->paid_at)
                        : 0;
                    $delays[] = $delay;
                } else {
                    // unpaid & overdue → delay until now
                    if ($payment->due_date->isPast()) {
                        $delays[] = $payment->due_date->diffInDays($now);
                    }
                }
            }
        }

        $avgDelay = count($delays) > 0 ? collect($delays)->avg() : 0;

        // Reliability %: 
        // 0 delay → 100%
        // 1-2 days late → 80-90%
        // 3-7 days late → 50-70%
        // 8+ → 20%
        if ($avgDelay == 0) {
            $paymentReliability = "100";
        } elseif ($avgDelay <= 2) {
            $paymentReliability = "90";
        } elseif ($avgDelay <= 7) {
            $paymentReliability = "70";
        } else {
            $paymentReliability = "30";
        }

        // Client Value (compare with all clients)
        $totalAllClients = TenantPayment::sum('amount');
        $clientValue = $totalAllClients > 0
            ? round(($lifetimeTotal / $totalAllClients) * 100, 1)
            : 0;

        // Fetch last 15 RADIUS sessions
        $sessions = Radacct::where('username', $user->username)
            ->orderBy('acctstarttime', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($session) {
                // Calculate session duration
                $duration = null;
                if ($session->acctstarttime && $session->acctstoptime) {
                    $start = \Carbon\Carbon::parse($session->acctstarttime);
                    $stop = \Carbon\Carbon::parse($session->acctstoptime);
                    $seconds = $stop->diffInSeconds($start);

                    $hours = floor($seconds / 3600);
                    $minutes = floor(($seconds % 3600) / 60);
                    $secs = $seconds % 60;
                    $duration = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
                }

                // Calculate total data used (input + output)
                $dataUsed = ($session->acctinputoctets ?? 0) + ($session->acctoutputoctets ?? 0);
                $dataUsedFormatted = $this->formatBytes($dataUsed);

                return [
                    'session_id' => $session->radacctid,
                    'start_time' => $session->acctstarttime,
                    'stop_time' => $session->acctstoptime,
                    'duration' => $duration ?? ($session->acctstoptime ? 'N/A' : 'Active'),
                    'data_used' => $dataUsedFormatted,
                    'data_in' => $this->formatBytes($session->acctinputoctets ?? 0),
                    'data_out' => $this->formatBytes($session->acctoutputoctets ?? 0),
                    'termination_cause' => $session->acctterminatecause ?? 'N/A',
                    'nas_ip' => $session->nasipaddress,
                    'framed_ip' => $session->framedipaddress,
                ];
            });

        return inertia('Users/Details', [
            'user' => $user,
            'payments' => $userPayments,
            'sessions' => $sessions,
            'lifetimeTotal' => $lifetimeTotal,
            'paymentReliability' => $paymentReliability,
            'clientValue' => $clientValue,
        ]);
    }

    // Helper function to format bytes
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }



    public function update(Request $request, NetworkUser $user)
    {
        // Validate the request
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('network_users')->ignore($user->id)],
            'password' => 'nullable|string|min:4',
            'phone' => 'required|string|max:15',
            /*'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('network_users', 'email')->ignore($user->id)->whereNotNull('email')
            ],*/
            'location' => 'nullable|string|max:255',
            'type' => ['required', Rule::in(['hotspot', 'pppoe', 'static'])],
            'package_id' => 'nullable|exists:packages,id',
            'expires_at' => 'nullable|date',
        ]);

        try {
            // Update the user in a transaction
            \DB::transaction(function () use ($user, $validated) {

                // If password not provided, don't update it
                if (empty($validated['password'])) {
                    unset($validated['password']);
                }

                // Preserve existing package_id if not provided
                if (
                    array_key_exists('package_id', $validated) &&
                    ($validated['package_id'] === null || $validated['package_id'] === '' || $validated['package_id'] === '0')
                ) {
                    unset($validated['package_id']);
                }

                // Final update
                $user->update($validated);
            });

            return back()->with([
                'success' => 'User updated successfully.',
                'user_id' => $user->id
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Handle duplicate entries gracefully
            if /*(str_contains($e->getMessage(), 'email')) {
                return back()->withErrors(['email' => 'This email address is already registered.'])->withInput();
            } elseif */(str_contains($e->getMessage(), 'username')) {
                return back()->withErrors(['username' => 'This username is already taken.'])->withInput();
            }

            return back()->withErrors(['error' => 'A user with this information already exists.'])->withInput();
        } catch (\Exception $e) {
            \Log::error('Failed to update user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['error' => 'Failed to update user. Please try again.'])->withInput();
        }
    }


    public function destroy(NetworkUser $user)
    {
        // Delete the user
        // Store the ID for the job since we'll delete the user
        $userId = $user->id;
        $mikrotikId = $user->mikrotik_id;
        $userType = $user->type;

        // Delete the user first
        $user->delete();

        // If there's a MikroTik ID, dispatch a job to clean up
        if ($mikrotikId) {
            // Create a temporary user object with just the needed data
            $tempUser = new NetworkUser([
                'id' => $userId,
                'mikrotik_id' => $mikrotikId,
                'type' => $userType,
            ]);

            // Dispatch the delete job
            \App\Jobs\SyncUserToMikrotik::dispatch($tempUser, 'delete')
                ->onQueue('mikrotik');
        }

        return back()->with('success', 'User deleted. MikroTik cleanup is being processed in the background.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:network_users,id',
        ])['ids'];

        // Get users before deletion
        $users = NetworkUser::whereIn('id', $ids)->get();

        // Update related payments in a transaction
        \DB::transaction(function () use ($ids) {
            TenantPayment::whereIn('user_id', $ids)
                ->update(['user_id' => null]);

            // Delete the users
            NetworkUser::whereIn('id', $ids)->delete();
        });

        // Dispatch jobs for MikroTik cleanup
        foreach ($users as $user) {
            if ($user->mikrotik_id) {
                $tempUser = new NetworkUser([
                    'id' => $user->id,
                    'mikrotik_id' => $user->mikrotik_id,
                    'type' => $user->type,
                ]);

                \App\Jobs\SyncUserToMikrotik::dispatch($tempUser, 'delete')
                    ->onQueue('mikrotik');
            }
        }

        return back()->with([
            'success' => 'Selected users deleted. MikroTik cleanup is being processed in the background.',
            'deleted_count' => count($ids)
        ]);
    }
}
