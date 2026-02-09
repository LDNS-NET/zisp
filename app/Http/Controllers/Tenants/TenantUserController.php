<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\NetworkUser;
use App\Models\Package;
use App\Models\Tenants\TenantPayment;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\Radius\Radacct;
class TenantUserController extends Controller
{
    /**
     * Check if password is required for user management
     */
    private function isPasswordRequired()
    {
        $tenantId = Auth::user()->tenant_id;
        $systemSettings = TenantSetting::where('tenant_id', $tenantId)
            ->where('category', 'system')
            ->first();
        
        return $systemSettings?->settings['require_password_for_user_management'] ?? true;
    }
    public function index(Request $request)
    {
        $type = $request->get('type', 'all');
        $search = $request->get('search');

        $query = NetworkUser::query()
            ->with(['package', 'hotspotPackage'])
            ->when($type !== 'all', function ($q) use ($type) {
                return $q->where('type', $type);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($subQ) use ($search) {
                    $subQ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('account_number', 'like', "%{$search}%");
                });
            })
            ->latest();

        $perPage = $request->get('per_page', 20);
        $users = $query->paginate($perPage);

        // Determine current session statuses from TenantActiveUsers

        // Fetch active/online usernames directly using the same logic as Active Users page
        $activeUsernames = \App\Models\Tenants\TenantActiveUsers::where('status', 'active')
            ->where('last_seen_at', '>', now()->subHours(24))
            ->whereNotNull('username')
            ->pluck('username')
            ->map(fn($u) => strtolower(trim($u)))
            ->unique()
            ->values()
            ->all();

        // Sync 'online' column based on this source of truth
        if (!empty($activeUsernames)) {
             NetworkUser::whereIn(\DB::raw('lower(trim(username))'), $activeUsernames)
                 ->where('online', false)
                 ->update(['online' => true]);
                 
             NetworkUser::whereNotIn(\DB::raw('lower(trim(username))'), $activeUsernames)
                 ->where('online', true)
                 ->update(['online' => false]);
        } else {
             // If no active users found, mark all as offline using chunk to prevent locks
             NetworkUser::where('online', true)->chunkById(100, function ($users) {
                 NetworkUser::whereIn('id', $users->pluck('id'))->update(['online' => false]);
             });
        }

        // Get available packages for the form
        $packages = [
            'hotspot' => Package::where('type', 'hotspot')->get(),
            'pppoe' => Package::where('type', 'pppoe')->get(),
            'static' => Package::where('type', 'static')->get(),
        ];

        // Get user counts by type for filters
        $counts = [
            'all' => NetworkUser::count(),
            'hotspot' => NetworkUser::where('type', 'hotspot')->count(),
            'pppoe' => NetworkUser::where('type', 'pppoe')->count(),
            'static' => NetworkUser::where('type', 'static')->count(),
            // Use the persisted `online` boolean column as the source of truth for frontend counts
            'online' => NetworkUser::where('online', true)->count(),
            'expired' => NetworkUser::where('expires_at', '<', now())->count(),
        ];

