<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Ban, CheckCircle, Trash2, AlertTriangle, ArrowLeft } from 'lucide-vue-next';

const props = defineProps({
    user: Object,
    tenantDetails: Object,
    tenantSettings: Object,
    mikrotiks: Array,
    totalEndUsers: Number,
    totalPayments: Number,
});

// Modal State
const showDeleteModal = ref(false);
const showSuspendModal = ref(false);

const openDeleteModal = () => showDeleteModal.value = true;
const openSuspendModal = () => showSuspendModal.value = true;
const closeModal = () => {
    showDeleteModal.value = false;
    showSuspendModal.value = false;
};

const confirmDelete = () => {
    router.delete(route('superadmin.users.destroy', props.user.id), {
        onFinish: () => closeModal(),
    });
};

const confirmSuspend = () => {
    const action = props.user.is_suspended ? 'unsuspend' : 'suspend';
    router.post(route(`superadmin.users.${action}`, props.user.id), {}, {
        onFinish: () => closeModal(),
    });
};
</script>

<template>
    <Head :title="`User Details - ${props.user.name}`" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('superadmin.users.index')" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <ArrowLeft class="w-5 h-5 text-gray-500" />
                    </Link>
                    <div>
                        <h2 class="text-2xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ props.user.name }}
                        </h2>
                        <p class="text-sm text-gray-500">{{ props.user.tenant_id }}</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button
                        @click="openSuspendModal"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium border rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors"
                        :class="props.user.is_suspended 
                            ? 'text-green-700 bg-green-50 border-green-200 hover:bg-green-100 focus:ring-green-500 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' 
                            : 'text-yellow-700 bg-yellow-50 border-yellow-200 hover:bg-yellow-100 focus:ring-yellow-500 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800'"
                    >
                        <component :is="props.user.is_suspended ? CheckCircle : Ban" class="w-4 h-4 mr-2" />
                        {{ props.user.is_suspended ? 'Activate User' : 'Suspend User' }}
                    </button>
                    
                    <button
                        @click="openDeleteModal"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800"
                    >
                        <Trash2 class="w-4 h-4 mr-2" />
                        Delete User
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Identity & Status -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Identity & Status</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Full Name</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.name || props.user.name }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Username</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.username || props.user.username || 'N/A' }}</div>
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
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Role</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 capitalize">{{ props.tenantDetails?.role || props.user.role || 'N/A' }}</div>
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
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Suspended</label>
                                <div class="mt-1">
                                    <span 
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="props.user.is_suspended ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                    >
                                        {{ props.user.is_suspended ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                            </div>
                             <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Business Reg. No.</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.business_registration_number || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location & Settings -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Location & Settings</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.address || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Country</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.country || props.user.country }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Timezone</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.timezone || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Language</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.language || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Domain</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.domain || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financials -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Financials</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Wallet Balance</label>
                                <div class="mt-1 text-sm font-bold text-green-600">{{ props.tenantDetails?.wallet_balance || '0.00' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">User Value</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.user_value || '0.00' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Total Payments</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.totalPayments }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Bank Name</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.bank_name || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Account Name</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.account_name || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.account_number || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">M-Pesa Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.mpesa_number || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Paybill Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.paybill_number || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Till Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.till_number || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Statistics -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">System Statistics</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">All Subscribers</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.all_subscribers || '0' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Users Count</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.users_count || props.totalEndUsers }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Mikrotik Count</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.mikrotik_count || props.totalMikrotiks }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Lifetime Traffic</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.lifetime_traffic || '0 MB' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dates & Security -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Dates & Security</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Joined Date</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.joining_date || new Date(props.user.created_at).toLocaleDateString() }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Expiry Date</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.expiry_date || props.user.subscription_expires_at || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Pruning Date</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.prunning_date || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Email Verified At</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.email_verified_at || props.user.email_verified_at || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Phone Verified At</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.phone_verified_at || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Last Login IP</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.last_login_ip || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">2FA Enabled</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.two_factor_enabled ? 'Yes' : 'No' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Account Locked Until</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.tenantDetails?.account_locked_until || 'N/A' }}</div>
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Wireguard</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="router in props.mikrotiks" :key="router.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ router.name || router.identity }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ router.wireguard_address }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ router.status }}</td>
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

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeModal">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full dark:bg-red-900/30">
                    <AlertTriangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div class="mt-4 text-center">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Delete Tenant?</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete <b>{{ props.user.name }}</b>? This action cannot be undone and will remove all associated data.
                    </p>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        @click="closeModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        @click="confirmDelete"
                    >
                        Delete Tenant
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Suspend Confirmation Modal -->
        <Modal :show="showSuspendModal" @close="closeModal">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto" :class="props.user.is_suspended ? 'bg-green-100 dark:bg-green-900/30' : 'bg-yellow-100 dark:bg-yellow-900/30'">
                    <component :is="props.user.is_suspended ? CheckCircle : Ban" class="w-6 h-6" :class="props.user.is_suspended ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'" />
                </div>
                <div class="mt-4 text-center">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                        {{ props.user.is_suspended ? 'Activate Tenant?' : 'Suspend Tenant?' }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ props.user.is_suspended 
                            ? `Are you sure you want to reactivate ${props.user.name}? They will regain access to the system.` 
                            : `Are you sure you want to suspend ${props.user.name}? They will lose access to the system immediately.` 
                        }}
                    </p>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        @click="closeModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-white border border-transparent rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2"
                        :class="props.user.is_suspended ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500'"
                        @click="confirmSuspend"
                    >
                        {{ props.user.is_suspended ? 'Activate' : 'Suspend' }}
                    </button>
                </div>
            </div>
        </Modal>
    </SuperAdminLayout>
</template>