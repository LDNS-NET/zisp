<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { computed } from 'vue';


const props = defineProps({
    user: Object,
    tenantSettings: Object,
    mikrotiks: Array,
    totalEndUsers: Number,
    totalPayments: Number,
});
</script>

<template>
    <Head :title="`User Details - ${props.user.name}`" />

    <SuperAdminLayout>
        <template #header>
            <h2 class="text-2xl font-semibold leading-tight">
                User Details - {{ props.user.name }}
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                        <!-- User Details -->
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Name: {{ props.user.name }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Email: {{ props.user.email }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Phone: {{ props.user.phone }}</p>
                            </div>
                            <!-- Add more user details as needed -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>