        return inertia('Users/index', [
            'users' => $users->through(fn($user) => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'account_number' => $user->account_number,
                'phone' => $user->phone,
                'location' => $user->location,
                'type' => $user->type,
                'package_id' => $user->package_id,
                'hotspot_package_id' => $user->hotspot_package_id,
                // Use real-time check against active session list for accuracy
                'is_online' => in_array(strtolower($user->username), $activeUsernames),
                'expires_at' => $user->expires_at,
                'expiry_human' => optional($user->expires_at)->diffForHumans(),
                'package' => ($user->package ?: $user->hotspotPackage) ? [
                    'id' => $user->package?->id ?? $user->hotspotPackage?->id,
                    'name' => $user->package?->name ?? $user->hotspotPackage?->name,
                ] : null,
            ]),
            'filters' => [
                'type' => $type,
                'search' => $search,
            ],
            'counts' => $counts,
            'packages' => $packages,
            'requirePasswordForUserManagement' => $this->isPasswordRequired(),
        ]);
    }

    public function store(Request $request)
    {
        // Validate the request
        $rules = [
            'full_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|min:3',
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
        ];

        // Conditionally add password requirement
        if ($this->isPasswordRequired()) {
            $rules['admin_password'] = 'required|current_password';
        }

        $validated = $request->validate($rules);

        // Force lowercase username
        $validated['username'] = strtolower($validated['username']);

        // Custom username uniqueness check within tenant database
        $existingUser = NetworkUser::where('username', $validated['username'])->first();
        if ($existingUser) {
            return back()->withErrors(['username' => 'The username has already been taken.'])->withInput();
        }



        try {
            // Create the user in a database transaction
            $user = \DB::transaction(function () use ($validated) {
                return NetworkUser::create([
                    'full_name' => $validated['full_name'],
                    'username' => $validated['username'],
                    'password' => $validated['password'],
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
            if (str_contains($e->getMessage(), 'email')) {
                return back()->withErrors(['email' => 'This email address is already registered.'])->withInput();
            } elseif (str_contains($e->getMessage(), 'username')) {
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

    public function show($uuid)
    {
        $user = NetworkUser::where('uuid', $uuid)->with(['package', 'hotspotPackage'])->firstOrFail();

        // Fetch user payments
        $userPayments = TenantPayment::where('user_id', $user->id)
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
        $rules = [
            'full_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255',
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
        ];

        // Conditionally add password requirement
        if ($this->isPasswordRequired()) {
            $rules['admin_password'] = 'required|current_password';
        }

        $validated = $request->validate($rules);

        // Force lowercase username
        $validated['username'] = strtolower($validated['username']);

        // Custom username uniqueness check within tenant database (excluding current user)
        $existingUser = NetworkUser::where('username', $validated['username'])
            ->where('id', '!=', $user->id)
            ->first();
        if ($existingUser) {
            return back()->withErrors(['username' => 'The username has already been taken.'])->withInput();
        }

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
            //Handle duplicate entries gracefully
            if (str_contains($e->getMessage(), 'email')) {
                return back()->withErrors(['email' => 'This email address is already registered.'])->withInput();
            } elseif (str_contains($e->getMessage(), 'username')) {
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
        $uuids = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string|exists:network_users,uuid',
        ])['ids'];

        // Get users before deletion by UUID
        $users = NetworkUser::whereIn('uuid', $uuids)->get();
        $ids = $users->pluck('id')->toArray();

        // Update related payments in a transaction
        // Update related payments in a transaction
        \DB::transaction(function () use ($ids, $users) {
            TenantPayment::whereIn('user_id', $ids)
                ->update(['user_id' => null]);

            // Delete the users individually to trigger model events (Radius cleanup)
            foreach ($users as $user) {
                $user->delete();
            }
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
            'deleted_count' => count($uuids)
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5MB max
        ]);

        set_time_limit(600); // Increase timeout to 10 minutes for large imports
        ini_set('memory_limit', '512M'); // Increase memory limit

        $file = $request->file('file');
        $path = $file->getRealPath();
        
        // Open the file
        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->withErrors(['error' => 'Could not read file']);
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['error' => 'File is empty']);
        }

        // Normalize header to lowercase and trim
        $header = array_map(function($h) {
            return strtolower(trim($h));
        }, $header);

        // Required columns
        $required = ['username', 'phone'];
        $missing = array_diff($required, $header);
        
        if (!empty($missing)) {
            fclose($handle);
            return back()->withErrors(['error' => 'Missing required columns: ' . implode(',', $missing)]);
        }

        // Map column names to indices
        $map = array_flip($header);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $rowNumber = 1; // Header is 1

        // Disable query log to reduce memory usage
        \DB::connection()->disableQueryLog();

        // Cache packages to avoid repeated queries
        $packageCache = Package::all()->keyBy('name');
            
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                
                // Skip empty lines
                if (count($row) < 1 || (count($row) === 1 && (empty($row[0]) || is_null($row[0])))) {
                    continue;
                }

                // Get values using map
                $username = isset($map['username']) && isset($row[$map['username']]) ? trim($row[$map['username']]) : '';
                $phone = isset($map['phone']) && isset($row[$map['phone']]) ? trim($row[$map['phone']]) : '';
                
                if (empty($username)) {
                    $errors[] = "Row $rowNumber: Username is required";
                    $errorCount++;
                    continue;
                }
                
                if (empty($phone)) {
                    $errors[] = "Row $rowNumber: Phone is required";
                    $errorCount++;
                    continue;
                }

                // Check duplicates in current tenant
                $exists = NetworkUser::where('username', $username)->exists();
                if ($exists) {
                    $errors[] = "Row $rowNumber: Username '$username' already exists";
                    $errorCount++;
                    continue;
                }

                // Other fields
                $fullName = isset($map['full_name']) && isset($row[$map['full_name']]) ? trim($row[$map['full_name']]) : '';
                $location = isset($map['location']) && isset($row[$map['location']]) ? trim($row[$map['location']]) : '';
                $password = isset($map['password']) && isset($row[$map['password']]) ? trim($row[$map['password']]) : '';
                $packageName = isset($map['package']) && isset($row[$map['package']]) ? trim($row[$map['package']]) : '';
                
                // Support both "expiry" and "expires_at" column names
                $expiryDate = null;
                if (isset($map['expiry']) && isset($row[$map['expiry']])) {
                    $expiryDate = trim($row[$map['expiry']]);
                } elseif (isset($map['expires_at']) && isset($row[$map['expires_at']])) {
                    $expiryDate = trim($row[$map['expires_at']]);
                }
                
                // Parse the expiry date if present
                $parsedExpiryDate = null;
                if (!empty($expiryDate)) {
                    try {
                        $parsedExpiryDate = \Carbon\Carbon::parse($expiryDate);
                    } catch (\Exception $e) {
                        $errors[] = "Row $rowNumber: Invalid expiry date format '$expiryDate' - " . $e->getMessage();
                        $errorCount++;
                        continue;
                    }
                }
                
                // Determine type
                $type = 'hotspot';
                if (isset($map['type']) && isset($row[$map['type']])) {
                    $rawType = strtolower(trim($row[$map['type']]));
                    if (in_array($rawType, ['hotspot', 'pppoe', 'static'])) {
                        $type = $rawType;
                    }
                }

                // Find package ID if provided
                $packageId = null;
                if (!empty($packageName)) {
                    // Use cached packages
                    $pkg = $packageCache->where('name', $packageName)->where('type', $type)->first();
                    
                    // If not found by precise type, try just name (loose matching)
                    if (!$pkg) {
                         $pkg = $packageCache->where('name', $packageName)->first();
                    }
                    
                    if ($pkg) {
                        $packageId = $pkg->id;
                    } else {
                        // Package not found - fall back to least costly package
                        \Log::warning("Row $rowNumber: Package '$packageName' not found for user '$username', falling back to least costly package");
                        
                        // Get least costly package for the user's type
                        $fallbackPkg = $packageCache->where('type', $type)->sortBy('price')->first();
                        
                        // If no package found for type, get the overall least costly package
                        if (!$fallbackPkg) {
                            $fallbackPkg = $packageCache->sortBy('price')->first();
                        }
                        
                        if ($fallbackPkg) {
                            $packageId = $fallbackPkg->id;
                            \Log::info("Row $rowNumber: Using fallback package '{$fallbackPkg->name}' (price: {$fallbackPkg->price}) for user '$username'");
                        } else {
                            \Log::error("Row $rowNumber: No packages available for fallback for user '$username'");
                        }
                    }
                }

                // Use individual transaction for each user to avoid rollback cascade
                \DB::beginTransaction();
                try {
                    NetworkUser::create([
                        'full_name' => $fullName,
                        'username' => $username,
                        'phone' => $phone,
                        'location' => $location,
                        'password' => $password, 
                        'type' => $type,
                        'package_id' => $packageId,
                        'expires_at' => $parsedExpiryDate,
                        'registered_at' => now(),
                        'created_by' => Auth::id(),
                    ]);
                    \DB::commit();
                    $successCount++;
                    
                } catch (\Exception $e) {
                    \DB::rollBack();
                    $errorMessage = $e->getMessage();
                    // Extract more specific error info
                    if (str_contains($errorMessage, 'Duplicate entry')) {
                        $errors[] = "Row $rowNumber: Duplicate entry for '$username'";
                    } elseif (str_contains($errorMessage, 'Data too long')) {
                        $errors[] = "Row $rowNumber: Data too long in one or more fields";
                    } else {
                        $errors[] = "Row $rowNumber ($username): " . $errorMessage;
                    }
                    $errorCount++;
                    
                    // Log detailed error for debugging
                    \Log::error("CSV Import Error - Row $rowNumber", [
                        'username' => $username,
                        'phone' => $phone,
                        'error' => $errorMessage,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            fclose($handle);

            if ($successCount == 0 && $errorCount > 0) {
                 // Show first 20 errors
                 $errorSummary = array_slice($errors, 0, 20);
                 return back()->withErrors([
                     'error' => "Import failed. No users imported. Total errors: $errorCount",
                     'details' => implode("\n", $errorSummary) . ($errorCount > 20 ? "\n... and " . ($errorCount - 20) . " more errors" : "")
                 ]);
            }

            $message = "Imported $successCount users successfully.";
            if ($errorCount > 0) {
                $message .= " Failed to import $errorCount rows.";
            }

            return back()->with([
                'success' => $message,
                'import_stats' => [
                    'success' => $successCount,
                    'errors' => $errorCount,
                    'total' => $successCount + $errorCount
                ],
                'import_errors' => array_slice($errors, 0, 50) // Show first 50 errors
            ]);

        } catch (\Exception $e) {
            fclose($handle);
            \Log::error('CSV Import Fatal Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Import failed: ' . $e->getMessage()]);
        }
    }
}
