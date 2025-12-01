<script setup>
import { ref } from "vue";
import { Head, useForm, router as inertiaRouter } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Card from "@/Components/Card.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import StatusBadge from "@/Components/StatusBadge.vue";

import {
    Activity,
    Wifi,
    Shield,
    FileText,
    Clock,
    Users,
    RefreshCw,
    Power,
    Terminal,
    Download,
    Cpu,
    HardDrive,
} from "lucide-vue-next";

// Props
const props = defineProps({
    mikrotik: Object,
    realtime: Object,
});

// Tabs
const activeTab = ref("overview");
const isRefreshing = ref(false);

const tabs = [
    { id: "overview", name: "Overview", icon: Activity },
    { id: "interfaces", name: "Interfaces", icon: Wifi },
    { id: "wireguard", name: "WireGuard", icon: Shield },
    { id: "logs", name: "Logs", icon: FileText },
];

// Helpers
const formatUptime = (uptime) => uptime || "N/A";

const formatBytes = (bytes) => {
    if (!bytes || bytes === 0) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB", "TB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

// Refresh
const refreshData = () => {
    isRefreshing.value = true;
    inertiaRouter.reload({
        only: ["realtime"],
        onFinish: () => (isRefreshing.value = false),
    });
};

// Simulated Actions
const rebootRouter = () => {
    if (confirm("Are you sure you want to reboot this router?")) {
        alert("Reboot command sent (simulated)");
    }
};

const syncRouter = () => {
    alert("Sync command sent (simulated)");
};
</script>

<template>
    <Head :title="mikrotik.name + ' - Details'" />

    <AuthenticatedLayout>
        <!-- HEADER -->
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ mikrotik.name }}
                    </h2>

                    <div class="mt-1 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <StatusBadge :status="realtime.is_online ? 'online' : 'offline'" />

                        <span>{{ mikrotik.ip_address }}</span>

                        <span v-if="realtime.resources?.['board-name']">
                            • {{ realtime.resources["board-name"] }}
                        </span>

                        <span v-if="realtime.resources?.version">
                            • RouterOS {{ realtime.resources.version }}
                        </span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <SecondaryButton @click="refreshData" :disabled="isRefreshing">
                        <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                        Refresh
                    </SecondaryButton>

                    <PrimaryButton
                        as="a"
                        :href="route('mikrotiks.downloadSetupScript', { mikrotik: mikrotik.id })"
                    >
                        <Download class="mr-2 h-4 w-4" />
                        Script
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <!-- MAIN CONTENT -->
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- STATS -->
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card
                        title="CPU Load"
                        :value="realtime.resources?.['cpu-load'] ? realtime.resources['cpu-load'] + '%' : 'N/A'"
                        :icon="Cpu"
                    />

                    <Card
                        title="Memory"
                        :value="realtime.resources?.['free-memory'] 
                            ? formatBytes(realtime.resources['total-memory'] - realtime.resources['free-memory']) 
                            : 'N/A'"
                        :subtitle="realtime.resources?.['total-memory'] 
                            ? 'of ' + formatBytes(realtime.resources['total-memory']) 
                            : ''"
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

                <!-- TABS -->
                <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
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
                            <component
                                :is="tab.icon"
                                class="-ml-0.5 mr-2 h-5 w-5"
                                :class="[
                                    activeTab === tab.id
                                        ? 'text-blue-500 dark:text-blue-400'
                                        : 'text-gray-400 group-hover:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-400'
                                ]"
                            />
                            {{ tab.name }}
                        </button>
                    </nav>
                </div>

                <!-- TAB CONTENT -->
                <div class="min-h-[400px]">
                    <!-- ========== OVERVIEW TAB ========== -->
                    <div v-if="activeTab === 'overview'" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <!-- SYSTEM INFO -->
                            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                                    System Information
                                </h3>

                                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Model
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ realtime.resources?.["board-name"] || "Unknown" }}
                                        </dd>
                                    </div>

                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Architecture
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ realtime.resources?.["architecture-name"] || "Unknown" }}
                                        </dd>
                                    </div>

                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Version
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ realtime.resources?.version || "Unknown" }}
                                        </dd>
                                    </div>

                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Build Time
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ realtime.resources?.["build-time"] || "Unknown" }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- ACTIONS -->
                            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Quick Actions
                                </h3>

                                <div class="grid grid-cols-2 gap-4">
                                    <SecondaryButton @click="syncRouter" class="justify-center">
                                        <RefreshCw class="mr-2 h-4 w-4" /> Sync
                                    </SecondaryButton>

                                    <SecondaryButton
                                        @click="rebootRouter"
                                        class="justify-center text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                    >
                                        <Power class="mr-2 h-4 w-4" /> Reboot
                                    </SecondaryButton>

                                    <SecondaryButton
                                        as="a"
                                        :href="route('mikrotiks.downloadSetupScript', { mikrotik: mikrotik.id })"
                                        class="justify-center"
                                    >
                                        <Download class="mr-2 h-4 w-4" /> Setup Script
                                    </SecondaryButton>

                                    <SecondaryButton disabled class="justify-center opacity-50">
                                        <Terminal class="mr-2 h-4 w-4" /> Terminal
                                    </SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========== INTERFACES TAB ========== -->
                    <div
                        v-if="activeTab === 'interfaces'"
                        class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                    >
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Name
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            MAC Address
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr v-for="iface in realtime.interfaces" :key="iface['.id']">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ iface.name }}
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ iface.type }}
                                        </td>

                                        <td class="px-6 py-4 text-sm">
                                            <span
                                                :class="[
                                                    iface.running === 'true'
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                    'inline-flex rounded-full px-2 text-xs font-semibold'
                                                ]"
                                            >
                                                {{ iface.running === "true" ? "Running" : "Down" }}
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ iface["mac-address"] || "-" }}
                                        </td>
                                    </tr>

                                    <tr v-if="!realtime.interfaces.length">
                                        <td
                                            colspan="4"
                                            class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            No interface data available
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========== WIREGUARD TAB ========== -->
                    <div
                        v-if="activeTab === 'wireguard'"
                        class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                    >
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Interface
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Public Key
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Endpoint
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Last Handshake
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Transfer (Rx/Tx)
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr v-for="peer in realtime.wireguard_peers" :key="peer['.id']">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ peer.interface }}
                                        </td>

                                        <td
                                            class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"
                                            :title="peer['public-key']"
                                        >
                                            {{ peer["public-key"]?.substring(0, 10) }}...
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ peer["endpoint-address"] }}:{{ peer["endpoint-port"] }}
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ peer["last-handshake"] || "Never" }}
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatBytes(peer.rx) }} /
                                            {{ formatBytes(peer.tx) }}
                                        </td>
                                    </tr>

                                    <tr v-if="!realtime.wireguard_peers.length">
                                        <td
                                            colspan="5"
                                            class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            No WireGuard peers found
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========== LOGS TAB ========== -->
                    <div
                        v-if="activeTab === 'logs'"
                        class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                    >
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Time
                                        </th>

                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Topics
                                        </th>

                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                            Message
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr v-for="log in realtime.router_logs" :key="log['.id']">
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ log.time }}
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <span
                                                class="inline-flex rounded-full bg-gray-100 px-2 text-xs font-semibold text-gray-800 dark:bg-gray-700 dark:text-gray-300"
                                            >
                                                {{ log.topics }}
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                            {{ log.message }}
                                        </td>
                                    </tr>

                                    <tr v-if="!realtime.router_logs.length">
                                        <td
                                            colspan="3"
                                            class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            No logs available
                                        </td>
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
