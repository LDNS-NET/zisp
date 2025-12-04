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
    DollarSign
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
});

const page = usePage();
const currency = computed(() => page.props.auth.user.currency || 'KES');

const editing = ref(null);
const showModal = ref(false);
const showActionsModal = ref(false);
const selectedPackage = ref(null);
const selectedPackages = ref([]);

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
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.type = 'hotspot';
    form.duration_unit = 'days';
    showModal.value = true;
}

function openEdit(pkg) {
    editing.value = pkg.id;
    form.name = pkg.name;
    form.price = pkg.price;
    form.duration_value = pkg.duration_value;
    form.duration_unit = pkg.duration_unit || 'days';
    form.type = pkg.type;
    form.upload_speed = pkg.upload_speed;
    form.download_speed = pkg.download_speed;
    form.burst_limit = pkg.burst_limit;
    form.device_limit = pkg.device_limit;
    showModal.value = true;
}

function openActions(pkg) {
    selectedPackage.value = pkg;
    showActionsModal.value = true;
}

const selectAll = ref(false);

watch(selectAll, (val) => {
    if (val) {
        selectedPackages.value = props.packages.map((pkg) => pkg.id);
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
        router.delete(route('packages.destroy', pkg.id), {
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
                <PrimaryButton @click="openCreate" class="flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    <span>Add Package</span>
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Bulk Actions -->
            <div v-if="selectedPackages.length" class="flex items-center gap-2 bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ selectedPackages.length }} selected</span>
                <DangerButton @click="bulkDelete" class="flex items-center gap-2">
                    <Trash2 class="w-4 h-4" /> Delete
                </DangerButton>
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
                                    <input type="checkbox" :value="pkg.id" v-model="selectedPackages" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
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
                                        <DollarSign class="w-3 h-3 text-green-600 dark:text-green-400" />
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
            <div v-if="pagination && pagination.links && pagination.links.length > 3" class="flex justify-center mt-6">
                <Pagination :links="pagination.links" />
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
                            <TextInput id="device_limit" v-model="form.device_limit" type="number" class="mt-1 w-full" />
                            <InputError :message="form.errors.device_limit" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
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
    </AuthenticatedLayout>
</template>
