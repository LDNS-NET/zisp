<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/Card.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { 
    Activity, 
    Server, 
    Clock, 
    Wifi, 
    Users, 
    Shield, 
    FileText, 
    RefreshCw, 
    Power, 
    Terminal,
    Download,
    Cpu,
    HardDrive
} from 'lucide-vue-next';

const props = defineProps({
    router: Object,
    realtime: Object,
});

const activeTab = ref('overview');
const isRefreshing = ref(false);

const tabs = [
    { id: 'overview', name: 'Overview', icon: Activity },
    { id: 'interfaces', name: 'Interfaces', icon: Wifi },
    { id: 'wireguard', name: 'WireGuard', icon: Shield },
    { id: 'logs', name: 'Logs', icon: FileText },
];

const formatUptime = (uptime) => {
    if (!uptime) return 'N/A';
    // RouterOS uptime format parsing could be complex, usually returns string like "1w2d3h"
    return uptime;
};

const formatBytes = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const refreshData = () => {
    isRefreshing.value = true;
    router.reload({
        only: ['realtime'],
        onFinish: () => isRefreshing.value = false,
    });
};

const rebootRouter = () => {
    if (confirm('Are you sure you want to reboot this router?')) {
        // Implement reboot logic
        alert('Reboot command sent (simulated)');
    }
};

const syncRouter = () => {
    // Implement sync logic
    alert('Sync command sent (simulated)');
};

</script>

<template>
    <Head :title="router.name + ' - Details'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ router.name }}
                    </h2>
                    <div class="mt-1 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <StatusBadge :status="realtime.is_online ? 'online' : 'offline'" />
                        <span>{{ router.ip_address }}</span>
                        <span v-if="realtime.resources?.['board-name']">• {{ realtime.resources['board-name'] }}</span>
                        <span v-if="realtime.resources?.version">• RouterOS {{ realtime.resources.version }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <SecondaryButton @click="refreshData" :disabled="isRefreshing">
                        <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                        Refresh
                    </SecondaryButton>
                    <PrimaryButton as="a" :href="route('mikrotiks.downloadSetupScript', { mikrotik: router.id })">
                        <Download class="mr-2 h-4 w-4" />
                        Script
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                
                <!-- Quick Stats -->
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card 
                        title="CPU Load" 
                        :value="realtime.resources?.['cpu-load'] ? realtime.resources['cpu-load'] + '%' : 'N/A'" 
                        :icon="Cpu"
                    />
                    <Card 
                        title="Memory" 
                        :value="realtime.resources?.['free-memory'] ? formatBytes(realtime.resources['total-memory'] - realtime.resources['free-memory']) : 'N/A'" 
                        :subtitle="realtime.resources?.['total-memory'] ? 'of ' + formatBytes(realtime.resources['total-memory']) : ''"
                        :icon="HardDrive"
                    />
                    <Card 
                        title="Uptime" 
                        :value="formatUptime(realtime.resources?.uptime)" 
                        :icon="Clock"
                    />
                    <Card 
                        title="Active Users" 
                        :value="(realtime.hotspot_active + realtime.pppoe_active).toString()" 
                        :subtitle="`Hotspot: ${realtime.hotspot_active} | PPPoE: ${realtime.pppoe_active}`"
                        :icon="Users"
                    />
                </div>

                <!-- Tabs -->
                <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                activeTab === tab.id
                                    ? 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300',
                                'group inline-flex items-center border-b-2 px-1 py-4 text-sm font-medium'
                            ]"
                        >
                            <component :is="tab.icon" class="-ml-0.5 mr-2 h-5 w-5" :class="[
                                activeTab === tab.id ? 'text-blue-500 dark:text-blue-400' : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-400'
                            ]" />
                            {{ tab.name }}
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="min-h-[400px]">
                    
                    <!-- Overview Tab -->
                    <div v-if="activeTab === 'overview'" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <!-- System Info Card -->
                            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">System Information</h3>
                                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Model</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ realtime.resources?.['board-name'] || 'Unknown' }}</dd>
                                    </div>
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Architecture</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ realtime.resources?.['architecture-name'] || 'Unknown' }}</dd>
                                    </div>
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Version</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ realtime.resources?.version || 'Unknown' }}</dd>
                                    </div>
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Build Time</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ realtime.resources?.['build-time'] || 'Unknown' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Actions Card -->
                            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Quick Actions</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <SecondaryButton @click="syncRouter" class="justify-center">
                                        <RefreshCw class="mr-2 h-4 w-4" /> Sync
                                    </SecondaryButton>
                                    <SecondaryButton @click="rebootRouter" class="justify-center text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                        <Power class="mr-2 h-4 w-4" /> Reboot
                                    </SecondaryButton>
                                    <SecondaryButton as="a" :href="route('mikrotiks.downloadSetupScript', { mikrotik: router.id })" class="justify-center">
                                        <Download class="mr-2 h-4 w-4" /> Setup Script
                                    </SecondaryButton>
                                    <SecondaryButton disabled class="justify-center opacity-50 cursor-not-allowed">
                                        <Terminal class="mr-2 h-4 w-4" /> Terminal
                                    </SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Interfaces Tab -->
                    <div v-if="activeTab === 'interfaces'" class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">MAC Address</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr v-for="iface in realtime.interfaces" :key="iface['.id']">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ iface.name }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ iface.type }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                                            <span :class="[
                                                iface.running === 'true' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                'inline-flex rounded-full px-2 text-xs font-semibold leading-5'
                                            ]">
                                                {{ iface.running === 'true' ? 'Running' : 'Down' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ iface['mac-address'] || '-' }}</td>
                                    </tr>
                                    <tr v-if="!realtime.interfaces.length">
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No interface data available</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- WireGuard Tab -->
                    <div v-if="activeTab === 'wireguard'" class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Interface</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Public Key</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Endpoint</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Handshake</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Transfer (Rx/Tx)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr v-for="peer in realtime.wireguard_peers" :key="peer['.id']">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ peer.interface }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400" :title="peer['public-key']">
                                            {{ peer['public-key']?.substring(0, 10) }}...
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ peer['endpoint-address'] }}:{{ peer['endpoint-port'] }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ peer['last-handshake'] || 'Never' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatBytes(peer.rx) }} / {{ formatBytes(peer.tx) }}
                                        </td>
                                    </tr>
                                    <tr v-if="!realtime.wireguard_peers.length">
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No WireGuard peers found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Logs Tab -->
                    <div v-if="activeTab === 'logs'" class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Topics</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Message</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr v-for="log in realtime.router_logs" :key="log['.id']">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ log.time }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 text-xs font-semibold leading-5 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                {{ log.topics }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">{{ log.message }}</td>
                                    </tr>
                                    <tr v-if="!realtime.router_logs.length">
                                        <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No logs available</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
