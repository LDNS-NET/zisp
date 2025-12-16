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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $settings?->business_name ?? $tenant->name,
                'logo' => $logoUrl,
                'currency' => $tenant->currency,
                'support_phone' => $settings?->support_phone,
                'support_email' => $settings?->support_email,
            ] : null,
        ];
    }
}
