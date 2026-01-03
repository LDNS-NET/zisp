<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Search, Filter, Router, Activity, MapPin, Wifi, WifiOff, AlertCircle, MoreVertical, Eye, Trash2, AlertTriangle, X } from 'lucide-vue-next';
import debounce from 'lodash/debounce';

const props = defineProps({
    mikrotiks: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

// Modal State
const showActionsModal = ref(false);
const showDeleteModal = ref(false);
const selectedMikrotik = ref(null);

// Debounced search
const updateSearch = debounce((value) => {
    router.get(
        route('superadmin.allmikrotiks.index'),
        { search: value, status: status.value },
        { preserveState: true, replace: true }
    );
}, 300);

// Watch filters
watch(status, () => {
    router.get(
        route('superadmin.allmikrotiks.index'),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    );
});

// Action Handlers
const openActions = (mikrotik) => {
    selectedMikrotik.value = mikrotik;
    showActionsModal.value = true;
};

const openDeleteModal = (mikrotik) => {
    selectedMikrotik.value = mikrotik;
    showDeleteModal.value = true;
    showActionsModal.value = false;
};

const closeModal = () => {
    showActionsModal.value = false;
    showDeleteModal.value = false;
    selectedMikrotik.value = null;
};

const confirmDelete = () => {
    if (selectedMikrotik.value) {
        router.delete(route('superadmin.allmikrotiks.destroy', selectedMikrotik.value.id), {
            onFinish: () => closeModal(),
        });
    }
};
</script>

<template>
    <Head title="All Mikrotiks" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                    System Routers
                </h2>
                <div class="text-sm text-gray-500">
                    {{ mikrotiks.total }} total routers
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
                        placeholder="Search by name, IP, location..."
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
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Router Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">IP Address</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Location</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            <tr v-for="mikrotik in mikrotiks.data" :key="mikrotik.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 dark:bg-indigo-900 dark:text-indigo-300">
                                            <Router class="h-5 w-5" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ mikrotik.name }}</div>
                                            <div class="text-xs text-gray-500">ID: {{ mikrotik.id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white font-mono">{{ mikrotik.wireguard_address || mikrotik.ip_address }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                        <MapPin class="mr-1.5 h-4 w-4 text-gray-400" />
                                        {{ mikrotik.location || 'Unknown' }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        mikrotik.status === 'online'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' 
                                            : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
                                    ]">
                                        <component :is="mikrotik.status === 'online' ? Wifi : WifiOff" class="mr-1.5 h-3 w-3" />
                                        {{ mikrotik.status === 'online' ? 'Online' : 'Offline' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <button @click="openActions(mikrotik)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700" title="Manage Router">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="mikrotiks.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <AlertCircle class="h-12 w-12 mb-3 opacity-20" />
                                        <p class="text-lg font-medium">No routers found</p>
                                        <p class="text-sm">Try adjusting your search or filters</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="mikrotiks.links.length > 3" class="border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <Link :href="mikrotiks.prev_page_url" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</Link>
                        <Link :href="mikrotiks.next_page_url" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</Link>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing <span class="font-medium">{{ mikrotiks.from }}</span> to <span class="font-medium">{{ mikrotiks.to }}</span> of <span class="font-medium">{{ mikrotiks.total }}</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <Link 
                                    v-for="(link, i) in mikrotiks.links" 
                                    :key="i"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 focus:z-20 focus:outline-offset-0',
                                        link.active 
                                            ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' 
                                            : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700',
                                        i === 0 ? 'rounded-l-md' : '',
                                        i === mikrotiks.links.length - 1 ? 'rounded-r-md' : '',
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
            <div class="p-4 dark:bg-slate-800 dark:text-white" v-if="selectedMikrotik">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4">
                        {{ selectedMikrotik.name }}
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-1">
                    <Link :href="route('superadmin.allmikrotiks.show', selectedMikrotik.id)" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40">
                            <Eye class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">View Details</span>
                    </Link>

                    <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>

                    <button @click="openDeleteModal(selectedMikrotik)" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 group-hover:bg-red-100 dark:group-hover:bg-red-900/40">
                            <Trash2 class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">Delete Router</span>
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
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Delete Router?</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete <b>{{ selectedMikrotik?.name }}</b>? This action cannot be undone.
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
                        Delete Router
                    </button>
                </div>
            </div>
        </Modal>
    </SuperAdminLayout>
</template>