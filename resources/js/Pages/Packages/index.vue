<script setup>
import { ref, watch, computed } from 'vue';
import { router, useForm, Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    Plus, 
    Edit, 
    Trash2, 
    Wifi, 
    Save, 
    X, 
    MoreVertical,
    XCircle,
    Package as PackageIcon,
    Zap,
    Clock,
    DollarSign,
    Search,
    Layers,
    Settings2
} from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    packages: Array,
    counts: Object,
    filters: Object,
    pagination: Object,
    currency: String,
    categories: Array,
});

const page = usePage();
const currency = computed(() => props.currency || page.props.tenant?.currency || 'KES');

const search = ref(props.filters?.search || '');
let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('packages.index'),
            { search: value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 500);
});

const editing = ref(null);
const showModal = ref(false);
const showActionsModal = ref(false);
const selectedPackage = ref(null);
const selectedPackages = ref([]);

const showCategoryModal = ref(false);
const editingCategory = ref(null);
const categoryForm = useForm({
    name: '',
    display_order: 0,
    is_default: false,
});

const form = useForm({
    name: '',
    price: '',
    duration_value: '',
    duration_unit: 'days',
    type: 'hotspot',
    upload_speed: '',
    download_speed: '',
    burst_limit: '',
    device_limit: '',
    hotspot_category_id: null,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.type = 'hotspot';
    form.duration_unit = 'days';
    showModal.value = true;
}

function openEdit(pkg) {
    editing.value = pkg.uuid;
    form.name = pkg.name;
    form.price = pkg.price;
    form.duration_value = pkg.duration_value;
    form.duration_unit = pkg.duration_unit || 'days';
    form.type = pkg.type;
    form.upload_speed = pkg.upload_speed;
    form.download_speed = pkg.download_speed;
    form.burst_limit = pkg.burst_limit;
    form.device_limit = pkg.device_limit;
    form.hotspot_category_id = pkg.hotspot_category_id;
    showModal.value = true;
}

function openActions(pkg) {
    selectedPackage.value = pkg;
    showActionsModal.value = true;
}

const selectAll = ref(false);

watch(selectAll, (val) => {
    if (val) {
        selectedPackages.value = props.packages.map((pkg) => pkg.uuid);
    } else {
        selectedPackages.value = [];
    }
});

watch(selectedPackages, (val) => {
    selectAll.value = val.length === props.packages.length && props.packages.length > 0;
});

const bulkDelete = () => {
    if (!selectedPackages.value.length) return;
    if (!confirm('Are you sure you want to delete selected packages?')) return;

    router.delete(route('packages.bulk-delete'), {
        data: { ids: selectedPackages.value },
        onSuccess: () => {
            selectedPackages.value = [];
            selectAll.value = false;
            toast.success('Packages deleted successfully');
        },
    });
};

function submit() {
    if (editing.value) {
        form.put(route('packages.update', editing.value), {
            onSuccess: () => {
                showModal.value = false;
                toast.success('Package updated successfully');
            },
            onError: () => {
                toast.error('Failed to update package');
            },
        });
    } else {
        form.post(route('packages.store'), {
            onSuccess: () => {
                showModal.value = false;
                toast.success('Package created successfully');
            },
            onError: () => {
                toast.error('Failed to create package');
            },
        });
    }
}

function remove(pkg) {
    if (confirm('Are you sure you want to delete this package?')) {
        router.delete(route('packages.destroy', pkg.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Package deleted successfully');
            },
            onError: () => {
                toast.error('Failed to delete package');
            },
        });
    }
}

function editCategory(cat) {
    editingCategory.value = cat.id;
    categoryForm.name = cat.name;
    categoryForm.display_order = cat.display_order;
    categoryForm.is_default = !!cat.is_default;
}

function cancelCategoryEdit() {
    editingCategory.value = null;
    categoryForm.reset();
}

function deleteCategory(cat) {
    if (confirm(`Are you sure you want to delete the category "${cat.name}"?`)) {
        router.delete(route('settings.hotspot.categories.destroy', cat.id), {
            onSuccess: () => toast.success('Category deleted successfully'),
            onError: () => toast.error('Failed to delete category'),
        });
    }
}

function submitCategory() {
    if (editingCategory.value) {
        categoryForm.put(route('settings.hotspot.categories.update', editingCategory.value), {
            onSuccess: () => {
                editingCategory.value = null;
                categoryForm.reset();
                toast.success('Category updated successfully');
            },
            onError: () => toast.error('Failed to update category'),
        });
    } else {
        categoryForm.post(route('settings.hotspot.categories.store'), {
            onSuccess: () => {
                categoryForm.reset();
                toast.success('Category added successfully');
            },
            onError: () => toast.error('Failed to add category'),
        });
    }
}
</script>

