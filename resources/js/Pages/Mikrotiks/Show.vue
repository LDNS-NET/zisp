<script setup>
import { ref } from "vue";
import { Head, useForm, router as inertiaRouter } from "@inertiajs/vue3";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Card from "@/Components/Card.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import Modal from "@/Components/Modal.vue";
import TextInput from "@/Components/TextInput.vue";
import InputLabel from "@/Components/InputLabel.vue";
import InputError from "@/Components/InputError.vue";

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
    inertiaRouter.get(route('mikrotiks.show', props.mikrotik.id), { force: 1 }, {
        only: ['realtime'],
        preserveScroll: true,
        onFinish: () => (isRefreshing.value = false),
    });
};

// Actions
const rebootRouter = () => {
    if (confirm("Are you sure you want to reboot this router?")) {
        inertiaRouter.post(route("mikrotiks.reboot", props.mikrotik.id), {}, {
            onSuccess: () => alert("Reboot command sent successfully."),
            onError: (errors) => alert("Failed to send reboot command."),
        });
    }
};

// Identity Modal
const showIdentityModal = ref(false);
const identityForm = useForm({
    identity: props.mikrotik.name,
});

const openIdentityModal = () => {
    identityForm.identity = props.mikrotik.name;
    showIdentityModal.value = true;
};

const closeIdentityModal = () => {
    showIdentityModal.value = false;
    identityForm.reset();
};

const updateIdentity = () => {
    identityForm.post(route("mikrotiks.updateIdentity", props.mikrotik.id), {
        onSuccess: () => {
            closeIdentityModal();
            // alert("Identity updated successfully.");
        },
        onError: () => {
            // alert("Failed to update identity.");
        },
    });
    });
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        alert("Copied to clipboard!");
    });
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
                    <!-- Actions Dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <SecondaryButton>
                                Actions
                                <MoreVertical class="ml-2 -mr-0.5 h-4 w-4" />
                            </SecondaryButton>
                        </template>

                        <template #content>
                            <DropdownLink as="button" @click="refreshData">
                                <div class="flex items-center">
                                    <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': isRefreshing }" />
                                    Refresh Data
                                </div>
                            </DropdownLink>

                            <DropdownLink as="button" @click="openIdentityModal">
                                <div class="flex items-center">
                                    <Edit class="mr-2 h-4 w-4" />
                                    Change Identity
                                </div>
                            </DropdownLink>

                            <div class="border-t border-gray-100 dark:border-gray-700"></div>

                            <DropdownLink as="button" @click="rebootRouter" class="text-red-600 dark:text-red-400">
                                <div class="flex items-center">
                                    <Power class="mr-2 h-4 w-4" />
                                    Reboot Router
                                </div>
                            </DropdownLink>
                        </template>
                    </Dropdown>
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

                            <!-- CONNECTION DETAILS -->
                            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Connection Details
                                </h3>

                                <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Management IP
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ mikrotik.wireguard_address || "N/A" }}
                                        </dd>
                                        </dd>
                                    </div>

                                    <div class="flex justify-between py-3" v-if="mikrotik.winbox_port && mikrotik.public_ip">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Remote Winbox
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100 flex items-center">
                                            <span class="mr-2">{{ mikrotik.public_ip }}:{{ mikrotik.winbox_port }}</span>
                                            <button 
                                                @click="copyToClipboard(`${mikrotik.public_ip}:${mikrotik.winbox_port}`)"
                                                class="text-gray-400 hover:text-blue-500 transition-colors"
                                                title="Copy Address"
                                            >
                                                <Copy class="h-4 w-4" />
                                            </button>
                                        </dd>
                                    </div>

                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            API Port
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ mikrotik.api_port || "8728" }}
                                        </dd>
                                    </div>

                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Last Seen
                                        </dt>
                                        <dd class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ mikrotik.last_seen_at || "Never" }}
                                        </dd>
                                    </div>

                                    <div class="flex justify-between py-3">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            WireGuard Status
                                        </dt>
                                        <dd class="text-sm">
                                            <span
                                                :class="[
                                                    mikrotik.wireguard_public_key
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                                    'inline-flex rounded-full px-2 text-xs font-semibold'
                                                ]"
                                            >
                                                {{ mikrotik.wireguard_public_key ? "Registered" : "Pending" }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
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
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Identity Modal -->
    <Modal :show="showIdentityModal" @close="closeIdentityModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Change Router Identity
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Update the router's identity name. This will change the name on the device and in the system.
            </p>

            <div class="mt-6">
                <InputLabel for="identity" value="New Identity Name" />

                <TextInput
                    id="identity"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="identityForm.identity"
                    placeholder="Router Name"
                    @keyup.enter="updateIdentity"
                />

                <InputError :message="identityForm.errors.identity" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeIdentityModal"> Cancel </SecondaryButton>

                <PrimaryButton
                    class="ml-3"
                    :class="{ 'opacity-25': identityForm.processing }"
                    :disabled="identityForm.processing"
                    @click="updateIdentity"
                >
                    Save Changes
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>


