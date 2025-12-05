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
    totalMikrotiks: Number,
});
</script>

<template>
    <Head :title="`User Details - ${props.user.name}`" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    User Details - {{ props.user.name }}
                </h2>
                <Link :href="route('superadmin.users.index')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition">
                    Back to List
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Key Metrics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total End Users</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.totalEndUsers }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Payments</div>
                        <div class="text-2xl font-bold text-green-600">{{ props.totalPayments }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Mikrotik Routers</div>
                        <div class="text-2xl font-bold text-blue-600">{{ props.totalMikrotiks }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Wallet Balance</div>
                        <div class="text-2xl font-bold text-purple-600">{{ props.user.wallet_balance }}</div>
                    </div>
                </div>

                <!-- General Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">General Information</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Business Name</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.name }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Email</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.email }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Phone</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.phone }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tenant ID</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.tenant_id }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                            <div class="mt-1">
                                <span :class="[
                                    'px-2 py-1 text-xs rounded-full',
                                    props.user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                ]">
                                    {{ props.user.status }}
                                </span>
                            </div>
                        </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Role</label>
                            <div class="mt-1 text-gray-900 dark:text-white capitalize">{{ props.user.role }}</div>
                        </div>
                    </div>
                </div>

                <!-- Location & Address -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Location & Address</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.address || 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Country</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.country }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Timezone</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.timezone }}</div>
                        </div>
                    </div>
                </div>

                <!-- Financial Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Financial Details</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Bank Name</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.bank_name || 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Account Name</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.account_name || 'N/A' }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.account_number || 'N/A' }}</div>
                        </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Paybill Number</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.paybill_number || 'N/A' }}</div>
                        </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Till Number</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.till_number || 'N/A' }}</div>
                        </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">M-Pesa Number</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.mpesa_number || 'N/A' }}</div>
                        </div>
                    </div>
                </div>

                 <!-- System Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">System Information</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Joined Date</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.joining_date }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Expiry Date</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.expiry_date }}</div>
                        </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Last Login IP</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.last_login_ip || 'N/A' }}</div>
                        </div>
                         <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Lifetime Traffic</label>
                            <div class="mt-1 text-gray-900 dark:text-white">{{ props.user.lifetime_traffic || '0' }}</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </SuperAdminLayout>
</template>