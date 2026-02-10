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
            'file' => 'required|file|mimes:csv,txt,json|max:5120', // 5MB max, supports CSV and JSON
        ]);

        set_time_limit(600); // Increase timeout to 10 minutes for large imports
        ini_set('memory_limit', '512M'); // Increase memory limit

        $file = $request->file('file');
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());
        
        $rows = [];
        
        // Parse file based on format
        if ($extension === 'json') {
            // Parse JSON file
            $content = file_get_contents($path);
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['error' => 'Invalid JSON format: ' . json_last_error_msg()]);
            }
            
            // Support both array of objects and object with data key
            if (isset($data['data']) && is_array($data['data'])) {
                $rows = $data['data'];
            } elseif (is_array($data)) {
                $rows = $data;
            } else {
                return back()->withErrors(['error' => 'JSON must contain an array of user objects']);
            }
            
            if (empty($rows)) {
                return back()->withErrors(['error' => 'File is empty']);
            }
            
            // Normalize keys to lowercase
            $rows = array_map(function($row) {
                return array_change_key_case($row, CASE_LOWER);
            }, $rows);
            
        } else {
            // Parse CSV file
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
            
            // Read all rows into array
            while (($row = fgetcsv($handle)) !== false) {
                // Skip empty lines
                if (count($row) < 1 || (count($row) === 1 && (empty($row[0]) || is_null($row[0])))) {
                    continue;
                }
                
                // Convert indexed array to associative array
                $assocRow = [];
                foreach ($header as $index => $key) {
                    $assocRow[$key] = isset($row[$index]) ? trim($row[$index]) : '';
                }
                $rows[] = $assocRow;
            }
            
            fclose($handle);
        }
        
        // Validate required columns
        $required = ['username', 'phone'];
        if (!empty($rows)) {
            $firstRow = $rows[0];
            $missing = array_diff($required, array_keys($firstRow));
            
            if (!empty($missing)) {
                return back()->withErrors(['error' => 'Missing required columns: ' . implode(',', $missing)]);
            }
        }
        
        $createCount = 0;
        $skipCount = 0;  // Track skipped existing users
        $errorCount = 0;
        $errors = [];
        $rowNumber = 0;

        // Disable query log to reduce memory usage
        \DB::connection()->disableQueryLog();

        // Cache packages to avoid repeated queries
        $packageCache = Package::all()->keyBy('name');
            
        try {
            foreach ($rows as $row) {
                $rowNumber++;
                
                // Get values from associative array
                $username = isset($row['username']) ? trim($row['username']) : '';
                $phone = isset($row['phone']) ? trim($row['phone']) : '';
                
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

                // Check duplicates in current tenant - REMOVED because we now use upsert

                // Optional fields
                $fullName = isset($row['full_name']) ? trim($row['full_name']) : $username;
                $location = isset($row['location']) ? trim($row['location']) : null;
                $password = isset($row['password']) ? trim($row['password']) : null;
                $packageName = isset($row['package']) ? trim($row['package']) : null;
                $accountNo = isset($row['account_no']) ? trim($row['account_no']) : null;
                
                // Handle multiple possible column names for expiry date
                $expiryDate = null;
                if (isset($row['expiry_date']) && !empty($row['expiry_date'])) {
                    $expiryDate = trim($row['expiry_date']);
                } elseif (isset($row['expires_at']) && !empty($row['expires_at'])) {
                    $expiryDate = trim($row['expires_at']);
                } elseif (isset($row['expiry_at']) && !empty($row['expiry_at'])) {
                    $expiryDate = trim($row['expiry_at']);
                } elseif (isset($row['expiry']) && !empty($row['expiry'])) {
                    $expiryDate = trim($row['expiry']);
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
                if (isset($row['type'])) {
                    $rawType = strtolower(trim($row['type']));
                    if (in_array($rawType, ['hotspot', 'pppoe', 'static'])) {
                        $type = $rawType;
                    }
                }

                // Find package ID if provided
                $packageId = null;
                if (!empty($packageName)) {
                    // Use cached packages - search by name AND type first
                    $pkg = $packageCache->filter(function($package) use ($packageName, $type) {
                        return $package->name === $packageName && $package->type === $type;
                    })->first();
                    
                    // If not found by precise type, try just name (loose matching)
                    if (!$pkg) {
                         $pkg = $packageCache->filter(function($package) use ($packageName) {
                             return $package->name === $packageName;
                         })->first();
                    }
                    
                    if ($pkg) {
                        $packageId = $pkg->id;
                    } else {
                        // Package not found - fall back to least costly package
                        \Log::warning("Row $rowNumber: Package '$packageName' not found for user '$username', falling back to least costly package");
                        
                        // Get least costly package for the user's type
                        $fallbackPkg = $packageCache->filter(function($package) use ($type) {
                            return $package->type === $type;
                        })->sortBy('price')->first();
                        
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
                    // Check if user already exists - if so, SKIP
                    $existingUser = NetworkUser::where('username', $username)->first();
                    
                    if ($existingUser) {
                        // User exists - SKIP this row
                        $skipCount++;
                        \Log::info("Row $rowNumber: Skipping existing user '$username'");
                        \DB::commit(); // Commit the transaction (nothing changed)
                        continue; // Move to next row
                    }
                    
                    // User doesn't exist - CREATE new user
                    $userData = [
                        'username' => $username,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'location' => $location,
                        'type' => $type,
                        'package_id' => $packageId,
                        'expires_at' => $parsedExpiryDate,
                        'registered_at' => now(),
                        'created_by' => Auth::id(),
                    ];
                    
                    // Handle password logic for new users
                    if (!empty($password)) {
                        // Password provided in import - use it
                        $userData['password'] = $password;
                    } else {
                        // New user without password - use default password
                        $userData['password'] = '12345678';
                        \Log::info("Row $rowNumber: Using default password '12345678' for new user '$username'");
                    }
                    
                    // Create the new user
                    NetworkUser::create($userData);
                    
                    \DB::commit();
                    $createCount++; // Track as new creation
                    
                } catch (\Exception $e) {
                    \DB::rollBack();
                    $errorMessage = $e->getMessage();
                    // Extract more specific error info
                    if (str_contains($errorMessage, 'Data too long')) {
                        $errors[] = "Row $rowNumber: Data too long in one or more fields";
                    } else {
                        $errors[] = "Row $rowNumber ($username): " . $errorMessage;
                    }
                    $errorCount++;
                    
                    // Log detailed error for debugging
                    \Log::error("Import Error - Row $rowNumber", [
                        'username' => $username,
                        'phone' => $phone,
                        'error' => $errorMessage,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $totalProcessed = $createCount + $skipCount;
            
            if ($totalProcessed == 0 && $errorCount > 0) {
                 // Show first 20 errors
                 $errorSummary = array_slice($errors, 0, 20);
                 return back()->withErrors([
                     'error' => "Import failed. No users processed. Total errors: $errorCount",
                     'details' => implode("\n", $errorSummary) . ($errorCount > 20 ? "\n... and " . ($errorCount - 20) . " more errors" : "")
                 ]);
            }

            $message = "Import completed: $createCount created, $skipCount skipped (existing).";
            if ($errorCount > 0) {
                $message .= " Failed to import $errorCount rows.";
            }

            return back()->with([
                'success' => $message,
                'import_stats' => [
                    'created' => $createCount,
                    'skipped' => $skipCount,
                    'success' => $totalProcessed,
                    'errors' => $errorCount,
                    'total' => $totalProcessed + $errorCount
                ],
                'import_errors' => array_slice($errors, 0, 50) // Show first 50 errors
            ]);

        } catch (\Exception $e) {
            // File handle is already closed at line 549, don't try to close it again
            \Log::error('CSV Import Fatal Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Import failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Sync all users to RADIUS database tables
     */
    public function syncToRadius(Request $request)
    {
        try {
            set_time_limit(600); // 10 minutes for large user bases
            
            $users = NetworkUser::with(['package', 'hotspotPackage'])->get();
            $syncedCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($users as $user) {
                try {
                    \DB::beginTransaction();

                    // Clear existing RADIUS entries for this user
                    \App\Models\Radius\Radcheck::where('username', $user->username)->delete();
                    \App\Models\Radius\Radreply::where('username', $user->username)->delete();
                    \App\Models\Radius\Radusergroup::where('username', $user->username)->delete();

                    // 1. Create radcheck entry (password)
                    \App\Models\Radius\Radcheck::create([
                        'username' => $user->username,
                        'attribute' => 'Cleartext-Password',
                        'op' => ':=',
                        'value' => $user->password,
                    ]);

                    // Get package (Standard or Hotspot)
                    $package = $user->package ?: $user->hotspotPackage;
                    
                    if ($package) {
                        // 2. Rate limit
                        $rateValue = "{$package->upload_speed}M/{$package->download_speed}M";
                        \App\Models\Radius\Radreply::create([
                            'username' => $user->username,
                            'attribute' => 'Mikrotik-Rate-Limit',
                            'op' => ':=',
                            'value' => $rateValue,
                        ]);

                        // 3. Simultaneous Use (Devices)
                        $deviceLimit = $package->device_limit ?? 1;
                        \App\Models\Radius\Radreply::create([
                            'username' => $user->username,
                            'attribute' => 'Simultaneous-Use',
                            'op' => ':=',
                            'value' => (string)$deviceLimit,
                        ]);

                        // 4. Session-Timeout for hotspot users
                        if ($user->type === 'hotspot') {
                            $seconds = 0;
                            $val = $package->duration_value ?? $package->duration ?? 1;
                            $unit = $package->duration_unit ?? 'days';
                            
                            switch ($unit) {
                                case 'minutes': $seconds = $val * 60; break;
                                case 'hours':   $seconds = $val * 3600; break;
                                case 'days':    $seconds = $val * 86400; break;
                                case 'weeks':   $seconds = $val * 604800; break;
                                case 'months':  $seconds = $val * 2592000; break;
                            }

                            if ($seconds > 0) {
                                \App\Models\Radius\Radreply::create([
                                    'username' => $user->username,
                                    'attribute' => 'Session-Timeout',
                                    'op' => ':=',
                                    'value' => (string)$seconds,
                                ]);
                            }
                        }

                        // 5. Group assignment (for non-hotspot users)
                        if ($user->type !== 'hotspot') {
                            \App\Models\Radius\Radusergroup::create([
                                'username' => $user->username,
                                'groupname' => $package->name ?? 'default',
                                'priority' => 1,
                            ]);
                        }
                    }

                    // 6. Expiration date
                    if ($user->expires_at) {
                        \App\Models\Radius\Radcheck::create([
                            'username' => $user->username,
                            'attribute' => 'Expiration',
                            'op' => ':=',
                            'value' => $user->expires_at->format('d M Y H:i:s'),
                        ]);
                    }

                    // 7. MAC-Auth for hotspot users
                    if ($user->type === 'hotspot' && !empty($user->mac_address)) {
                        \App\Models\Radius\Radcheck::updateOrCreate(
                            ['username' => $user->mac_address, 'attribute' => 'Cleartext-Password'],
                            ['op' => ':=', 'value' => $user->mac_address]
                        );
                    }

                    \DB::commit();
                    $syncedCount++;

                } catch (\Exception $e) {
                    \DB::rollBack();
                    $failedCount++;
                    $errors[] = "User '{$user->username}': " . $e->getMessage();
                    \Log::error("RADIUS sync failed for user {$user->username}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "RADIUS sync completed: {$syncedCount} users synced successfully";
            if ($failedCount > 0) {
                $message .= ", {$failedCount} failed";
            }

            return back()->with([
                'success' => $message,
                'sync_stats' => [
                    'synced' => $syncedCount,
                    'failed' => $failedCount,
                    'total' => $users->count()
                ],
                'sync_errors' => array_slice($errors, 0, 20) // Show first 20 errors
            ]);

        } catch (\Exception $e) {
            \Log::error('RADIUS Sync Fatal Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'RADIUS sync failed: ' . $e->getMessage()]);
        }
    }
}
