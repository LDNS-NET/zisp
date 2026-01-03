<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import { 
    Trash2, 
    ArrowLeft, 
    CreditCard, 
    User, 
    Calendar, 
    CheckCircle, 
    XCircle, 
    Clock,
    Smartphone,
    FileText,
    Hash
} from 'lucide-vue-next';

const props = defineProps({
    payment: Object,
});

// Modal State
const showDeleteModal = ref(false);

const openDeleteModal = () => showDeleteModal.value = true;
const closeModal = () => showDeleteModal.value = false;

const confirmDelete = () => {
    router.delete(route('superadmin.payments.destroy', props.payment.id), {
        onFinish: () => closeModal(),
    });
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(amount);
};
</script>

<template>
    <Head :title="`Payment Details - ${props.payment.receipt_number}`" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('superadmin.payments.index')" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <ArrowLeft class="w-5 h-5 text-gray-500" />
                    </Link>
                    <div>
                        <h2 class="text-2xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ props.payment.receipt_number }}
                        </h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span 
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium capitalize"
                                :class="{
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': props.payment.status === 'paid',
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': props.payment.status === 'pending',
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': props.payment.status === 'failed'
                                }"
                            >
                                <component 
                                    :is="props.payment.status === 'paid' ? CheckCircle : (props.payment.status === 'failed' ? XCircle : Clock)" 
                                    class="w-3 h-3 mr-1"
                                />
                                {{ props.payment.status }}
                            </span>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ formatCurrency(props.payment.amount) }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <button
                        @click="openDeleteModal"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800"
                    >
                        <Trash2 class="w-4 h-4 mr-2" />
                        Delete Payment
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Transaction Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <CreditCard class="w-5 h-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Transaction Details</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Amount</label>
                                <div class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">{{ formatCurrency(props.payment.amount) }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Payment Method</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 capitalize">{{ props.payment.payment_method }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Receipt Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ props.payment.receipt_number }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Checkout Request ID</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono break-all">{{ props.payment.checkout_request_id || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Merchant Request ID</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono break-all">{{ props.payment.merchant_request_id || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payer Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <User class="w-5 h-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Payer Information</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Phone Number</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.payment.phone }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Account Reference</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.payment.account_reference || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">User ID (Paid To)</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.payment.user_id || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenant Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" v-if="props.payment.tenant">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <FileText class="w-5 h-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Tenant Information</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tenant Name</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    <Link :href="route('superadmin.users.show', props.payment.tenant.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        {{ props.payment.tenant.name }}
                                    </Link>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tenant Email</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.payment.tenant.email }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timestamps -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <Calendar class="w-5 h-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Timeline</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Transaction Date</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ formatDate(props.payment.created_at) }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ formatDate(props.payment.updated_at) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeModal">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full dark:bg-red-900/30">
                    <Trash2 class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div class="mt-4 text-center">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Delete Payment?</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete payment <b>{{ props.payment.receipt_number }}</b>? This action cannot be undone.
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
                        Delete Payment
                    </button>
                </div>
            </div>
        </Modal>
    </SuperAdminLayout>
</template>
