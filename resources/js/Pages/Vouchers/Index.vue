<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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
    Printer,
    Search,
    Copy,
    CheckCircle,
    AlertCircle,
    Clock,
    User,
    Package,
} from 'lucide-vue-next';

const toast = useToast();

const page = usePage();

const props = defineProps({
    vouchers: Object,
    voucherCount: Number,
    creating: { type: Boolean, default: false },
    viewing: { type: Boolean, default: false },
    selectedVoucherId: { type: Number, default: null },
    flash: Object,
    packages: Array,
    currency: String,
    filters: Object,
});

const currency = computed(
    () => props.currency || page.props.tenant?.currency || 'KES',
);

const showFormModal = ref(false);
const showActionsModal = ref(false);
const showViewModal = ref(false);
const selectedVoucher = ref(null);
const selected = ref([]);
const selectAll = ref(false);

const search = ref(props.filters?.search || '');
let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('vouchers.index'),
            { search: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 500);
});

const form = useForm({
    prefix: '',
    length: 8,
    quantity: 1,
    package_id: '',
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
    if (confirm(`Are you sure you want to delete voucher "${voucher.code}"?`)) {
        router.delete(route('vouchers.destroy', voucher.id), {
            preserveScroll: true,
            onSuccess: () => {
                showActionsModal.value = false;
                toast.success('Voucher deleted successfully');
            },
        });
    }
};

const viewVoucher = (voucher) => {
    selectedVoucher.value = voucher;
    router.get(route('vouchers.show', voucher.id), {
        preserveScroll: true,
        preserveState: true,
    });
};

const copyToClipboard = (text) => {
    navigator.clipboard
        .writeText(text)
        .then(() => {
            toast.success('Code copied to clipboard');
        })
        .catch(() => {
            toast.error('Failed to copy code');
        });
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
            replace: true,
            preserveState: true,
            preserveScroll: true,
        },
    );
    resetForm();
};

