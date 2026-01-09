<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, Link, router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';
import { 
    UserPlus, 
    UserCheck, 
    Search, 
    Filter, 
    MoreVertical, 
    Edit, 
    Trash2, 
    Eye, 
    CheckCircle, 
    XCircle,
    Smartphone,
    MapPin,
    Calendar,
    Wifi,
    Upload,
    FileText,
    AlertCircle
} from 'lucide-vue-next';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const toast = useToast();

const props = defineProps({
    users: Object,
    filters: Object,
    counts: { type: Object, default: () => ({ all: 0, hotspot: 0, pppoe: 0, static: 0, online: 0, expired: 0 }) },
    packages: Object,
    activeUsernames: Array,
    debugInfo: Object,
});

const showModal = ref(false);
const showImportModal = ref(false);
const editing = ref(null);
const viewing = ref(null);
const selectedFilter = ref(props.filters?.type || 'all');
const search = ref(props.filters?.search || '');

const form = useForm({
    full_name: '',
    username: '',
    password: '',
    phone: '',
    // email: '',
    location: '',
    package_id: '',
    type: 'hotspot',
    expires_at: '',
});

const importForm = useForm({
    file: null,
});

// Watchers for filters
watch(selectedFilter, (value) => {
    router.get(
        route('users.index'), 
        { type: value, search: search.value }, 
        { preserveScroll: true }
    );
});

let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('users.index'),
            { type: selectedFilter.value, search: value },
            { preserveScroll: true }
        );
    }, 300);
});

