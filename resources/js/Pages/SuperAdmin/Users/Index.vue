<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Search, Filter, MoreVertical, Trash2, Eye, Ban, CheckCircle, AlertCircle, AlertTriangle } from 'lucide-vue-next';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    users: Object,
    filters: Object,
    countries: Array,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const country = ref(props.filters.country || '');

// Modal State
const showDeleteModal = ref(false);
const showSuspendModal = ref(false);
const selectedUser = ref(null);

// Debounced search
const updateSearch = debounce((value) => {
    router.get(
        route('superadmin.users.index'),
        { search: value, status: status.value, country: country.value },
        { preserveState: true, replace: true }
    );
}, 300);

// Watch filters
watch([status, country], () => {
    router.get(
        route('superadmin.users.index'),
        { search: search.value, status: status.value, country: country.value },
        { preserveState: true, replace: true }
    );
});

// Action Handlers
const openDeleteModal = (user) => {
    selectedUser.value = user;
    showDeleteModal.value = true;
};

const openSuspendModal = (user) => {
    selectedUser.value = user;
    showSuspendModal.value = true;
};

const closeModal = () => {
    showDeleteModal.value = false;
    showSuspendModal.value = false;
    selectedUser.value = null;
};

const confirmDelete = () => {
    if (selectedUser.value) {
        router.delete(route('superadmin.users.destroy', selectedUser.value.id), {
            onFinish: () => closeModal(),
        });
    }
};

const confirmSuspend = () => {
    if (selectedUser.value) {
        const action = selectedUser.value.is_suspended ? 'unsuspend' : 'suspend';
        router.post(route(`superadmin.users.${action}`, selectedUser.value.id), {}, {
            onFinish: () => closeModal(),
        });
    }
};
</script>

<template>
    <Head title="Manage Tenants" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                    Manage Tenants
                </h2>
                <div class="text-sm text-gray-500">
                    {{ users.total }} total tenants
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
                        placeholder="Search by name, email, phone..."
                        class="block w-full rounded-lg border-0 py-2.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    />
                </div>

                <!-- Filters -->
                <div class="flex gap-3">
                    <select
                        v-model="status"
                        class="block rounded-lg border-0 py-2.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>

                    <select
                        v-model="country"
                        class="block rounded-lg border-0 py-2.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    >
                        <option value="">All Countries</option>
                        <option v-for="c in countries" :key="c.country_code" :value="c.country_code">
                            {{ c.country }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tenant</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Contact</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Location</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Joined</th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold dark:bg-indigo-900 dark:text-indigo-300">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                                            <div class="text-sm text-gray-500">{{ user.tenant_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ user.email }}</div>
                                    <div class="text-sm text-gray-500">{{ user.phone }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="user.country_code" class="mr-2 text-lg">{{ user.country_code === 'KE' ? '🇰🇪' : (user.country_code === 'GH' ? '🇬🇭' : (user.country_code === 'NG' ? '🇳🇬' : '🌍')) }}</span>
                                        {{ user.country || 'Unknown' }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        user.is_suspended 
                                            ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' 
                                            : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                    ]">
                                        {{ user.is_suspended ? 'Suspended' : 'Active' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ new Date(user.created_at).toLocaleDateString() }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <button @click="openActions(user)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700" title="Manage Tenant">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <AlertCircle class="h-12 w-12 mb-3 opacity-20" />
                                        <p class="text-lg font-medium">No tenants found</p>
                                        <p class="text-sm">Try adjusting your search or filters</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="users.links.length > 3" class="border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <Link :href="users.prev_page_url" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</Link>
                        <Link :href="users.next_page_url" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</Link>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing <span class="font-medium">{{ users.from }}</span> to <span class="font-medium">{{ users.to }}</span> of <span class="font-medium">{{ users.total }}</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <Link 
                                    v-for="(link, i) in users.links" 
                                    :key="i"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 focus:z-20 focus:outline-offset-0',
                                        link.active 
                                            ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' 
                                            : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700',
                                        i === 0 ? 'rounded-l-md' : '',
                                        i === users.links.length - 1 ? 'rounded-r-md' : '',
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
            <div class="p-4 dark:bg-slate-800 dark:text-white" v-if="selectedUser">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4">
                        {{ selectedUser.name }}
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <component :is="AlertCircle" class="w-5 h-5 rotate-45" /> <!-- Using AlertCircle as X icon replacement if X is not imported, or import X -->
                    </button>
                </div>

                <div class="space-y-1">
                    <Link :href="route('superadmin.users.show', selectedUser.id)" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40">
                            <Eye class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">View Details</span>
                    </Link>

                    <button @click="openSuspendModal(selectedUser)" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md" :class="selectedUser.is_suspended ? 'bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 group-hover:bg-green-100' : 'bg-yellow-50 text-yellow-600 dark:bg-yellow-900/20 dark:text-yellow-400 group-hover:bg-yellow-100'">
                            <component :is="selectedUser.is_suspended ? CheckCircle : Ban" class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ selectedUser.is_suspended ? 'Activate Tenant' : 'Suspend Tenant' }}</span>
                    </button>

                    <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>

                    <button @click="openDeleteModal(selectedUser)" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 group-hover:bg-red-100 dark:group-hover:bg-red-900/40">
                            <Trash2 class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">Delete Tenant</span>
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
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Delete Tenant?</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete <b>{{ selectedUser?.name }}</b>? This action cannot be undone and will remove all associated data.
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
                <div class="flex items-center justify-center w-12 h-12 mx-auto" :class="selectedUser?.is_suspended ? 'bg-green-100 dark:bg-green-900/30' : 'bg-yellow-100 dark:bg-yellow-900/30'">
                    <component :is="selectedUser?.is_suspended ? CheckCircle : Ban" class="w-6 h-6" :class="selectedUser?.is_suspended ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'" />
                </div>
                <div class="mt-4 text-center">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                        {{ selectedUser?.is_suspended ? 'Activate Tenant?' : 'Suspend Tenant?' }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ selectedUser?.is_suspended 
                            ? `Are you sure you want to reactivate ${selectedUser?.name}? They will regain access to the system.` 
                            : `Are you sure you want to suspend ${selectedUser?.name}? They will lose access to the system immediately.` 
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
                        :class="selectedUser?.is_suspended ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500'"
                        @click="confirmSuspend"
                    >
                        {{ selectedUser?.is_suspended ? 'Activate' : 'Suspend' }}
                    </button>
                </div>
            </div>
        </Modal>
    </SuperAdminLayout>
</template>
