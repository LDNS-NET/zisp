<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';
import { 
    Plus, 
    Trash2, 
    Eye, 
    Gift, 
    MoreVertical, 
    XCircle, 
    Edit,
    Calendar,
    Tag,
    DollarSign
} from 'lucide-vue-next';

const toast = useToast();

const props = defineProps({
    vouchers: Object,
    voucherCount: Number,
    creating: { type: Boolean, default: false },
    flash: Object,
});

const showFormModal = ref(false);
const showActionsModal = ref(false);
const selectedVoucher = ref(null);
const selected = ref([]);
const selectAll = ref(false);

const form = useForm({
    code: '',
    name: '',
    value: '',
    type: 'fixed',
    usage_limit: '',
    expires_at: '',
    is_active: true,
    note: '',
});

const toggleSelectAll = () => {
    if (selectAll.value) {
        selected.value = props.vouchers.data.map((v) => v.id);
    } else {
        selected.value = [];
    }
};

const bulkDelete = () => {
    if (selected.value.length === 0) return;

    if (confirm('Are you sure you want to delete selected vouchers?')) {
        router.delete(route('vouchers.bulk-delete'), {
            data: { ids: selected.value },
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                selectAll.value = false;
                toast.success('Vouchers deleted successfully');
            },
        });
    }
};

const confirmVoucherDeletion = (voucher) => {
    if (confirm('Are you sure you want to delete this voucher?')) {
        router.delete(route('vouchers.destroy', voucher.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Voucher deleted successfully');
            },
        });
    }
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
};

const closeFormModal = () => {
    showFormModal.value = false;
    router.get(
        route('vouchers.index'),
        {},
        {
            replace: true, // Clean URL (remove ?create=true)
            preserveState: true,
            preserveScroll: true,
        },
    );
    resetForm();
};

const submitForm = () => {
    form.post(route('vouchers.store'), {
        onSuccess: () => {
            closeFormModal();
            toast.success('Voucher created successfully');
        },
        onError: () => {
            toast.error('Failed to create voucher');
        },
    });
};

