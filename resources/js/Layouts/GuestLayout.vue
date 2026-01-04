<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import Toast from '@/Components/Toast.vue';

const page = usePage();
const tenantLogo = page.props.tenantLogo;
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-gray-100 to-gray-300 pt-6 sm:pt-0 dark:from-gray-900 dark:via-gray-800 dark:to-black"
    >
        <div class="mb-6">
            <Link href="/">
                <!-- Display tenant logo if available, otherwise use application logo -->
                <img
                    v-if="tenantLogo"
                    :src="tenantLogo"
                    alt="Tenant Logo"
                    class="h-24 w-24 object-contain transition-transform duration-300 hover:scale-105"
                />
                <ApplicationLogo
                    v-else
                    class="h-24 w-24 fill-current text-gray-500 transition-transform duration-300 hover:scale-105 dark:text-gray-400"
                />
            </Link>
        </div>

        <div
            class="w-full overflow-hidden rounded-2xl border border-white/20 bg-white/30 px-8 py-8 shadow-2xl backdrop-blur-xl sm:max-w-2xl dark:border-gray-700/50 dark:bg-gray-800/40"
        >
            <slot />
        </div>
    </div>
    <Toast />
</template>
