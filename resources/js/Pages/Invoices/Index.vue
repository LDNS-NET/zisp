<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { useToast } from 'vue-toastification';
import { 
    Plus, 
    ReceiptText, 
    MoreVertical, 
    Edit, 
    Trash2, 
    XCircle,
    User,
    DollarSign,
    Calendar,
    Calendar,
    FileText,
    Search
} from 'lucide-vue-next';

const toast = useToast();
const editing = ref(null);
const showModal = ref(false);
const showActionsModal = ref(false);
const selectedInvoice = ref(null);
const selectedInvoices = ref([]);
const selectAll = ref(false);

const props = defineProps({
    auth: Object,
    invoices: Object,
    filters: Object,
    can: Object,
    flash: Object,
    networkUsers: Array,
});

const search = ref(props.filters?.search || '');
let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('invoices.index'),
            { search: value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
});

const form = useForm({
    user_id: '',
    amount: '',
    package: '',
    issued_on: '',
    due_on: '',
    status: 'pending',
});

function openModal(invoice = null) {
    form.reset();
    if (invoice) {
        editing.value = invoice.id;
        form.user_id = invoice.customer?.id || '';
        form.amount = invoice.amount;
        form.package = invoice.package;
        form.issued_on = invoice.issued_on;
        form.due_on = invoice.due_on;
        form.status = invoice.status;
    } else {
        editing.value = null;
        form.status = 'pending';
    }
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
}

function submit() {
    if (editing.value) {
        form.put(route('invoices.update', editing.value), {
            onSuccess: () => {
                toast.success('Invoice updated successfully.');
                closeModal();
            },
            onError: () => {
                toast.error('There was an error updating the invoice.');
            },
        });
    } else {
        form.post(route('invoices.store'), {
            onSuccess: () => {
                toast.success('Invoice created successfully.');
                closeModal();
            },
            onError: () => {
                toast.error('There was an error creating the invoice.');
            },
        });
    }
}

const deleteInvoice = (invoice) => {
    if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
        router.delete(route('invoices.destroy', invoice.id), {
            onSuccess: () => {
                toast.success('Invoice deleted successfully.');
            },
            onError: () => {
                toast.error('There was an error deleting the invoice.');
            },
        });
    }
};

function bulkDelete() {
    if (selectedInvoices.value.length === 0) return;

    if (confirm(`Are you sure you want to delete ${selectedInvoices.value.length} selected invoices?`)) {
        router.delete(route('invoices.bulk-destroy'), {
            data: { ids: selectedInvoices.value.map((inv) => inv.id) },
            preserveScroll: true,
            onSuccess: () => {
                selectedInvoices.value = [];
                selectAll.value = false;
                toast.success('Selected invoices deleted successfully.');
            },
            onError: () => {
                toast.error('There was an error deleting the selected invoices.');
            },
        });
    }
}

function toggleSelectAll(event) {
    if (event.target.checked) {
        selectedInvoices.value = props.invoices && props.invoices.data ? [...props.invoices.data] : [];
    } else {
        selectedInvoices.value = [];
    }
}

watch(selectAll, (val) => {
    if (val) {
        selectedInvoices.value = props.invoices && props.invoices.data ? [...props.invoices.data] : [];
    } else {
        selectedInvoices.value = [];
    }
});

watch(selectedInvoices, (val) => {
    selectAll.value = props.invoices && props.invoices.data && val.length === props.invoices.data.length && val.length > 0;
});

