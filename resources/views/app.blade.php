<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead

        @php
            $tenant = tenant();
            $settings = $tenant ? \App\Models\TenantGeneralSetting::where('tenant_id', $tenant->id)->first() : null;
            $primaryColor = $settings->primary_color ?? '#3b82f6';
            $secondaryColor = $settings->secondary_color ?? '#1e40af';
        @endphp

        <style>
            :root {
                --primary-color: {{ $primaryColor }};
                --secondary-color: {{ $secondaryColor }};
            }
        </style>
    </head>
    <style>
        #app-loader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: radial-gradient(circle at center, #111827 0%, #000000 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .loader-content {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
        }

        .loader-logo-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loader-logo {
            width: 80px;
            height: auto;
            z-index: 10;
            animation: pulse-scale 2s ease-in-out infinite;
        }

        .loader-spinner-outer {
            position: absolute;
            inset: 0;
            border: 2px solid rgba(59, 130, 246, 0.1);
            border-radius: 50%;
        }

        .loader-spinner-inner {
            position: absolute;
            inset: -4px;
            border: 3px solid transparent;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1.5s cubic-bezier(0.5, 0.1, 0.5, 0.9) infinite;
            filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.5));
        }

        .loader-text {
            color: #94a3b8;
            font-family: 'Figtree', sans-serif;
            font-size: 0.875rem;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            animation: shimmer 2s linear infinite;
            background: linear-gradient(90deg, #94a3b8 0%, #ffffff 50%, #94a3b8 100%);
            background-size: 200% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse-scale {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 1; }
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
    </style>

    <div id="app-loader">
        <div class="loader-content">
            <div class="loader-logo-wrapper">
                <div class="loader-spinner-outer"></div>
                <div class="loader-spinner-inner"></div>
                <img src="/images/zyraisp.png" alt="ZimaRadius" class="loader-logo">
            </div>
            <div class="loader-text">Loading ZimaRadius</div>
        </div>
    </div>

    <script>
        // Safety Fallback: Remove loader if app fails to load or on global errors
        (function() {
            const loader = document.getElementById('app-loader');
            if (!loader) return;

            const removeLoader = () => {
                if (loader.parentNode) {
                    loader.style.opacity = '0';
                    setTimeout(() => loader.remove(), 600);
                    // Cleanup listeners
                    window.removeEventListener('error', handleError);
                    window.removeEventListener('unhandledrejection', handleError);
                    clearTimeout(safetyTimeout);
                }
            };

            const handleError = (error) => {
                const errorMessage = error?.reason?.message || error?.message || '';
                const isAssetError = errorMessage.includes('Failed to fetch dynamically imported module') || 
                                   errorMessage.includes('Importing a module script failed');

                if (isAssetError) {
                    console.error('Asset loading failed. Attempting recovery reload...', error);
                    
                    // Prevent infinite reload loops
                    const lastReload = sessionStorage.getItem('last_asset_reload');
                    const now = Date.now();
                    
                    if (!lastReload || (now - parseInt(lastReload)) > 30000) { // 30s cooldown
                        sessionStorage.setItem('last_asset_reload', now.toString());
                        window.location.reload();
                        return;
                    }
                }

                console.warn('App initialization encountered an issue, removing loader.', error);
                removeLoader();
            };

            // Global error listeners
            window.addEventListener('error', handleError);
            window.addEventListener('unhandledrejection', handleError);

            // Safety timeout: 15 seconds max for initial load
            const safetyTimeout = setTimeout(() => {
                console.warn('App load timed out, forcing loader removal.');
                removeLoader();
            }, 15000);

            // Expose for app.js to call if needed
            window.removeAppLoader = removeLoader;
        })();
    </script>
    @inertia
</html>
