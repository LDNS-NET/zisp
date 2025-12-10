<script setup>
import { usePage, Link, Head } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { all } from 'axios';

const props = defineProps({
    mikrotiks: Object,
    page: Object,

});
</script>

<template>
    <Head title="All Mikrotiks" />
    <SuperAdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                All Mikrotiks in the System
            </h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Mikrotiks Table -->
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="mikrotik in props.mikrotiks.data" :key="mikrotik.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ mikrotik.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ mikrotik.wireguard_address }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ mikrotik.location }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ mikrotik.status }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <Link :href="route('superadmin.allmikrotiks.show', mikrotik.id)" class="text-indigo-600 hover:text-indigo-900">
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!--<div class="mt-4">
                            <Pagination :links="allmikrotiks.links" />
                        </div>-->
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>