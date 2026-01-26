<script setup>
import { ref, watch } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Search, RefreshCw, Eye, Activity, Router } from 'lucide-vue-next'

const props = defineProps({
    devices: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '');
let searchTimeout;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('devices.index'),
            { search: value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
});

const syncDevices = () => {
    router.post(route('devices.sync'), {}, {
        preserveScroll: true,
        onStart: () => {
            // Optional: show loading state
        }
    });
}

const getStatusColor = (online) => {
    return online ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="TR-069 Devices" />

        <div class="max-w-7xl mx-auto p-6 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-2">
                    <Router class="h-8 w-8 text-blue-600" />
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">TR-069 Devices</h2>
                </div>
                
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <div class="relative w-full sm:w-72">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Search class="h-4 w-4 text-gray-400" />
                        </div>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search serial, model..."
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out"
                        />
                    </div>
                
                    <PrimaryButton @click="syncDevices" class="flex items-center gap-2 whitespace-nowrap">
                        <RefreshCw class="h-4 w-4" /> Sync GenieACS
                    </PrimaryButton>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg shadow bg-white dark:bg-gray-800">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Device Info</th>
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subscriber</th>
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Network</th>
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Contact</th>
                            <th class="px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="device in devices.data" :key="device.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium', getStatusColor(device.online)]">
                                    {{ device.online ? 'Online' : 'Offline' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ device.manufacturer }} {{ device.model }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">SN: {{ device.serial_number }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div v-if="device.subscriber" class="text-sm text-gray-900 dark:text-white">
                                    {{ device.subscriber.name }}
                                </div>
                                <div v-else class="text-sm text-gray-400 italic">Unassigned</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-xs text-gray-900 dark:text-white font-mono">WAN: {{ device.wan_ip || 'N/A' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">LAN: {{ device.lan_ip || 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ device.last_contact_at ? new Date(device.last_contact_at).toLocaleString() : 'Never' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end gap-2">
                                    <Link 
                                        :href="route('devices.show', device.id)"
                                        class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                                        title="View Details"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                    <button 
                                        @click="syncDevices"
                                        class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/30 rounded-lg transition-colors"
                                        title="Diagnostic"
                                    >
                                        <Activity class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="devices.data.length === 0">
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                No devices found. Ensure routers are configured to point to your GenieACS server.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="devices.total > 0" class="flex justify-center mt-6">
                <Pagination :links="devices.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
