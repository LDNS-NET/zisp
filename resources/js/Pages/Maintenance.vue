<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: String,
});
</script>

<template>
    <Head title="System Maintenance" />

    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-900 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full text-center space-y-8">
            <!-- Icon/Illustration -->
            <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>

            <!-- Heading -->
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                System Operational
            </h1>
            
            <h2 class="text-xl font-medium text-emerald-600 dark:text-emerald-400">
                Maintenance in Progress
            </h2>

            <!-- Message -->
            <p class="text-base text-gray-600 dark:text-gray-300 leading-relaxed">
                We are currently upgrading our systems to serve you better. 
                <span class="font-semibold text-gray-800 dark:text-gray-200">Your internet service remains fully active and operational.</span>
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Access to the portal is temporarily suspended. We will be back shortly.
            </p>

            <!-- Footer/Contact -->
            <div class="pt-8 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Need urgent assistance? Contact support.
                </p>
            </div>
        </div>
    </div>
</template>
