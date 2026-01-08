<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Storage;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = $request->user()?->tenant ?? tenant();
        $settings = null;

        if ($tenant) {
            $settings = \Illuminate\Support\Facades\Cache::remember("tenant_general_setting_{$tenant->id}", 60, function () use ($tenant) {
                return \App\Models\TenantGeneralSetting::where('tenant_id', $tenant->id)->first();
            });
        }

        // Prepare logo URL
        $logoUrl = null;
        if ($settings?->logo) {
            if (!str_starts_with($settings->logo, 'http')) {
                $logoUrl = Storage::disk('public')->url($settings->logo);
            } else {
                $logoUrl = $settings->logo;
            }
        } elseif ($tenant?->logo) {
            if (!str_starts_with($tenant->logo, 'http')) {
                $logoUrl = Storage::disk('public')->url($tenant->logo);
            } else {
                $logoUrl = $tenant->logo;
            }
        }

        // Fetch counts for navigation
        $counts = [
            'all_users' => \App\Models\Tenants\NetworkUser::count(),
            'online_users' => 0,
            'leads' => \App\Models\Tenants\TenantLeads::count(),
            'tickets' => \App\Models\Tenants\TenantTickets::count(),
            'packages' => \App\Models\Package::count(),
            'vouchers' => \App\Models\Voucher::count(),
            'invoices' => \App\Models\Tenants\TenantInvoice::count(),
            'mikrotiks' => \App\Models\Tenants\TenantMikrotik::count(),
        ];

        if ($tenant) {
            $counts['online_users'] = \App\Models\Tenants\TenantActiveUsers::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('last_seen_at', '>', now()->subHours(24))
                ->distinct('username')
                ->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? array_merge($request->user()->toArray(), [
                    'unread_notifications_count' => $request->user()->unreadNotifications()->count(),
                ]) : ($request->user('customer') ? $request->user('customer')->toArray() : null),
                'impersonated_by' => $request->session()->get('impersonated_by'),
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $settings?->business_name ?? $tenant->name,
                'logo' => $logoUrl,
                'currency' => $tenant->currency,
                'support_phone' => $settings?->support_phone,
                'support_email' => $settings?->support_email,
            ] : null,
            'sidebarCounts' => $counts,
            'superadminCounts' => [
                'pending_onboarding_requests' => $request->user()?->is_super_admin ? \App\Models\OnboardingRequest::where('status', 'pending')->count() : 0,
                'pending_domain_requests' => $request->user()?->is_super_admin ? \App\Models\DomainRequest::where('status', 'pending')->count() : 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