const closeViewModal = () => {
    showViewModal.value = false;
    selectedVoucher.value = null;
    router.get(
        route('vouchers.index'),
        {},
        {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        },
    );
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

const formatDate = (dateString, voucher = null) => {
    if (voucher?.package) {
        return `${voucher.package.duration_value} ${voucher.package.duration_unit}`;
    }
    if (!dateString) return 'No Expiry';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const isExpired = (dateString, voucher = null) => {
    if (voucher?.package && voucher.status === 'active') return false;
    if (!dateString) return false;
    return new Date(dateString) < new Date();
};

const getStatusColor = (status, expired = false) => {
    if (expired)
        return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';

    const colors = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        used: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        revoked:
            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        expired: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    };
    return colors[status] || colors.expired;
};

const getStatusIcon = (status) => {
    const icons = {
        active: CheckCircle,
        used: CheckCircle,
        revoked: XCircle,
        expired: AlertCircle,
    };
    return icons[status] || AlertCircle;
};

// Watch for changes in `props.creating` to open/close modal
watch(
    () => props.creating,
    (newCreatingValue) => {
        if (newCreatingValue) {
            resetForm();
            showFormModal.value = true;
        } else {
            showFormModal.value = false;
        }
    },
    { immediate: true },
);

// Watch for changes in `props.viewing` and `props.selectedVoucherId`
watch(
    () => props.viewing,
    (newViewingValue) => {
        if (newViewingValue && props.selectedVoucherId) {
            // Fetch the voucher details if needed
            const voucher = props.vouchers?.data?.find(
                (v) => v.id === props.selectedVoucherId,
            );
            if (voucher) {
                selectedVoucher.value = voucher;
                showViewModal.value = true;
            }
        } else {
            showViewModal.value = false;
        }
    },
    { immediate: true },
);

const openCreateModal = () => {
    router.get(route('vouchers.index', { create: true }), {
        preserveScroll: true,
        preserveState: true,
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
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="flex items-center gap-2 text-2xl font-bold text-gray-800 dark:text-white"
                    >
                        <Gift
                            class="h-6 w-6 text-blue-600 dark:text-blue-400"
                        />
                        Vouchers
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage discount vouchers and promotional codes
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a
                        :href="route('vouchers.print')"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-200 dark:hover:bg-slate-600"
                    >
                        <Printer class="h-4 w-4" />
                        <span>Print Vouchers</span>
                    </a>
                    <PrimaryButton
                        @click="openCreateModal"
                        class="flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        <span>New Voucher</span>
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Search and Bulk Actions -->
            <div
                class="flex flex-col items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:flex-row"
            >
                <div class="relative w-full sm:w-72">
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                    >
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search vouchers..."
                        class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 leading-5 text-gray-900 placeholder-gray-500 transition duration-150 ease-in-out focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white sm:text-sm"
                    />
                </div>

                <div v-if="selected.length" class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400"
                        >{{ selected.length }} selected</span
                    >
                    <DangerButton
                        @click="bulkDelete"
                        class="flex items-center gap-2"
                    >
                        <Trash2 class="h-4 w-4" /> Delete
                    </DangerButton>
                </div>
            </div>

            <!-- Vouchers Table (Desktop) / Cards (Mobile) -->
            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <!-- Desktop Table -->
                <div class="hidden overflow-x-auto md:block">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-slate-700"
                    >
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="w-10 px-6 py-3 text-center"
                                >
                                    <Checkbox
                                        v-model:checked="selectAll"
                                        @change="toggleSelectAll"
                                        class="rounded"
                                    />
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Code
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Value
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Type
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Usage
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Duration
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="voucher in vouchers.data"
                                :key="voucher.id"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-slate-700/50"
                            >
                                <td
                                    class="whitespace-nowrap px-6 py-4 text-center"
                                >
                                    <Checkbox
                                        :value="voucher.id"
                                        v-model:checked="selected"
                                        class="rounded"
                                    />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <Tag
                                            class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                        />
                                        <span
                                            class="font-mono text-sm font-medium text-gray-900 dark:text-white"
                                            >{{ voucher.code }}</span
                                        >
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div
                                        class="flex items-center gap-1 text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        <span
                                            class="text-green-600 dark:text-green-400"
                                            >{{ currency }}</span
                                        >
                                        <span
                                            >{{ voucher.value
                                            }}{{
                                                voucher.type === 'percentage'
                                                    ? '%'
                                                    : ''
                                            }}</span
                                        >
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-xs font-semibold capitalize',
                                            voucher.type === 'fixed'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                                : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                                        ]"
                                    >
                                        {{ voucher.type }}
                                    </span>
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300"
                                >
                                    {{ voucher.usage_limit || 'Unlimited' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div
                                        class="flex items-center gap-1 text-sm"
                                        :class="
                                            isExpired(
                                                voucher.expires_at,
                                                voucher,
                                            )
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-gray-600 dark:text-gray-300'
                                        "
                                    >
                                        <Calendar class="h-3 w-3" />
                                        <span>{{
                                            formatDate(
                                                voucher.expires_at,
                                                voucher,
                                            )
                                        }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        :class="
                                            getStatusColor(
                                                voucher.status,
                                                isExpired(
                                                    voucher.expires_at,
                                                    voucher,
                                                ) &&
                                                    voucher.status === 'active',
                                            )
                                        "
                                    >
                                        {{
                                            isExpired(
                                                voucher.expires_at,
                                                voucher,
                                            ) && voucher.status === 'active'
                                                ? 'Expired'
                                                : voucher.status
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                                >
                                    <button
                                        @click="openActions(voucher)"
                                        class="rounded-full p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-slate-700 dark:hover:text-blue-400"
                                    >
                                        <MoreVertical class="h-5 w-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="vouchers.data.length === 0">
                                <td
                                    colspan="8"
                                    class="px-6 py-10 text-center text-gray-500 dark:text-gray-400"
                                >
                                    <div
                                        class="flex flex-col items-center justify-center"
                                    >
                                        <Gift
                                            class="mb-3 h-12 w-12 text-gray-300 dark:text-gray-600"
                                        />
                                        <p class="text-lg font-medium">
                                            No vouchers found
                                        </p>
                                        <p class="text-sm">
                                            Create your first voucher to get
                                            started
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div
                    class="divide-y divide-gray-200 dark:divide-slate-700 md:hidden"
                >
                    <div
                        v-for="voucher in vouchers.data"
                        :key="voucher.id"
                        class="space-y-3 p-4"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <Tag
                                        class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                    />
                                    <span
                                        class="font-mono text-sm font-medium text-gray-900 dark:text-white"
                                        >{{ voucher.code }}</span
                                    >
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-xs font-semibold capitalize',
                                            voucher.type === 'fixed'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                                : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                                        ]"
                                    >
                                        {{ voucher.type }}
                                    </span>
                                    <span
                                        :class="
                                            getStatusColor(
                                                voucher.status,
                                                isExpired(
                                                    voucher.expires_at,
                                                    voucher,
                                                ) &&
                                                    voucher.status === 'active',
                                            )
                                        "
                                    >
                                        {{
                                            isExpired(
                                                voucher.expires_at,
                                                voucher,
                                            ) && voucher.status === 'active'
                                                ? 'Expired'
                                                : voucher.status
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div
                                    class="text-lg font-bold text-gray-900 dark:text-white"
                                >
                                    {{ voucher.value
                                    }}{{
                                        voucher.type === 'percentage' ? '%' : ''
                                    }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-300"
                        >
                            <div>
                                Usage: {{ voucher.usage_limit || 'Unlimited' }}
                            </div>
                            <div
                                :class="
                                    isExpired(voucher.expires_at, voucher)
                                        ? 'text-red-600 dark:text-red-400'
                                        : ''
                                "
                            >
                                {{ formatDate(voucher.expires_at, voucher) }}
                            </div>
                        </div>

                        <button
                            @click="openActions(voucher)"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-200 dark:hover:bg-slate-600"
                        >
                            <MoreVertical class="h-4 w-4" /> Manage Voucher
                        </button>
                    </div>
                    <div
                        v-if="vouchers.data.length === 0"
                        class="p-8 text-center text-gray-500 dark:text-gray-400"
                    >
                        No vouchers found.
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-show="vouchers.total > 0" class="mt-6 flex justify-center">
                <Pagination
                    :links="vouchers.links"
                    :per-page="vouchers.per_page"
                    :total="vouchers.total"
                    :from="vouchers.from"
                    :to="vouchers.to"
                />
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showFormModal" @close="closeFormModal">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h2
                    class="mb-4 text-lg font-medium text-gray-900 dark:text-white"
                >
                    Create New Voucher
                </h2>

                <form @submit.prevent="submitForm">
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="package_id" value="Package" />
                            <select
                                id="package_id"
                                v-model="form.package_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                required
                            >
                                <option value="">Select a package</option>
                                <option
                                    v-for="pkg in packages"
                                    :key="pkg.id"
                                    :value="pkg.id"
                                >
                                    {{ pkg.name }} - {{ currency }}
                                    {{ pkg.price }}
                                </option>
                            </select>
                            <InputError
                                class="mt-2"
                                :message="form.errors.package_id"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    for="prefix"
                                    value="Prefix (Optional)"
                                />
                                <TextInput
                                    id="prefix"
                                    type="text"
                                    maxlength="4"
                                    class="mt-1 block w-full uppercase"
                                    v-model="form.prefix"
                                    placeholder="e.g., VOC"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.prefix"
                                />
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Max 4 characters
                                </p>
                            </div>

                            <div>
                                <InputLabel for="length" value="Code Length" />
                                <TextInput
                                    id="length"
                                    type="number"
                                    min="6"
                                    max="20"
                                    class="mt-1 block w-full"
                                    v-model="form.length"
                                    required
                                />
                                <InputError
                                    class="mt-2"
                                    :message="form.errors.length"
                                />
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Minimum 6 characters
                                </p>
                            </div>
                        </div>

                        <div>
                            <InputLabel for="quantity" value="Quantity" />
                            <TextInput
                                id="quantity"
                                type="number"
                                min="1"
                                max="1000"
                                class="mt-1 block w-full"
                                v-model="form.quantity"
                                required
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.quantity"
                            />
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Number of vouchers to generate (max 1000)
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <DangerButton type="button" @click="closeFormModal"
                            >Cancel</DangerButton
                        >
                        <PrimaryButton
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Generate Vouchers
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Actions Modal -->
        <Modal
            :show="showActionsModal"
            @close="showActionsModal = false"
            maxWidth="sm"
        >
            <div
                class="p-4 dark:bg-slate-800 dark:text-white"
                v-if="selectedVoucher"
            >
                <div
                    class="mb-4 flex items-center justify-between border-b border-gray-100 pb-2 dark:border-slate-700"
                >
                    <h3
                        class="truncate pr-4 text-lg font-medium text-gray-900 dark:text-white"
                    >
                        {{ selectedVoucher.code }}
                    </h3>
                    <button
                        @click="showActionsModal = false"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                    >
                        <XCircle class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-1">
                    <!-- View Details Button -->
                    <button
                        @click="
                            viewVoucher(selectedVoucher);
                            showActionsModal = false;
                        "
                        class="group flex w-full items-center gap-3 rounded-lg p-2.5 text-left transition-colors hover:bg-gray-50 dark:hover:bg-slate-700"
                    >
                        <div
                            class="rounded-md bg-blue-50 p-1.5 text-blue-600 group-hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:group-hover:bg-blue-900/40"
                        >
                            <Eye class="h-4 w-4" />
                        </div>
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >View Details</span
                        >
                    </button>

                    <div
                        class="my-1 border-t border-gray-100 dark:border-slate-700"
                    ></div>

                    <!-- Delete Button -->
                    <button
                        @click="confirmVoucherDeletion(selectedVoucher)"
                        class="group flex w-full items-center gap-3 rounded-lg p-2.5 text-left transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
                    >
                        <div
                            class="rounded-md bg-red-50 p-1.5 text-red-600 group-hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:group-hover:bg-red-900/40"
                        >
                            <Trash2 class="h-4 w-4" />
                        </div>
                        <span
                            class="text-sm font-medium text-red-600 dark:text-red-400"
                            >Delete Voucher</span
                        >
                    </button>
                </div>
            </div>
        </Modal>

        <!-- View Voucher Modal -->
        <Modal :show="showViewModal" @close="closeViewModal" maxWidth="2xl">
            <div
                class="p-6 dark:bg-slate-800 dark:text-white"
                v-if="selectedVoucher"
            >
                <div class="mb-6 flex items-center justify-between">
                    <h3
                        class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white"
                    >
                        <Gift
                            class="h-5 w-5 text-blue-600 dark:text-blue-400"
                        />
                        Voucher Details
                    </h3>
                    <button
                        @click="closeViewModal"
                        class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                    >
                        <XCircle class="h-6 w-6" />
                    </button>
                </div>

                <!-- Voucher Code Card -->
                <div
                    class="mb-6 rounded-xl border border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50 p-6 dark:border-blue-800/30 dark:from-blue-900/20 dark:to-indigo-900/20"
                >
                    <div
                        class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p
                                class="mb-1 text-sm text-blue-600 dark:text-blue-400"
                            >
                                Voucher Code
                            </p>
                            <div class="flex items-center gap-3">
                                <span
                                    class="font-mono text-2xl font-bold text-gray-900 dark:text-white"
                                    >{{ selectedVoucher.code }}</span
                                >
                                <button
                                    @click="
                                        copyToClipboard(selectedVoucher.code)
                                    "
                                    class="rounded-lg p-1.5 transition-colors hover:bg-blue-100 dark:hover:bg-blue-900/40"
                                    title="Copy code"
                                >
                                    <Copy
                                        class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                    />
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'rounded-full px-3 py-1 text-sm font-semibold capitalize',
                                    getStatusColor(
                                        selectedVoucher.status,
                                        isExpired(
                                            selectedVoucher.expires_at,
                                            selectedVoucher,
                                        ) &&
                                            selectedVoucher.status === 'active',
                                    ),
                                ]"
                            >
                                <component
                                    :is="getStatusIcon(selectedVoucher.status)"
                                    class="mr-1 inline h-3 w-3"
                                />
                                {{
                                    isExpired(
                                        selectedVoucher.expires_at,
                                        selectedVoucher,
                                    ) && selectedVoucher.status === 'active'
                                        ? 'Expired'
                                        : selectedVoucher.status
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-slate-700/50"
                        >
                            <h4
                                class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                Value Information
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Type:</span
                                    >
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs font-semibold capitalize',
                                            selectedVoucher.type === 'fixed'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                                : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                                        ]"
                                    >
                                        {{ selectedVoucher.type }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Value:</span
                                    >
                                    <span
                                        class="text-lg font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ selectedVoucher.value
                                        }}{{
                                            selectedVoucher.type ===
                                            'percentage'
                                                ? '%'
                                                : ` ${currency}`
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-slate-700/50"
                        >
                            <h4
                                class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                Package Details
                            </h4>
                            <div
                                v-if="selectedVoucher.package"
                                class="space-y-3"
                            >
                                <div class="flex items-center gap-2">
                                    <Package
                                        class="h-4 w-4 text-blue-600 dark:text-blue-400"
                                    />
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                        >{{
                                            selectedVoucher.package.name
                                        }}</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Duration:</span
                                    >
                                    <span
                                        class="text-sm text-gray-900 dark:text-white"
                                    >
                                        {{
                                            selectedVoucher.package
                                                .duration_value
                                        }}
                                        {{
                                            selectedVoucher.package
                                                .duration_unit
                                        }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Price:</span
                                    >
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ currency }}
                                        {{ selectedVoucher.package.price }}
                                    </span>
                                </div>
                                <div
                                    v-if="selectedVoucher.package.description"
                                    class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ selectedVoucher.package.description }}
                                </div>
                            </div>
                            <div
                                v-else
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                No package associated
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-slate-700/50"
                        >
                            <h4
                                class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                Usage Information
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Usage Limit:</span
                                    >
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            selectedVoucher.usage_limit ||
                                            'Unlimited'
                                        }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Times Used:</span
                                    >
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ selectedVoucher.times_used || 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-lg bg-gray-50 p-4 dark:bg-slate-700/50"
                        >
                            <h4
                                class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                Timeline
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Created:</span
                                    >
                                    <span
                                        class="text-sm text-gray-900 dark:text-white"
                                        >{{
                                            formatDateTime(
                                                selectedVoucher.created_at,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center gap-2"
                                    :class="
                                        isExpired(
                                            selectedVoucher.expires_at,
                                            selectedVoucher,
                                        )
                                            ? 'text-red-600 dark:text-red-400'
                                            : ''
                                    "
                                >
                                    <Clock class="h-4 w-4" />
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Expires:</span
                                    >
                                    <span class="text-sm font-medium">{{
                                        formatDateTime(
                                            selectedVoucher.expires_at,
                                        ) || 'Never'
                                    }}</span>
                                </div>
                                <div
                                    v-if="selectedVoucher.used_at"
                                    class="flex items-center gap-2"
                                >
                                    <CheckCircle
                                        class="h-4 w-4 text-green-600 dark:text-green-400"
                                    />
                                    <span
                                        class="text-sm text-gray-600 dark:text-gray-300"
                                        >Used:</span
                                    >
                                    <span
                                        class="text-sm text-gray-900 dark:text-white"
                                        >{{
                                            formatDateTime(
                                                selectedVoucher.used_at,
                                            )
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="selectedVoucher.used_by"
                            class="rounded-lg bg-gray-50 p-4 dark:bg-slate-700/50"
                        >
                            <h4
                                class="mb-3 text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                Used By
                            </h4>
                            <div class="flex items-center gap-2">
                                <User class="h-4 w-4 text-gray-400" />
                                <span
                                    class="text-sm text-gray-900 dark:text-white"
                                    >{{ selectedVoucher.used_by.name }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-end gap-3">
                    <DangerButton @click="closeViewModal"> Close </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