function openActions(invoice) {
    selectedInvoice.value = invoice;
    showActionsModal.value = true;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function isOverdue(dueDate, status) {
    if (status === 'paid') return false;
    if (!dueDate) return false;
    return new Date(dueDate) < new Date();
}
</script>

<template>
    <Head title="Invoices" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <ReceiptText class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        Invoices
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage customer invoices and billing
                    </p>
                </div>
                <PrimaryButton v-if="props.can && props.can.create_invoice" @click="openModal()" class="flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    <span>Create Invoice</span>
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Search and Bulk Actions -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search invoices..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out"
                    />
                </div>

                <div v-if="selectedInvoices.length" class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ selectedInvoices.length }} selected</span>
                    <DangerButton @click="bulkDelete" class="flex items-center gap-2">
                        <Trash2 class="w-4 h-4" /> Delete
                    </DangerButton>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <!-- Desktop Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left w-10">
                                    <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice #</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="invoice in props.invoices.data" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" :value="invoice" v-model="selectedInvoices" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <FileText class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                        <span class="font-mono text-sm font-medium text-gray-900 dark:text-white">{{ invoice.invoice_number }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 flex-shrink-0 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-xs">
                                            {{ invoice.customer?.name?.charAt(0).toUpperCase() || 'U' }}
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ invoice.customer?.name || 'Unknown' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-sm font-medium text-gray-900 dark:text-white">
                                        <DollarSign class="w-3 h-3 text-green-600 dark:text-green-400" />
                                        <span>{{ invoice.amount }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-sm" :class="isOverdue(invoice.due_date, invoice.status) ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300'">
                                        <Calendar class="w-3 h-3" />
                                        <span>{{ formatDate(invoice.due_date) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                        invoice.status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                        isOverdue(invoice.due_date, invoice.status) ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                                    ]">
                                        {{ isOverdue(invoice.due_date, invoice.status) ? 'Overdue' : invoice.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openActions(invoice)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!props.invoices.data || props.invoices.data.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <ReceiptText class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                        <p class="text-lg font-medium">No invoices found</p>
                                        <p class="text-sm">Create your first invoice to get started</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-gray-200 dark:divide-slate-700">
                    <div v-for="invoice in props.invoices.data" :key="invoice.id" class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold">
                                    {{ invoice.customer?.name?.charAt(0).toUpperCase() || 'U' }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ invoice.customer?.name || 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ invoice.invoice_number }}</div>
                                </div>
                            </div>
                            <span :class="[
                                'px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                invoice.status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                isOverdue(invoice.due_date, invoice.status) ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                                'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                            ]">
                                {{ isOverdue(invoice.due_date, invoice.status) ? 'Overdue' : invoice.status }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="flex items-center gap-1 text-gray-900 dark:text-white font-medium">
                                <DollarSign class="w-4 h-4 text-green-600 dark:text-green-400" />
                                <span>{{ invoice.amount }}</span>
                            </div>
                            <div class="flex items-center gap-1 text-gray-600 dark:text-gray-300" :class="isOverdue(invoice.due_date, invoice.status) ? 'text-red-600 dark:text-red-400' : ''">
                                <Calendar class="w-4 h-4" />
                                <span>{{ formatDate(invoice.due_date) }}</span>
                            </div>
                        </div>

                        <button @click="openActions(invoice)" class="w-full flex items-center justify-center gap-2 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                            <MoreVertical class="w-4 h-4" /> Manage Invoice
                        </button>
                    </div>
                    <div v-if="!props.invoices.data || props.invoices.data.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No invoices found.
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-show="invoices.total > 0" class="flex justify-center mt-6">
                <Pagination 
                    :links="invoices.links" 
                    :per-page="invoices.per_page"
                    :total="invoices.total"
                    :from="invoices.from"
                    :to="invoices.to"
                />
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ editing ? 'Edit Invoice' : 'Create Invoice' }}
                </h3>
                <form @submit.prevent="submit">
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="user_id" value="Customer/User" />
                            <SelectInput id="user_id" v-model="form.user_id" :options="[
                                { value: '', label: 'Select a user' },
                                ...(Array.isArray(props.networkUsers) ? props.networkUsers : []).map((user) => ({
                                    value: user.id,
                                    label: user.full_name,
                                })),
                            ]" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.user_id" />
                        </div>
                        <div>
                            <InputLabel for="amount" value="Amount" />
                            <TextInput id="amount" v-model="form.amount" type="number" step="0.01" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.amount" />
                        </div>
                        <div>
                            <InputLabel for="package" value="Package" />
                            <TextInput id="package" v-model="form.package" type="text" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.package" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="issued_on" value="Issued On" />
                                <TextInput id="issued_on" v-model="form.issued_on" type="date" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.issued_on" />
                            </div>
                            <div>
                                <InputLabel for="due_on" value="Due On" />
                                <TextInput id="due_on" v-model="form.due_on" type="date" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.due_on" />
                            </div>
                        </div>
                        <div>
                            <InputLabel for="status" value="Status" />
                            <select v-model="form.status" id="status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="closeModal">Cancel</DangerButton>
                        <PrimaryButton type="submit" :disabled="form.processing">{{ editing ? 'Update' : 'Create' }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Actions Modal -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-4 dark:bg-slate-800 dark:text-white" v-if="selectedInvoice">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4">
                        {{ selectedInvoice.invoice_number }}
                    </h3>
                    <button @click="showActionsModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-1">
                    <button @click="openModal(selectedInvoice); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40">
                            <Edit class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Edit Invoice</span>
                    </button>

                    <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>

                    <button @click="deleteInvoice(selectedInvoice); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 group-hover:bg-red-100 dark:group-hover:bg-red-900/40">
                            <Trash2 class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">Delete Invoice</span>
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