onMounted(() => {
    // Poll for real-time status updates every 5 seconds
    const interval = setInterval(() => {
        router.reload({
            only: ['users', 'counts'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 5000);

    // Clean up interval on unmount
    return () => clearInterval(interval);
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.type = 'hotspot';
    showModal.value = true;
}

function openImport() {
    importForm.reset();
    showImportModal.value = true;
}

function submitImport() {
    importForm.post(route('users.import'), {
        onSuccess: (page) => {
            showImportModal.value = false;
            importForm.reset();
            toast.success('Import process completed');
            
            // Check for import errors in session (passed from backend)
            // Ideally backend returns them in flash props or errors bag.
            // Our backend puts 'import_errors' in session flash. 
            // Inertia props should update automatically.
            if (page.props.flash?.import_errors && page.props.flash.import_errors.length > 0) {
                // We might want to show a toast or a modal with errors.
                // For now, let's just toast a warning or rely on the user checking the success message which mentions errors.
                 toast.warning(`Some rows were skipped. Check the success message.`);
            }
        },
        onError: () => {
            toast.error('Failed to import file.');
        },
        forceFormData: true,
    });
}

function downloadSample() {
    // Generate a simple CSV content
    const csvContent = "username,phone,full_name,location,type,package,password\njohn_doe,0712345678,John Doe,Nairobi,hotspot,Weekly Bundle,secret123\njane_smith,0723456789,Jane Smith,Mombasa,pppoe,Home Fiber,securePass";
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "users_import_sample.csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

const selectedUsers = ref([]);

const bulkDelete = () => {
    if (selectedUsers.value.length && confirm(`Are you sure you want to delete ${selectedUsers.value.length} users?`)) {
        router.delete(route('users.bulk-delete'), {
            data: { ids: selectedUsers.value },
            onSuccess: () => {
                selectedUsers.value = [];
                toast.success('Users successfully deleted');
            },
        });
    }
};

function openEdit(user) {
    editing.value = user.id;
    form.full_name = user.full_name ?? '';
    form.username = user.username ?? '';
    form.password = ''; // Don't show current password
    form.phone = user.phone ?? '';
    // form.email = user.email ?? '';
    form.location = user.location ?? '';
    form.package_id = user.package_id ?? '';
    form.type = user.type ?? 'hotspot';
    form.expires_at = user.expires_at ? user.expires_at.slice(0, 16) : '';
    showModal.value = true;
}

function submit() {
    const options = {
        onSuccess: () => {
            showModal.value = false;
            toast.success(editing.value ? 'User updated successfully' : 'User created successfully');
        },
        onError: () => {
            toast.error('Please check the form for errors.');
        },
    };

    if (editing.value) {
        form.put(route('users.update', editing.value), options);
    } else {
        form.post(route('users.store'), options);
    }
}

function remove(id) {
    if (confirm('Are you sure you want to delete this User?')) {
        router.delete(route('users.destroy', id), {
            preserveScroll: true,
            onSuccess: () => toast.success('User deleted successfully'),
            onError: () => toast.error('Failed to delete user'),
        });
    }
}

function viewUser(user) {
    viewing.value = user;
}

const packagesByType = computed(() => {
    return props.packages[form.type] || [];
});

const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedUsers.value = props.users.data.map(u => u.id);
    } else {
        selectedUsers.value = [];
    }
};

const showActionsModal = ref(false);
const selectedUserForActions = ref(null);

const openActions = (user) => {
    selectedUserForActions.value = user;
    showActionsModal.value = true;
};
</script>

<template>
    <Head title="Users" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <UserCheck class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        User Management
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your hotspot and PPPoE subscribers
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button 
                        @click="openImport"
                        class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        <Upload class="w-4 h-4" />
                        <span>Import CSV</span>
                    </button>
                    <PrimaryButton @click="openCreate" class="flex items-center gap-2">
                        <UserPlus class="w-4 h-4" />
                        <span>Add User</span>
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div v-if="true" class="bg-gray-100 dark:bg-slate-900 p-2 text-[10px] font-mono mb-4 rounded border border-gray-200 dark:border-slate-700">
            Debug Info: {{ debugInfo }} | Active Usernames: {{ activeUsernames }}
        </div>

        <div class="space-y-6">
            <!-- Filters & Search -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
                <!-- Tabs -->
                <div class="flex p-1 space-x-1 bg-gray-100 dark:bg-slate-900 rounded-lg w-full sm:w-auto overflow-x-auto">
                    <button
                        v-for="type in ['all', 'hotspot', 'pppoe', 'static']"
                        :key="type"
                        @click="selectedFilter = type"
                        :class="[
                            'px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 whitespace-nowrap',
                            selectedFilter === type
                                ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm'
                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                        ]"
                    >
                        {{ type.charAt(0).toUpperCase() + type.slice(1) }}
                        <span class="ml-1 text-xs opacity-70 bg-gray-200 dark:bg-slate-800 px-1.5 py-0.5 rounded-full">
                            {{ counts[type] || 0 }}
                        </span>
                    </button>
                </div>

                <!-- Search -->
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search users..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out"
                    />
                </div>
            </div>

            <!-- Bulk Actions -->
            <div v-if="selectedUsers.length" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 flex items-center justify-between animate-fade-in">
                <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
                    <CheckCircle class="w-5 h-5" />
                    <span class="font-medium">{{ selectedUsers.length }} users selected</span>
                </div>
                <DangerButton @click="bulkDelete" class="flex items-center gap-2">
                    <Trash2 class="w-4 h-4" />
                    Delete Selected
                </DangerButton>
            </div>

            <!-- Users Table (Desktop) / Cards (Mobile) -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        :checked="selectedUsers.length === users.data.length && users.data.length > 0"
                                        @change="toggleSelectAll"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-800 dark:border-slate-600"
                                    />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User Details</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Package</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer" @click="$inertia.visit(route('users.show', user.id))">
                                <td class="px-6 py-4 whitespace-nowrap" @click.stop>
                                    <input
                                        type="checkbox"
                                        :value="user.id"
                                        v-model="selectedUsers"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-800 dark:border-slate-600"
                                    />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center group">
                                        <div class="h-10 w-10 flex-shrink-0 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm group-hover:ring-2 group-hover:ring-blue-500 transition-all">
                                            {{ user.username.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ user.username }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.full_name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-gray-300 flex items-center gap-2">
                                        <Smartphone class="w-3 h-3 text-gray-400" />
                                        {{ user.phone }}
                                    </div>
                                    <div v-if="user.location" class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2 mt-1">
                                        <MapPin class="w-3 h-3" />
                                        {{ user.location }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white font-medium">{{ user.package?.name || 'No Package' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1 mt-1">
                                        <Calendar class="w-3 h-3" />
                                        {{ user.expiry_human }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        user.is_online 
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' 
                                            : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    ]">
                                        {{ user.is_online ? 'Online' : 'Offline' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" @click.stop>
                                    <button @click="openActions(user)" class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors" title="Actions">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <UserCheck class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                        <p class="text-lg font-medium">No users found</p>
                                        <p class="text-sm">Try adjusting your search or filters</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden">
                    <div v-if="selectedUsers.length > 0" class="p-4 bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                        <label class="flex items-center">
                            <input 
                                type="checkbox"
                                :checked="selectedUsers.length === users.data.length && users.data.length > 0"
                                @change="toggleSelectAll"
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-800 dark:border-slate-600"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Select All</span>
                        </label>
                    </div>

                    <div class="divide-y divide-gray-200 dark:divide-slate-700">
                        <div v-for="user in users.data" :key="user.id" 
                            class="p-3 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer"
                            @click="$inertia.visit(route('users.show', user.id))">
                            <div class="flex items-center gap-3">
                                <input 
                                    type="checkbox"
                                    :value="user.id"
                                    v-model="selectedUsers"
                                    @click.stop
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-800 dark:border-slate-600"
                                />
                                <div class="h-9 w-9 flex-shrink-0 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-xs">
                                    {{ user.username.charAt(0).toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <div class="truncate">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.username }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.full_name || user.phone }}</div>
                                        </div>
                                        <span :class="[
                                            'ml-2 flex-shrink-0 px-1.5 py-0.5 text-[10px] font-semibold rounded-full',
                                            user.is_online 
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' 
                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                        ]">
                                            {{ user.is_online ? '●' : '○' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between mt-1">
                                        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="flex items-center gap-0.5">
                                                <Wifi class="w-3 h-3" />
                                                {{ user.package?.name || '-' }}
                                            </span>
                                            <span class="flex items-center gap-0.5">
                                                <Calendar class="w-3 h-3" />
                                                {{ user.expiry_human }}
                                            </span>
                                        </div>
                                        <button @click.stop="openActions(user)" class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                                            <MoreVertical class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="users.data.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <UserCheck class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" />
                                <p class="text-sm font-medium">No users found</p>
                                <p class="text-xs">Try adjusting your filters</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Pagination 
                :links="users.links" 
                :per-page="users.per_page" 
                :total="users.total" 
                :from="users.from" 
                :to="users.to" 
            />
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ editing ? 'Edit User' : 'Create New User' }}
                </h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="full_name" value="Full Name" />
                            <TextInput v-model="form.full_name" id="full_name" class="mt-1 block w-full" />
                            <InputError :message="form.errors.full_name" />
                        </div>
                        <div>
                            <InputLabel for="username" value="Username" />
                            <TextInput v-model="form.username" id="username" class="mt-1 block w-full" />
                            <InputError :message="form.errors.username" />
                        </div>
                        <div>
                            <InputLabel for="password" value="Password" />
                            <TextInput v-model="form.password" id="password" type="text" class="mt-1 block w-full" placeholder="Leave empty to keep current" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Phone Number" />
                            <TextInput v-model="form.phone" id="phone" class="mt-1 block w-full" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <!-- <div>
                            <InputLabel for="email" value="Email Address" />
                            <TextInput v-model="form.email" id="email" type="email" class="mt-1 block w-full" />
                            <InputError :message="form.errors.email" />
                        </div> -->
                        <div>
                            <InputLabel for="location" value="Location" />
                            <TextInput v-model="form.location" id="location" class="mt-1 block w-full" />
                            <InputError :message="form.errors.location" />
                        </div>
                        <div>
                            <InputLabel for="type" value="User Type" />
                            <select v-model="form.type" id="type" class="mt-1 block w-full border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm">
                                <option value="hotspot">Hotspot</option>
                                <option value="pppoe">PPPoE</option>
                                <option value="static">Static</option>
                            </select>
                            <InputError :message="form.errors.type" />
                        </div>
                        <div>
                            <InputLabel for="package_id" value="Package" />
                            <select v-model="form.package_id" id="package_id" class="mt-1 block w-full border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm">
                                <option value="">Select Package</option>
                                <option v-for="pkg in packagesByType" :key="pkg.id" :value="pkg.id">
                                    {{ pkg.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.package_id" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="expires_at" value="Expiry Date" />
                            <TextInput id="expires_at" type="datetime-local" v-model="form.expires_at" class="mt-1 block w-full" />
                            <InputError :message="form.errors.expires_at" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="showModal = false">Cancel</DangerButton>
                        <PrimaryButton :disabled="form.processing">{{ editing ? 'Update User' : 'Create User' }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
        
        <!-- Actions Modal (Mobile) -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-6 dark:bg-slate-800" v-if="selectedUserForActions">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Actions for {{ selectedUserForActions.username }}
                </h3>
                <div class="space-y-3">
                    <button 
                        @click="$inertia.visit(route('users.show', selectedUserForActions.id)); showActionsModal = false"
                        class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                    >
                        <Eye class="w-4 h-4 mr-3 text-blue-500" />
                        View Details
                    </button>
                    
                    <button 
                        @click="openEdit(selectedUserForActions); showActionsModal = false"
                        class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                    >
                        <Edit class="w-4 h-4 mr-3 text-amber-500" />
                        Edit User
                    </button>
                    
                    <button 
                        @click="remove(selectedUserForActions.id); showActionsModal = false"
                        class="w-full flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                    >
                        <Trash2 class="w-4 h-4 mr-3" />
                        Delete User
                    </button>
                </div>
                <div class="mt-6 flex justify-end">
                    <button 
                        @click="showActionsModal = false"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
        <!-- Import Modal -->
        <Modal :show="showImportModal" @close="showImportModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Import Users via CSV
                    </h3>
                    <button @click="showImportModal = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                        <span class="sr-only">Close</span>
                        <XCircle class="w-6 h-6" />
                    </button>
                </div>

                <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 p-4 rounded-r-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <AlertCircle class="h-5 w-5 text-blue-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Upload a CSV file with the following columns: <br>
                                <span class="font-mono text-xs">username, phone, full_name, location, type, package, password</span>
                            </p>
                            <button 
                                type="button" 
                                @click="downloadSample" 
                                class="mt-2 text-sm font-medium text-blue-700 dark:text-blue-300 underline hover:text-blue-600"
                            >
                                Download Sample CSV
                            </button>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitImport" class="space-y-4">
                    <div class="space-y-2">
                        <InputLabel for="file" value="Select CSV File" />
                        <div class="relative border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-6 hover:border-blue-500 dark:hover:border-blue-500 transition-colors text-center cursor-pointer"
                             @dragover.prevent
                             @drop.prevent="(e) => importForm.file = e.dataTransfer.files[0]">
                            
                            <input 
                                type="file" 
                                id="file" 
                                accept=".csv,.txt"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                @change="(e) => importForm.file = e.target.files[0]"
                            />
                            
                            <div class="space-y-2" v-if="!importForm.file">
                                <Upload class="mx-auto h-10 w-10 text-gray-400" />
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium text-blue-600 hover:text-blue-500">Click to upload</span> or drag and drop
                                </div>
                                <p class="text-xs text-gray-500">CSV or TXT up to 5MB</p>
                            </div>
                            
                            <div v-else class="flex items-center justify-center gap-3">
                                <FileText class="h-8 w-8 text-blue-500" />
                                <div class="text-left">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ importForm.file.name }}</p>
                                    <p class="text-xs text-gray-500">{{ (importForm.file.size / 1024).toFixed(1) }} KB</p>
                                </div>
                                <button type="button" @click.stop="importForm.file = null" class="p-1 hover:bg-gray-200 dark:hover:bg-slate-700 rounded-full">
                                    <XCircle class="w-5 h-5 text-gray-500" />
                                </button>
                            </div>
                        </div>
                        <InputError :message="importForm.errors.file" />
                    </div>

                    <!-- Import Errors Display -->
                    <div v-if="$page.props.flash.import_errors && $page.props.flash.import_errors.length > 0" class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-md">
                         <h4 class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">Import Warnings/Errors</h4>
                         <ul class="list-disc pl-5 text-xs text-red-700 dark:text-red-400 max-h-32 overflow-y-auto">
                             <li v-for="(err, idx) in $page.props.flash.import_errors" :key="idx">{{ err }}</li>
                         </ul>
                    </div>
                
                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="showImportModal = false">Cancel</DangerButton>
                        <PrimaryButton 
                            :disabled="importForm.processing || !importForm.file"
                            :class="{ 'opacity-25': importForm.processing || !importForm.file }"
                        >
                            <span v-if="importForm.processing">Importing...</span>
                            <span v-else>Start Import</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
