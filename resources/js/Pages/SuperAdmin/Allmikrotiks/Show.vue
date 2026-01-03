<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import { 
    Router, 
    Trash2, 
    ArrowLeft, 
    Activity, 
    MapPin, 
    Globe, 
    Server, 
    Shield,
    Clock,
    User
} from 'lucide-vue-next';

const props = defineProps({
    mikrotik: Object,
});

// Modal State
const showDeleteModal = ref(false);

const openDeleteModal = () => showDeleteModal.value = true;
const closeModal = () => showDeleteModal.value = false;

const confirmDelete = () => {
    router.delete(route('superadmin.allmikrotiks.destroy', props.mikrotik.id), {
        onFinish: () => closeModal(),
    });
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString();
};
</script>

<template>
    <Head :title="`Router Details - ${props.mikrotik.name}`" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('superadmin.allmikrotiks.index')" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <ArrowLeft class="w-5 h-5 text-gray-500" />
                    </Link>
                    <div>
                        <h2 class="text-2xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ props.mikrotik.name || props.mikrotik.identity }}
                        </h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span 
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                :class="props.mikrotik.status === 'online' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'"
                            >
                                <span class="w-2 h-2 mr-1.5 rounded-full" :class="props.mikrotik.status === 'online' ? 'bg-green-500' : 'bg-red-500'"></span>
                                {{ props.mikrotik.status === 'online' ? 'Online' : 'Offline' }}
                            </span>
                            <span class="text-sm text-gray-500">{{ props.mikrotik.ip_address }}</span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <button
                        @click="openDeleteModal"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800"
                    >
                        <Trash2 class="w-4 h-4 mr-2" />
                        Delete Router
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Connection Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <Server class="w-5 h-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Connection Details</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">IP Address</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ props.mikrotik.ip_address }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">WireGuard Address</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ props.mikrotik.wireguard_address || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">API Port</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.mikrotik.api_port }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Web Port</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.mikrotik.web_port }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Interface</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.mikrotik.interface }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Location & Info -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <MapPin class="w-5 h-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Location & System</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Location</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.mikrotik.location || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Coordinates</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                                    {{ props.mikrotik.coordinates || 'N/A' }}
                                </div>
                            </div>
                             <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Site Name</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.mikrotik.site_name || 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenant Information -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" v-if="props.mikrotik.tenant">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <User class="w-5 h-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Tenant Information</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tenant Name</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    <Link :href="route('superadmin.users.show', props.mikrotik.tenant.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                        {{ props.mikrotik.tenant.name }}
                                    </Link>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tenant Email</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.mikrotik.tenant.email }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tenant Phone</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ props.mikrotik.tenant.phone }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timestamps -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-2 mb-4">
                            <Clock class="w-5 h-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Activity</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Created At</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ formatDate(props.mikrotik.created_at) }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</label>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ formatDate(props.mikrotik.updated_at) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeModal">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full dark:bg-red-900/30">
                    <Trash2 class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div class="mt-4 text-center">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">Delete Router?</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete <b>{{ props.mikrotik.name }}</b>? This action cannot be undone.
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
