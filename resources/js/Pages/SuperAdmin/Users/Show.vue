<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { computed } from 'vue';


const props = defineProps({
    user: Object,
    tenantDetails: Object,
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
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Business Info -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Business Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Business Name</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.name || props.user.name }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Email</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.email || props.user.email }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Phone</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.phone || props.user.phone }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tenant ID</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.user.tenant_id }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                                <div class="mt-1">
                                    <span 
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="props.tenantDetails?.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    >
                                        {{ props.tenantDetails?.status || 'Unknown' }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Country</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.country || props.user.country }}</div>
                            </div>
                             <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.address || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Joined Date</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.joining_date || new Date(props.user.created_at).toLocaleDateString() }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financials -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Financials</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Wallet Balance</label>
                                <div class="mt-1 text-sm font-bold text-green-600">{{ props.tenantDetails?.wallet_balance || '0.00' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Total Payments</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.totalPayments }}</div>
                            </div>
                             <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">User Value</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.user_value || '0.00' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">M-Pesa Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.mpesa_number || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Paybill Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.paybill_number || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Network Stats -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Network Statistics</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Total End Users</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.totalEndUsers }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Mikrotik Routers</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.totalMikrotiks }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Lifetime Traffic</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.lifetime_traffic || '0 MB' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                 <!-- Mikrotik List -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" v-if="props.mikrotiks && props.mikrotiks.length > 0">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Connected Routers</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="router in props.mikrotiks" :key="router.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ router.name || router.identity }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ router.ip_address }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </SuperAdminLayout>
</template>