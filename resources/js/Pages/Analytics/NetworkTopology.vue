<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Network, Server, Activity, Cpu, HardDrive, Clock, MapPin, RefreshCw } from 'lucide-vue-next';

const props = defineProps({
    topology: Object,
});

const topologyData = ref(props.topology);
const selectedDevice = ref(null);
const autoRefresh = ref(true);
const refreshInterval = ref(null);

// Status colors
const statusColors = {
    online: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-800 dark:text-green-400', border: 'border-green-500' },
    warning: { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-800 dark:text-yellow-400', border: 'border-yellow-500' },
    offline: { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-800 dark:text-red-400', border: 'border-red-500' },
};

// Refresh topology data
function refreshTopology() {
    router.reload({ only: ['topology'], preserveScroll: true });
}

// Auto-refresh every 30 seconds
onMounted(() => {
    if (autoRefresh.value) {
        refreshInterval.value = setInterval(() => {
            refreshTopology();
        }, 30000);
    }
});

onUnmounted(() => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
    }
});

// Toggle auto-refresh
function toggleAutoRefresh() {
    autoRefresh.value = !autoRefresh.value;
    
    if (autoRefresh.value) {
        refreshInterval.value = setInterval(() => {
            refreshTopology();
        }, 30000);
    } else {
        if (refreshInterval.value) {
            clearInterval(refreshInterval.value);
        }
    }
}

// Select device
function selectDevice(node) {
    selectedDevice.value = node;
}

// Get status badge classes
function getStatusClasses(status) {
    return statusColors[status] || statusColors.offline;
}

// Navigate to router management
function manageRouter(routerId) {
    router.visit(route('mikrotiks.index'));
}
</script>

<template>
    <Head title="Network Topology" />
    
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <Network class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                            Network Topology
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Real-time visualization of your network infrastructure
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="toggleAutoRefresh"
                        :class="[
                            'flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-colors',
                            autoRefresh 
                                ? 'bg-blue-600 text-white' 
                                : 'bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300'
                        ]"
                    >
                        <RefreshCw :class="['w-4 h-4', autoRefresh ? 'animate-spin' : '']" />
                        <span>Auto-refresh</span>
                    </button>
                    
                    <button
                        @click="refreshTopology"
                        class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                    >
                        <RefreshCw class="w-4 h-4" />
                        <span>Refresh Now</span>
                    </button>
                </div>
            </div>
        </template>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Routers</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ topology.summary.total }}
                        </p>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <Server class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Online</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">
                            {{ topology.summary.online }}
                        </p>
                    </div>
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <Activity class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Warning</p>
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">
                            {{ topology.summary.warning }}
                        </p>
                    </div>
                    <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <Activity class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Offline</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">
                            {{ topology.summary.offline }}
                        </p>
                    </div>
                    <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <Server class="w-6 h-6 text-red-600 dark:text-red-400" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Network Visualization -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Network Map
                </h3>
                
                <!-- Simple Grid Layout for Routers -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div
                        v-for="node in topology.nodes"
                        :key="node.id"
                        @click="selectDevice(node)"
                        :class="[
                            'p-4 rounded-lg border-2 cursor-pointer transition-all hover:shadow-lg',
                            getStatusClasses(node.status).border,
                            getStatusClasses(node.status).bg,
                            selectedDevice?.id === node.id ? 'ring-2 ring-blue-500' : ''
                        ]"
                    >
                        <div class="flex flex-col items-center text-center">
                            <Server :class="['w-8 h-8 mb-2', getStatusClasses(node.status).text]" />
                            <p class="font-medium text-sm text-gray-900 dark:text-white truncate w-full">
                                {{ node.name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ node.ip }}
                            </p>
                            <div :class="['mt-2 px-2 py-0.5 rounded-full text-xs font-medium', getStatusClasses(node.status).bg, getStatusClasses(node.status).text]">
                                {{ node.status }}
                            </div>
                            <p v-if="node.active_users > 0" class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                {{ node.active_users }} users
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="topology.nodes.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <Server class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p>No routers found</p>
                    <Link :href="route('mikrotiks.index')" class="text-blue-600 hover:underline mt-2 inline-block">
                        Add your first router
                    </Link>
                </div>
            </div>

            <!-- Device Details Panel -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Device Details
                </h3>

                <div v-if="selectedDevice" class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-slate-700">
                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ selectedDevice.name }}</h4>
                        <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusClasses(selectedDevice.status).bg, getStatusClasses(selectedDevice.status).text]">
                            {{ selectedDevice.status }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-start gap-2">
                            <Server class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">IP Address</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedDevice.ip }}</p>
                            </div>
                        </div>

                        <div v-if="selectedDevice.location" class="flex items-start gap-2">
                            <MapPin class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Location</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedDevice.location }}</p>
                            </div>
                        </div>

                        <div v-if="selectedDevice.cpu !== null" class="flex items-start gap-2">
                            <Cpu class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">CPU Load</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <div class="flex-1 bg-gray-200 dark:bg-slate-700 rounded-full h-2">
                                        <div 
                                            :class="[
                                                'h-2 rounded-full transition-all',
                                                selectedDevice.cpu > 80 ? 'bg-red-500' : selectedDevice.cpu > 60 ? 'bg-yellow-500' : 'bg-green-500'
                                            ]"
                                            :style="{ width: selectedDevice.cpu + '%' }"
                                        ></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedDevice.cpu }}%</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedDevice.memory !== null" class="flex items-start gap-2">
                            <HardDrive class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Memory Usage</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <div class="flex-1 bg-gray-200 dark:bg-slate-700 rounded-full h-2">
                                        <div 
                                            :class="[
                                                'h-2 rounded-full transition-all',
                                                selectedDevice.memory > 80 ? 'bg-red-500' : selectedDevice.memory > 60 ? 'bg-yellow-500' : 'bg-green-500'
                                            ]"
                                            :style="{ width: selectedDevice.memory + '%' }"
                                        ></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedDevice.memory }}%</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedDevice.uptime" class="flex items-start gap-2">
                            <Clock class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Uptime</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedDevice.uptime }}</p>
                            </div>
                        </div>

                        <div v-if="selectedDevice.last_seen" class="flex items-start gap-2">
                            <Activity class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Last Seen</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedDevice.last_seen }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2">
                            <Activity class="w-4 h-4 text-gray-400 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Active Users</p>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedDevice.active_users }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200 dark:border-slate-700">
                        <Link
                            :href="route('mikrotiks.index')"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <Server class="w-4 h-4" />
                            <span>Manage Router</span>
                        </Link>
                    </div>
                </div>

                <div v-else class="text-center py-12 text-gray-500 dark:text-gray-400">
                    <Server class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p class="text-sm">Select a device to view details</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