<template>
    <Head title="Packages" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <PackageIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        Internet Packages
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your service packages and pricing
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <PrimaryButton @click="showCategoryModal = true" class="flex items-center gap-2 bg-slate-100 !text-slate-700 hover:bg-slate-200 border-none shadow-none">
                        <Layers class="w-4 h-4" />
                        <span>Add Category</span>
                    </PrimaryButton>
                    <PrimaryButton @click="openCreate" class="flex items-center gap-2">
                        <Plus class="w-4 h-4" />
                        <span>Add Package</span>
                    </PrimaryButton>
                </div>
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
                        placeholder="Search packages..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out"
                    />
                </div>

                <div v-if="selectedPackages.length" class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ selectedPackages.length }} selected</span>
                    <DangerButton @click="bulkDelete" class="flex items-center gap-2">
                        <Trash2 class="w-4 h-4" /> Delete
                    </DangerButton>
                </div>
            </div>

            <!-- Packages Table (Desktop) / Cards (Mobile) -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left w-10">
                                    <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Package</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Speed</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="pkg in packages" :key="pkg.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" :value="pkg.uuid" v-model="selectedPackages" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                            <Wifi class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ pkg.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                        pkg.type === 'hotspot' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' :
                                        pkg.type === 'pppoe' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                    ]">
                                        {{ pkg.type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                    <div class="flex items-center gap-1">
                                        <Zap class="w-3 h-3 text-amber-500" />
                                        <span>{{ pkg.upload_speed }}/{{ pkg.download_speed }} Mbps</span>
                                    </div>
                                    <div v-if="pkg.burst_limit" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Burst: {{ pkg.burst_limit }} Mbps
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1 text-sm font-medium text-gray-900 dark:text-white">
                                        <span class="text-green-600 dark:text-green-400">{{ currency }}</span>
                                        <span>{{ pkg.price }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                    <div class="flex items-center gap-1">
                                        <Clock class="w-3 h-3 text-gray-400" />
                                        <span>{{ pkg.duration_value }} {{ pkg.duration_unit }}</span>
                                    </div>
                                    <div v-if="pkg.duration_in_days" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        (~{{ pkg.duration_in_days }} days)
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openActions(pkg)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="packages.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <PackageIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                        <p class="text-lg font-medium">No packages found</p>
                                        <p class="text-sm">Create your first package to get started</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-gray-200 dark:divide-slate-700">
                    <div v-for="pkg in packages" :key="pkg.id" class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <Wifi class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ pkg.name }}</div>
                                    <span :class="[
                                        'inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                        pkg.type === 'hotspot' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' :
                                        pkg.type === 'pppoe' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                                    ]">
                                        {{ pkg.type }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ currency }} {{ pkg.price }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="flex items-center gap-1 text-gray-600 dark:text-gray-300">
                                <Zap class="w-4 h-4 text-amber-500" />
                                <span>{{ pkg.upload_speed }}/{{ pkg.download_speed }} Mbps</span>
                            </div>
                            <div class="flex items-center gap-1 text-gray-600 dark:text-gray-300">
                                <Clock class="w-4 h-4 text-gray-400" />
                                <span>{{ pkg.duration_value }} {{ pkg.duration_unit }}</span>
                            </div>
                        </div>

                        <button @click="openActions(pkg)" class="w-full flex items-center justify-center gap-2 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                            <MoreVertical class="w-4 h-4" /> Manage Package
                        </button>
                    </div>
                    <div v-if="packages.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No packages found.
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-show="pagination.total > 0" class="flex justify-center mt-6">
                <Pagination 
                    :links="pagination.links" 
                    :per-page="pagination.per_page"
                    :total="pagination.total"
                    :from="pagination.from"
                    :to="pagination.to"
                />
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ editing ? 'Edit Package' : 'Create Package' }}
                </h3>
                <form @submit.prevent="submit">
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="name" value="Package Name" />
                            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autofocus />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="price" :value="`Price (${currency})`" />
                                <TextInput id="price" v-model="form.price" type="number" step="0.01" class="mt-1 w-full" required />
                                <InputError :message="form.errors.price" />
                            </div>

                            <div>
                                <InputLabel for="type" value="Package Type" />
                                <select v-model="form.type" id="type" class="mt-1 w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="hotspot">Hotspot</option>
                                    <option value="pppoe">PPPoE</option>
                                    <option value="static">Static</option>
                                </select>
                                <InputError :message="form.errors.type" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="duration" value="Duration" />
                            <div class="flex gap-2">
                                <TextInput id="duration_value" v-model="form.duration_value" type="number" min="1" class="mt-1 w-1/2" required />
                                <select v-model="form.duration_unit" class="mt-1 w-1/2 rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="minutes">Minutes</option>
                                    <option value="hours">Hours</option>
                                    <option value="days">Days</option>
                                    <option value="weeks">Weeks</option>
                                    <option value="months">Months</option>
                                </select>
                            </div>
                            <InputError :message="form.errors.duration" />
                        </div>

                        <div v-if="form.type === 'hotspot'">
                            <InputLabel for="device_limit" value="Device Limit" />
                            <TextInput id="device_limit" type="number" class="mt-1 block w-full" v-model="form.device_limit" required />
                            <InputError class="mt-2" :message="form.errors.device_limit" />
                        </div>

                        <div class="mt-4" v-if="form.type === 'hotspot'">
                            <InputLabel for="hotspot_category_id" value="Hotspot Category" />
                            <select id="hotspot_category_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" v-model="form.hotspot_category_id">
                                <option :value="null">Select Category</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.hotspot_category_id" />
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="upload_speed" value="Upload Speed (Mbps)" />
                                <TextInput id="upload_speed" v-model="form.upload_speed" type="number" class="mt-1 w-full" required />
                                <InputError :message="form.errors.upload_speed" />
                            </div>
                            <div>
                                <InputLabel for="download_speed" value="Download Speed (Mbps)" />
                                <TextInput id="download_speed" v-model="form.download_speed" type="number" class="mt-1 w-full" required />
                                <InputError :message="form.errors.download_speed" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="burst_limit" value="Burst Limit (Optional)" />
                            <TextInput id="burst_limit" v-model="form.burst_limit" type="number" class="mt-1 w-full" />
                            <InputError :message="form.errors.burst_limit" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="showModal = false">Cancel</DangerButton>
                        <PrimaryButton :disabled="form.processing">{{ editing ? 'Update Package' : 'Create Package' }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Actions Modal (Compact) -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-4 dark:bg-slate-800 dark:text-white" v-if="selectedPackage">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4">
                        {{ selectedPackage.name }}
                    </h3>
                    <button @click="showActionsModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-1">
                    <button @click="openEdit(selectedPackage); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40">
                            <Edit class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Edit Package</span>
                    </button>

                    <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>

                    <button @click="remove(selectedPackage); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 group-hover:bg-red-100 dark:group-hover:bg-red-900/40">
                            <Trash2 class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">Delete Package</span>
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Category Management Modal -->
        <Modal :show="showCategoryModal" @close="showCategoryModal = false" maxWidth="md">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
                        <Layers class="w-5 h-5 text-blue-600" />
                        Hotspot Categories
                    </h3>
                    <button @click="showCategoryModal = false" class="text-gray-400 hover:text-gray-500">
                        <XCircle class="w-5 h-5" />
                    </button>
                </div>

                <!-- Add/Edit Category Form -->
                <form @submit.prevent="submitCategory" class="mb-6 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-100 dark:border-slate-700">
                    <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wider">
                        {{ editingCategory ? 'Edit Category' : 'Quick Add Category' }}
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="cat_name" value="Category Name" />
                            <TextInput id="cat_name" v-model="categoryForm.name" class="mt-1 block w-full" placeholder="e.g. Daily, Monthly" required />
                            <InputError :message="categoryForm.errors.name" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="cat_order" value="Display Order" />
                                <TextInput id="cat_order" type="number" v-model="categoryForm.display_order" class="mt-1 block w-full" />
                                <InputError :message="categoryForm.errors.display_order" />
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" v-model="categoryForm.is_default" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                    <span class="text-xs font-medium text-slate-600 dark:text-slate-400 group-hover:text-blue-600 transition-colors">Default</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <PrimaryButton :disabled="categoryForm.processing" class="flex-1 justify-center">
                                <Save v-if="editingCategory" class="w-4 h-4 mr-2" />
                                <Plus v-else class="w-4 h-4 mr-2" />
                                {{ editingCategory ? 'Update' : 'Add' }}
                            </PrimaryButton>
                            <button v-if="editingCategory" type="button" @click="cancelCategoryEdit" class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>

                <!-- List of Categories -->
                <div v-if="categories && categories.length > 0">
                    <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wider">Current Categories</h4>
                    <div class="space-y-2 max-h-60 overflow-y-auto pr-2">
                        <div v-for="cat in categories" :key="cat.id" class="flex items-center justify-between p-3 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-lg shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center font-bold text-blue-600 dark:text-blue-400 text-xs">
                                    {{ cat.display_order }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-slate-900 dark:text-white">{{ cat.name }}</span>
                                        <span v-if="cat.is_default" class="px-1.5 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-md">DEFAULT</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button @click="editCategory(cat)" class="p-1.5 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors rounded-md hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <Edit class="w-4 h-4" />
                                </button>
                                <button @click="deleteCategory(cat)" class="p-1.5 text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded-md hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-6 text-slate-500 text-sm">
                    No categories created yet.
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
