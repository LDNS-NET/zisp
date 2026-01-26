<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import Toast from '@/Components/Toast.vue';

const page = usePage();
const tenantLogo = page.props.tenantLogo;
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gray-900 pt-0 sm:pt-0 relative overflow-hidden"
    >
        <!-- Background Overlay (Matching Welcome Page) -->
        <div class="fixed inset-0 z-0 pointer-events-none overflow-hidden">
            <div class="absolute -top-[30%] -left-[10%] w-[70%] h-[70%] rounded-full bg-orange-600/20 blur-[120px] animate-pulse"></div>
            <div class="absolute top-[20%] -right-[10%] w-[60%] h-[60%] rounded-full bg-red-600/20 blur-[100px] animate-pulse delay-700"></div>
            <div class="absolute -bottom-[20%] left-[20%] w-[50%] h-[50%] rounded-full bg-amber-600/20 blur-[100px] animate-pulse delay-1000"></div>
            
            <!-- Subtle Grid Overlay -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:60px_60px] opacity-20"></div>
        </div>

        <div class="mb-6 z-10">
            <Link href="/" class="flex flex-col items-center">
                <!-- Display tenant logo if available, otherwise use application logo -->
                <img
                    v-if="tenantLogo"
                    :src="tenantLogo"
                    alt="Tenant Logo"
                    class="h-24 w-24 object-contain transition-transform duration-300 hover:scale-105"
                />
                <ApplicationLogo
                    v-else
                    class="h-24 w-24 fill-current text-orange-600 transition-transform duration-300 hover:scale-110 dark:text-orange-500 mb-2"
                />
                <span class="font-extrabold text-2xl text-gray-900 dark:text-white tracking-tight">
                    Mfire ISP Manager
                </span>
                <span class="text-xs font-medium text-orange-500 uppercase tracking-[0.2em] mt-1">
                    Happiness in every surf
                </span>
            </Link>
        </div>

        <div
            class="w-full overflow-hidden rounded-2xl border border-white/10 bg-white/5 px-8 py-8 shadow-2xl backdrop-blur-xl sm:max-w-2xl relative z-10"
        >
            <slot />
        </div>
    </div>
    <Toast />
</template>
