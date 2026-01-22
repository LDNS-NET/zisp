<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenants\TenantInstallation;
use App\Models\User;
use App\Models\Tenants\TenantEquipment;
use App\Models\Tenants\NetworkUser;
use App\Models\Tenants\TenantInstallationChecklist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TenantInstallationController extends Controller
{
    public function index(Request $request)
    {
        $installations = TenantInstallation::with(['technician', 'networkUser', 'equipment'])
            ->when($request->search, function ($q) use ($request) {
                $q->where('installation_number', 'like', "%{$request->search}%")
                  ->orWhere('customer_name', 'like', "%{$request->search}%")
                  ->orWhere('customer_phone', 'like', "%{$request->search}%")
                  ->orWhere('installation_address', 'like', "%{$request->search}%");
            })
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when($request->priority, function ($q) use ($request) {
                $q->where('priority', $request->priority);
            })
            ->when($request->technician_id, function ($q) use ($request) {
                $q->where('technician_id', $request->technician_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate('scheduled_date', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate('scheduled_date', '<=', $request->date_to);
            })
            ->latest('scheduled_date')
            ->paginate($request->get('per_page', 15));

        $stats = [
            'total' => TenantInstallation::count(),
            'scheduled' => TenantInstallation::where('status', 'scheduled')->count(),
            'in_progress' => TenantInstallation::where('status', 'in_progress')->count(),
            'completed' => TenantInstallation::where('status', 'completed')->count(),
            'cancelled' => TenantInstallation::where('status', 'cancelled')->count(),
            'today' => TenantInstallation::today()->count(),
        ];

        // Get technicians from Users with technical or technician roles
        $tenantId = Auth::user()->tenant_id;
        $technicians = User::where('tenant_id', $tenantId)
            ->where('is_suspended', false)
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['technical', 'technician', 'network_engineer']);
            })
            ->get(['id', 'name', 'username', 'phone']);

        return Inertia::render('Tenants/Installations/Index', [
            'installations' => $installations,
            'stats' => $stats,
            'technicians' => $technicians,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'priority' => $request->priority,
                'technician_id' => $request->technician_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
        ]);
    }

    public function create()
    {
        $technicians = TenantTechnician::active()->get(['id', 'name', 'phone', 'specialization']);
        $equipment = TenantEquipment::all(['id', 'name', 'type', 'serial_number']);
        $networkUsers = NetworkUser::where('status', 'active')->get(['id', 'username', 'name', 'phone']);
        $checklists = TenantInstallationChecklist::active()->get(['id', 'name', 'installation_type', 'service_type']);

        return Inertia::render('Tenants/Installations/Create', [
            'technicians' => $technicians,
            'equipment' => $equipment,
            'networkUsers' => $networkUsers,
            'checklists' => $checklists,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'network_user_id' => 'nullable|exists:network_users,id',
            'technician_id' => 'required|exists:tenant_technicians,id',
            'equipment_id' => 'nullable|exists:tenant_equipments,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'installation_address' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'installation_type' => 'required|in:new,relocation,upgrade,repair,maintenance',
            'service_type' => 'required|in:fiber,wireless,hybrid',
            'priority' => 'required|in:low,medium,high,urgent',
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'estimated_duration' => 'nullable|integer|min:1',
            'installation_notes' => 'nullable|string',
            'installation_cost' => 'nullable|numeric|min:0',
            'checklist_data' => 'nullable|array',
        ]);

        $installation = TenantInstallation::create($validated);

        return redirect()->route('tenant.installations.show', $installation)
            ->with('success', 'Installation scheduled successfully.');
    }

    public function show(TenantInstallation $installation)
    {
        $installation->load([
            'technician',
            'networkUser',
            'equipment',
            'photos',
            'creator',
            'updater'
        ]);

        $checklists = TenantInstallationChecklist::active()
            ->forInstallationType($installation->installation_type)
            ->forServiceType($installation->service_type)
            ->get();

        return Inertia::render('Tenants/Installations/Show', [
            'installation' => $installation,
            'checklists' => $checklists,
        ]);
    }

    public function edit(TenantInstallation $installation)
    {
        $technicians = TenantTechnician::active()->get(['id', 'name', 'phone', 'specialization']);
        $equipment = TenantEquipment::all(['id', 'name', 'type', 'serial_number']);
        $networkUsers = NetworkUser::where('status', 'active')->get(['id', 'username', 'name', 'phone']);

        return Inertia::render('Tenants/Installations/Edit', [
            'installation' => $installation,
            'technicians' => $technicians,
            'equipment' => $equipment,
            'networkUsers' => $networkUsers,
        ]);
    }

    public function update(Request $request, TenantInstallation $installation)
    {
        $validated = $request->validate([
            'network_user_id' => 'nullable|exists:network_users,id',
            'technician_id' => 'required|exists:tenant_technicians,id',
            'equipment_id' => 'nullable|exists:tenant_equipments,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'installation_address' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'installation_type' => 'required|in:new,relocation,upgrade,repair,maintenance',
            'service_type' => 'required|in:fiber,wireless,hybrid',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled,on_hold',
            'priority' => 'required|in:low,medium,high,urgent',
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'estimated_duration' => 'nullable|integer|min:1',
            'installation_notes' => 'nullable|string',
            'technician_notes' => 'nullable|string',
            'installation_cost' => 'nullable|numeric|min:0',
            'payment_collected' => 'nullable|boolean',
            'checklist_data' => 'nullable|array',
        ]);

        $installation->update($validated);

        return redirect()->back()->with('success', 'Installation updated successfully.');
    }

    public function destroy(TenantInstallation $installation)
    {
        $installation->delete();

        return redirect()->route('tenant.installations.index')
            ->with('success', 'Installation deleted successfully.');
    }

    public function start(TenantInstallation $installation)
    {
        if ($installation->status !== 'scheduled') {
            return back()->with('error', 'Only scheduled installations can be started.');
        }

        $installation->start();

        return back()->with('success', 'Installation started successfully.');
    }

    public function complete(Request $request, TenantInstallation $installation)
    {
        if ($installation->status !== 'in_progress') {
            return back()->with('error', 'Only in-progress installations can be completed.');
        }

        $validated = $request->validate([
            'technician_notes' => 'nullable|string',
            'equipment_installed' => 'nullable|array',
            'customer_rating' => 'nullable|integer|min:1|max:5',
            'customer_feedback' => 'nullable|string',
            'payment_collected' => 'nullable|boolean',
            'checklist_data' => 'nullable|array',
        ]);

        $installation->update([
            'technician_notes' => $validated['technician_notes'] ?? null,
            'equipment_installed' => $validated['equipment_installed'] ?? null,
            'customer_rating' => $validated['customer_rating'] ?? null,
            'customer_feedback' => $validated['customer_feedback'] ?? null,
            'payment_collected' => $validated['payment_collected'] ?? false,
            'checklist_data' => $validated['checklist_data'] ?? null,
        ]);

        $installation->complete();

        return back()->with('success', 'Installation completed successfully.');
    }

    public function cancel(Request $request, TenantInstallation $installation)
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $installation->cancel($validated['reason']);

        return back()->with('success', 'Installation cancelled.');
    }

    public function calendar(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $installations = TenantInstallation::with(['technician'])
            ->whereYear('scheduled_date', $year)
            ->whereMonth('scheduled_date', $month)
            ->get()
            ->map(function ($inst) {
                return [
                    'id' => $inst->id,
                    'title' => $inst->customer_name,
                    'start' => $inst->scheduled_date->format('Y-m-d') . ($inst->scheduled_time ? ' ' . $inst->scheduled_time : ''),
                    'status' => $inst->status,
                    'priority' => $inst->priority,
                    'technician' => $inst->technician ? $inst->technician->name : null,
                    'address' => $inst->installation_address,
                    'backgroundColor' => $this->getStatusColor($inst->status),
                    'borderColor' => $this->getPriorityColor($inst->priority),
                ];
            });

        return Inertia::render('Tenants/Installations/Calendar', [
            'installations' => $installations,
            'month' => $month,
            'year' => $year,
        ]);
    }

    public function dashboard()
    {
        $today = now()->toDateString();

        $stats = [
            'today_scheduled' => TenantInstallation::today()->scheduled()->count(),
            'today_in_progress' => TenantInstallation::today()->inProgress()->count(),
            'today_completed' => TenantInstallation::today()->completed()->count(),
            'upcoming' => TenantInstallation::upcoming()->count(),
            'overdue' => TenantInstallation::where('scheduled_date', '<', $today)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->count(),
        ];

        $todayInstallations = TenantInstallation::with(['technician', 'networkUser'])
            ->today()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->get();

        $activeTechnicians = TenantTechnician::active()
            ->with(['installations' => function ($q) use ($today) {
                $q->where('scheduled_date', $today)
                  ->whereIn('status', ['scheduled', 'in_progress']);
            }])
            ->get();

        return Inertia::render('Tenants/Installations/Dashboard', [
            'stats' => $stats,
            'todayInstallations' => $todayInstallations,
            'activeTechnicians' => $activeTechnicians,
        ]);
    }

    private function getStatusColor($status)
    {
        return match($status) {
            'scheduled' => '#3B82F6',
            'in_progress' => '#F59E0B',
            'completed' => '#10B981',
            'cancelled' => '#EF4444',
            'on_hold' => '#6B7280',
            default => '#9CA3AF',
        };
    }

    private function getPriorityColor($priority)
    {
        return match($priority) {
            'urgent' => '#DC2626',
            'high' => '#F97316',
            'medium' => '#FBBF24',
            'low' => '#34D399',
            default => '#9CA3AF',
        };
    }
}
