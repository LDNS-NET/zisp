<script setup>
import { ref } from "vue";
import { Head, useForm, router as inertiaRouter } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Card from "@/Components/Card.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import Modal from "@/Components/Modal.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";
import { useToast } from "vue-toastification";

import {
    Activity,
    Wifi,
    RefreshCw,
    Power,
    MoreVertical,
    Cpu,
    Edit,
    HardDrive,
    Clock,
    Users,
    Copy,
    Server,
    Globe,
    Shield
} from "lucide-vue-next";

const props = defineProps({
    mikrotik: Object,
    realtime: Object,
});

// State
const activeTab = ref("overview");
const isRefreshing = ref(false);
const showIdentityModal = ref(false);

const tabs = [
    { id: "overview", name: "Overview", icon: Activity },
    { id: "interfaces", name: "Interfaces", icon: Wifi },
];

const identityForm = useForm({
    identity: props.mikrotik.name,
});

// Helpers
const formatUptime = (seconds) => {
    if (!seconds) return "N/A";
    // Check if it's already formatted string (from backend accessor)
    if (typeof seconds === 'string') return seconds;
    
    const d = Math.floor(seconds / (3600 * 24));
    const h = Math.floor((seconds % (3600 * 24)) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${d}d ${h}h ${m}m`;
};

const formatBytes = (bytes) => {
    if (!bytes || bytes === 0) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB", "TB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        // Optional: toast notification here
        onSuccess: toast.success(`Copied to clipboard: ${text}`);
    });
};

// Actions
const refreshData = () => {
    isRefreshing.value = true;
    inertiaRouter.get(
        route("mikrotiks.show", props.mikrotik.id),
        {},
        {
            preserveScroll: true,
            only: ["realtime"],
            onFinish: () => (isRefreshing.value = false),
        }
    );
};

const rebootRouter = () => {
    if (confirm("Are you sure you want to reboot this router? This will disconnect all users.")) {
        inertiaRouter.post(
            route("mikrotiks.reboot", props.mikrotik.id),
            {},
            {
                onSuccess: () => alert("Reboot command sent."),
            }
        );
    }
};

const openIdentityModal = () => {
    identityForm.identity = props.mikrotik.name;
    showIdentityModal.value = true;
};

const updateIdentity = () => {
    identityForm.post(route("mikrotiks.updateIdentity", props.mikrotik.id), {
        onSuccess: () => {
            showIdentityModal.value = false;
            identityForm.reset();
        },
    });
};
</script>

<template>
    <Head :title="mikrotik.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ mikrotik.name }}
                    </h2>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <StatusBadge :status="realtime.is_online ? 'online' : 'offline'" />
                        <span>IP: {{ mikrotik.wireguard_address || mikrotik.ip_address }}</span>
                        <span>• RouterOS {{ mikrotik.os_version || realtime.resources?.version || '-' }}</span>
                        <span>• Connected: {{ formatUptime(mikrotik.uptime_formatted) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <SecondaryButton>
                                Actions
                                <MoreVertical class="ml-2 -mr-0.5 h-4 w-4" />
                            </SecondaryButton>
                        </template>
                        <template #content>
                            <button
                                @click="refreshData"
                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 transition duration-150 ease-in-out hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800"
                            >
                                <div class="flex items-center">
                                    <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                                    Refresh Data
                                </div>
                            </button>
                            <button
                                @click="openIdentityModal"
                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 transition duration-150 ease-in-out hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800"
                            >
                                <div class="flex items-center">
                                    <Edit class="mr-2 h-4 w-4" />
                                    Change Identity
                                </div>
                            </button>
                            <div class="border-t border-gray-100 dark:border-gray-700" />
                            <button
                                @click="rebootRouter"
                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-600 dark:text-red-400 transition duration-150 ease-in-out hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800"
                            >
                                <div class="flex items-center">
                                    <Power class="mr-2 h-4 w-4" />
                                    Reboot Router
                                </div>
                            </button>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- KPI Cards -->
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card
                        title="CPU Load"
                        :value="mikrotik.cpu_usage ? mikrotik.cpu_usage + '%' : 'N/A'"
                        :icon="Cpu"
                    />
                    <Card
                        title="Memory Usage"
                        :value="mikrotik.memory_usage ? mikrotik.memory_usage + '%' : 'N/A'"
                        :subtitle="realtime.resources?.['total-memory'] ? formatBytes(realtime.resources['total-memory']) + ' Total' : ''"
                        :icon="HardDrive"
                    />
                    <Card
                        title="Uptime (Online)"
                        :value="mikrotik.uptime_formatted || 'N/A'"
                        :icon="Clock"
                    />
                    <Card
                        title="Active Sessions"
                        :value="(realtime.hotspot_active + realtime.pppoe_active).toString()"
                        :subtitle="`Hotspot: ${realtime.hotspot_active} | PPPoE: ${realtime.pppoe_active}`"
                        :icon="Users"
                    />
                </div>

                <!-- Tabs Navigation -->
                <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                activeTab === tab.id
                                    ? 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
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

                <!-- Tab Content -->
                <div class="min-h-[400px]">
                    <!-- OVERVIEW TAB -->
                    <div v-if="activeTab === 'overview'" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <!-- System Info -->
                            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                <div class="flex items-center mb-4">
                                    <Server class="h-5 w-5 text-gray-400 mr-2" />
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">System Information</h3>
                                </div>
                                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Board Name</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ realtime.resources?.["board-name"] || "-" }}</dd>
                                    </div>
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Architecture</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ realtime.resources?.["architecture-name"] || "-" }}</dd>
                                    </div>
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">RouterOS Version</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ realtime.resources?.version || "-" }}</dd>
                                    </div>
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Build Time</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ realtime.resources?.["build-time"] || "-" }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Connection Details -->
                            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                <div class="flex items-center mb-4">
                                    <Globe class="h-5 w-5 text-gray-400 mr-2" />
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Remote Access</h3>
                                </div>
                                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">VPN IP</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ mikrotik.wireguard_address || "N/A" }}</dd>
                                    </div>
                                    
                                    <!-- Remote Winbox Address -->
                                    <div class="flex justify-between py-3" v-if="mikrotik.winbox_port && mikrotik.public_ip">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center">
                                            Remote Winbox
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Public
                                            </span>
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100 flex items-center bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded">
                                            <span class="mr-2 font-mono">zyraaf.cloud:{{ mikrotik.winbox_port }}</span>
                                            <button 
                                                @click="copyToClipboard(`zyraaf.cloud:${mikrotik.winbox_port}`)"
                                                class="text-gray-400 hover:text-blue-500 transition-colors bg:dark:bg-gray-600 p-1 rounded shadow-sm border border-gray-200 dark:border-gray-500"
                                                title="Copy to Clipboard"
                                            >
                                                <Copy class="h-3 w-3" />
                                            </button>
                                        </dd>
                                    </div>

                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">API Port</dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">{{ mikrotik.api_port || "8728" }}</dd>
                                    </div>

                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                        <dd class="text-sm">
                                            <span :class="[
                                                mikrotik.wireguard_public_key ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400',
                                                'font-medium'
                                            ]">
                                                {{ mikrotik.wireguard_public_key ? 'Secured (WireGuard)' : 'Pending Setup' }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <!-- INTERFACES TAB -->
                    <div v-if="activeTab === 'interfaces'" class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">MAC</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    <tr v-for="iface in realtime.interfaces" :key="iface['.id']">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ iface.name }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ iface.type }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            <span :class="[
                                                iface.running === 'true'
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                'inline-flex rounded-full px-2 text-xs font-semibold'
                                            ]">
                                                {{ iface.running === 'true' ? 'Running' : 'Down' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ iface['mac-address'] || '-' }}</td>
                                    </tr>
                                    <tr v-if="!realtime.interfaces?.length">
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No interface data available.
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

    <Modal :show="showIdentityModal" @close="showIdentityModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Change Router Identity</h2>
            <div class="mt-6">
                <InputLabel for="identity" value="New Identity Name" />
                <TextInput
                    id="identity"
                    v-model="identityForm.identity"
                    class="mt-1 block w-full"
                    placeholder="e.g. Main-Gateway"
                    @keyup.enter="updateIdentity"
                />
                <InputError :message="identityForm.errors.identity" class="mt-2" />
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="showIdentityModal = false">Cancel</SecondaryButton>
                <PrimaryButton @click="updateIdentity" :disabled="identityForm.processing">Save Changes</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
