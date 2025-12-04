<script setup>
import { ref, computed, watch } from 'vue';
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
    Wifi
} from 'lucide-vue-next';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const toast = useToast();

const props = defineProps({
    users: Object,
    filters: Object,
    counts: Object,
    packages: Object,
});

const showModal = ref(false);
const editing = ref(null);
const viewing = ref(null);
const selectedFilter = ref(props.filters?.type || 'all');
const search = ref(props.filters?.search || '');

const form = useForm({
    full_name: '',
    username: '',
    password: '',
    phone: '',
    email: '',
    location: '',
    package_id: '',
    type: 'hotspot',
    expires_at: '',
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

function openCreate() {
    editing.value = null;
    form.reset();
    form.type = 'hotspot';
    showModal.value = true;
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
    form.email = user.email ?? '';
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
                <PrimaryButton @click="openCreate" class="flex items-center gap-2">
                    <UserPlus class="w-4 h-4" />
                    <span>Add User</span>
                </PrimaryButton>
            </div>
        </template>

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
                        <div v-for="user in users.data" :key="user.id" class="p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                            <div class="flex items-start gap-3">
                                <input 
                                    type="checkbox"
                                    :value="user.id"
                                    v-model="selectedUsers"
                                    class="mt-1 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-800 dark:border-slate-600"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm">
                                                {{ user.username.charAt(0).toUpperCase() }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.username }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.full_name }}</div>
                                            </div>
                                        </div>
                                        <span :class="[
                                            'px-2 py-0.5 text-xs font-semibold rounded-full',
                                            user.is_online 
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' 
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                        ]">
                                            {{ user.is_online ? 'Online' : 'Offline' }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                                        <div class="text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                            <Smartphone class="w-3 h-3" /> {{ user.phone }}
                                        </div>
                                        <div class="text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                            <Wifi class="w-3 h-3" /> {{ user.package?.name || '-' }}
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-slate-700/50">
                                        <div class="text-xs text-gray-400 flex items-center gap-1">
                                            <Calendar class="w-3 h-3" />
                                            Exp: {{ user.expiry_human }}
                                        </div>
                                        <button @click="openActions(user)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            <MoreVertical class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="users.data.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <UserCheck class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                <p class="text-lg font-medium">No users found</p>
                                <p class="text-sm">Try adjusting your search or filters</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Pagination :links="users.links" />
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
                        <div>
                            <InputLabel for="email" value="Email Address" />
                            <TextInput v-model="form.email" id="email" type="email" class="mt-1 block w-full" />
                            <InputError :message="form.errors.email" />
                        </div>
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
    </AuthenticatedLayout>
</template>