const formatDate = (dateString) => {
    if (!dateString) return 'No Expiry';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const isExpired = (dateString) => {
    if (!dateString) return false;
    return new Date(dateString) < new Date();
};

// --- Watchers ---
// Watch for changes in `props.creating` to open/close modal and reset form
watch(
    () => props.creating,
    (newCreatingValue) => {
        if (newCreatingValue) {
            // Only reset form and show modal if it's truly entering the 'create' state
            resetForm();
            showFormModal.value = true;
        } else {
            showFormModal.value = false;
        }
    },
    { immediate: true },
); // `immediate: true` runs the watch on component mount

const openCreateModal = () => {
    // This is the correct way to open the modal via Inertia and query parameter
    router.get(route('vouchers.index', { create: true }), {
        preserveScroll: true, // Keep scroll position
        preserveState: true, // Keep component state (important for modal)
    });
};

const openActions = (voucher) => {
    selectedVoucher.value = voucher;
    showActionsModal.value = true;
};
</script>

<template>
    <Head title="Vouchers" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Gift class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        Vouchers
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage discount vouchers and promotional codes
                    </p>
                </div>
                <PrimaryButton @click="openCreateModal" class="flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    <span>New Voucher</span>
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Bulk Actions -->
            <div v-if="selected.length" class="flex items-center gap-2 bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ selected.length }} selected</span>
                <DangerButton @click="bulkDelete" class="flex items-center gap-2">
                    <Trash2 class="w-4 h-4" /> Delete
                </DangerButton>
            </div>

            <!-- Vouchers Table (Desktop) / Cards (Mobile) -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-center w-10">
                                    <Checkbox v-model:checked="selectAll" @change="toggleSelectAll" class="rounded" />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Value</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usage</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expires</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="voucher in vouchers.data" :key="voucher.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <Checkbox :value="voucher.id" v-model:checked="selected" class="rounded" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <Tag class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                        <span class="font-mono text-sm font-medium text-gray-900 dark:text-white">{{ voucher.code }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-sm font-medium text-gray-900 dark:text-white">
                                        <DollarSign class="w-3 h-3 text-green-600 dark:text-green-400" />
                                        <span>{{ voucher.value }}{{ voucher.type === 'percentage' ? '%' : '' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                        voucher.type === 'fixed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                                        'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'
                                    ]">
                                        {{ voucher.type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                    {{ voucher.usage_limit || 'Unlimited' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-sm" :class="isExpired(voucher.expires_at) ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300'">
                                        <Calendar class="w-3 h-3" />
                                        <span>{{ formatDate(voucher.expires_at) }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full',
                                        voucher.is_active && !isExpired(voucher.expires_at) ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                    ]">
                                        {{ voucher.is_active && !isExpired(voucher.expires_at) ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openActions(voucher)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="vouchers.data.length === 0">
                                <td colspan="8" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <Gift class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                        <p class="text-lg font-medium">No vouchers found</p>
                                        <p class="text-sm">Create your first voucher to get started</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-gray-200 dark:divide-slate-700">
                    <div v-for="voucher in vouchers.data" :key="voucher.id" class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <Tag class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                    <span class="font-mono text-sm font-medium text-gray-900 dark:text-white">{{ voucher.code }}</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                        voucher.type === 'fixed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                                        'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'
                                    ]">
                                        {{ voucher.type }}
                                    </span>
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full',
                                        voucher.is_active && !isExpired(voucher.expires_at) ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                    ]">
                                        {{ voucher.is_active && !isExpired(voucher.expires_at) ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ voucher.value }}{{ voucher.type === 'percentage' ? '%' : '' }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <div>Usage: {{ voucher.usage_limit || 'Unlimited' }}</div>
                            <div :class="isExpired(voucher.expires_at) ? 'text-red-600 dark:text-red-400' : ''">
                                {{ formatDate(voucher.expires_at) }}
                            </div>
                        </div>

                        <button @click="openActions(voucher)" class="w-full flex items-center justify-center gap-2 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                            <MoreVertical class="w-4 h-4" /> Manage Voucher
                        </button>
                    </div>
                    <div v-if="vouchers.data.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No vouchers found.
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="vouchers.total > vouchers.per_page" class="flex justify-center mt-6">
                <Pagination :links="vouchers.links" />
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showFormModal" @close="closeFormModal">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h2 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                    Create New Voucher
                </h2>

                <form @submit.prevent="submitForm">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <InputLabel for="code" value="Voucher Code" />
                            <TextInput id="code" type="text" class="mt-1 block w-full" v-model="form.code" required autofocus />
                            <InputError class="mt-2" :message="form.errors.code" />
                        </div>

                        <div>
                            <InputLabel for="name" value="Voucher Name" />
                            <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="value" value="Value" />
                            <TextInput id="value" type="number" step="0.01" class="mt-1 block w-full" v-model="form.value" required />
                            <InputError class="mt-2" :message="form.errors.value" />
                        </div>

                        <div>
                            <InputLabel for="type" value="Type" />
                            <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                <option value="fixed">Fixed Amount</option>
                                <option value="percentage">Percentage</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.type" />
                        </div>

                        <div>
                            <InputLabel for="usage_limit" value="Usage Limit (Optional)" />
                            <TextInput id="usage_limit" type="number" class="mt-1 block w-full" v-model="form.usage_limit" min="1" />
                            <InputError class="mt-2" :message="form.errors.usage_limit" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Leave empty for unlimited usage.
                            </p>
                        </div>

                        <div>
                            <InputLabel for="expires_at" value="Expires At (Optional)" />
                            <TextInput id="expires_at" type="date" class="mt-1 block w-full" v-model="form.expires_at" />
                            <InputError class="mt-2" :message="form.errors.expires_at" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Leave empty for no expiry date.
                            </p>
                        </div>

                        <div class="mt-4 flex items-center md:col-span-2">
                            <Checkbox id="is_active" v-model:checked="form.is_active" />
                            <InputLabel for="is_active" class="ml-2">Is Active</InputLabel>
                            <InputError class="mt-2" :message="form.errors.is_active" />
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <DangerButton type="button" @click="closeFormModal">Cancel</DangerButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Create Voucher
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Actions Modal -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-4 dark:bg-slate-800 dark:text-white" v-if="selectedVoucher">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4">
                        {{ selectedVoucher.code }}
                    </h3>
                    <button @click="showActionsModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-1">
                    <Link :href="route('vouchers.show', selectedVoucher.id)" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40">
                            <Eye class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">View Details</span>
                    </Link>

                    <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>

                    <button @click="confirmVoucherDeletion(selectedVoucher); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 group-hover:bg-red-100 dark:group-hover:bg-red-900/40">
                            <Trash2 class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">Delete Voucher</span>
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
