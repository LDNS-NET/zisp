<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Search, Filter, CreditCard, CheckCircle, XCircle, Clock, AlertCircle, MoreVertical, Eye, Trash2, AlertTriangle, X, Send } from 'lucide-vue-next';
import debounce from 'lodash/debounce';

const props = defineProps({
    payments: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const method = ref(props.filters?.method || '');

// Modal State
const showActionsModal = ref(false);
const showDeleteModal = ref(false);
const selectedPayment = ref(null);

// Debounced search
const updateSearch = debounce((value) => {
    router.get(
        route('superadmin.payments.index'),
        { search: value, status: status.value, method: method.value },
        { preserveState: true, replace: true }
    );
}, 300);

// Watch filters
watch([status, method], () => {
    router.get(
        route('superadmin.payments.index'),
        { search: search.value, status: status.value, method: method.value },
        { preserveState: true, replace: true }
    );
});

const formatCurrency = (amount, currency) => {
    return new Intl.NumberFormat('en-KE', { style: 'currency', currency: currency || 'KES' }).format(amount);
};

// Action Handlers
const openActions = (payment) => {
    selectedPayment.value = payment;
    showActionsModal.value = true;
};

const openDeleteModal = (payment) => {
    selectedPayment.value = payment;
    showDeleteModal.value = true;
    showActionsModal.value = false;
};

const closeModal = () => {
    showActionsModal.value = false;
    showDeleteModal.value = false;
    selectedPayment.value = null;
};

const confirmDelete = () => {
    if (selectedPayment.value) {
        router.delete(route('superadmin.payments.destroy', selectedPayment.value.id), {
            onFinish: () => closeModal(),
        });
    }
};

const canDisburse = (payment) => {
    if (!payment || !payment.tenant) return false;
    
    const isPaid = payment.status === 'paid';
    const isMpesa = payment.payment_method === 'mpesa';
    const isPendingOrFailed = ['pending', 'failed'].includes(payment.disbursement_status);
    const isKenya = payment.tenant.country_code === 'KE';
    
    // Check if tenant uses system M-Pesa API
    const mpesaGateway = payment.tenant.payment_gateways?.find(g => 
        g.provider === 'mpesa' && g.is_active
    );
    
    const usesSystemApi = mpesaGateway && !mpesaGateway.use_own_api;
    
    return isPaid && isMpesa && isPendingOrFailed && isKenya && usesSystemApi;
};

const disbursePayment = (payment) => {
    if (confirm('Are you sure you want to trigger manual disbursement for this payment?')) {
        router.post(route('superadmin.payments.disburse', payment.id), {}, {
            onSuccess: () => closeModal(),
        });
    }
};
</script>

<template>
    <Head title="System Payments" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                    System Payments
                </h2>
                <div class="text-sm text-gray-500">
                    {{ payments.total }} transactions found
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters Bar -->
            <div class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <!-- Search -->
                <div class="relative flex-1 max-w-md">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        @input="updateSearch($event.target.value)"
                        type="text"
                        placeholder="Search receipt, phone..."
                        class="block w-full rounded-lg border-0 py-2.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    />
                </div>

                <!-- Filters -->
                <div class="flex gap-3">
                    <select
                        v-model="method"
                        class="block rounded-lg border-0 py-2.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    >
                        <option value="">All Methods</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="paystack">Paystack</option>
                        <option value="flutterwave">Flutterwave</option>
                    </select>

                    <select
                        v-model="status"
                        class="block rounded-lg border-0 py-2.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    >
                        <option value="">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Transaction</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tenant</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            <tr v-for="payment in payments.data" :key="payment.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300">
                                            <CreditCard class="h-5 w-5" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ payment.receipt_number || 'N/A' }}</div>
                                            <div class="text-xs text-gray-500">{{ payment.payment_method }} • {{ payment.phone }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white">
                                        {{ formatCurrency(payment.amount, payment.currency) }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ payment.tenant?.name || 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">{{ payment.tenant?.email }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        payment.status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' :
                                        payment.status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' :
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
                                    ]">
                                        <component 
                                            :is="payment.status === 'paid' ? CheckCircle : (payment.status === 'pending' ? Clock : XCircle)" 
                                            class="mr-1.5 h-3 w-3" 
                                        />
                                        {{ payment.status.charAt(0).toUpperCase() + payment.status.slice(1) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ new Date(payment.created_at).toLocaleString() }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <button @click="openActions(payment)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700" title="Manage Payment">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="payments.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <AlertCircle class="h-12 w-12 mb-3 opacity-20" />
                                        <p class="text-lg font-medium">No payments found</p>
                                        <p class="text-sm">Try adjusting your search or filters</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="payments.links.length > 3" class="border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <Link :href="payments.prev_page_url" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</Link>
                        <Link :href="payments.next_page_url" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</Link>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing <span class="font-medium">{{ payments.from }}</span> to <span class="font-medium">{{ payments.to }}</span> of <span class="font-medium">{{ payments.total }}</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <Link 
                                    v-for="(link, i) in payments.links" 
                                    :key="i"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 focus:z-20 focus:outline-offset-0',
                                        link.active 
                                            ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' 
                                            : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700',
                                        i === 0 ? 'rounded-l-md' : '',
                                        i === payments.links.length - 1 ? 'rounded-r-md' : '',
                                        !link.url ? 'pointer-events-none opacity-50' : ''
                                    ]"
                                />
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Modal -->
        <Modal :show="showActionsModal" @close="closeModal" maxWidth="sm">
            <div class="p-4 dark:bg-slate-800 dark:text-white" v-if="selectedPayment">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4">
                        Payment Details
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-1">
                    <Link :href="route('superadmin.payments.show', selectedPayment.id)" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40">
                            <Eye class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">View Details</span>
                    </Link>

                    <button 
                        v-if="canDisburse(selectedPayment)"
                        @click="disbursePayment(selectedPayment)" 
                        class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors text-left group"
                    >
                        <div class="p-1.5 rounded-md bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 group-hover:bg-green-100 dark:group-hover:bg-green-900/40">
                            <Send class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-green-600 dark:text-green-400">Manual Disburse</span>
                    </button>

                    <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>

                    <button @click="openDeleteModal(selectedPayment)" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 group-hover:bg-red-100 dark:group-hover:bg-red-900/40">
                            <Trash2 class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">Delete Payment</span>
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeModal">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full dark:bg-red-900/30">
                    <AlertTriangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div class="mt-4 text-center">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Delete Payment?</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete this payment of <b>{{ formatCurrency(selectedPayment?.amount, selectedPayment?.currency) }}</b>? This action cannot be undone.
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