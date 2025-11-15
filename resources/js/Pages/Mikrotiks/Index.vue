<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';
import { route } from 'ziggy-js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TextArea from '@/Components/TextArea.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import {
    Plus,
    Edit,
    Eye,
    Trash2,
    Wifi,
    Download,
    Activity,
    MoreHorizontal,
    ExternalLink,
    TestTube,
    RotateCcw,
} from 'lucide-vue-next';

const props = defineProps({
    routers: Array,
    openvpnProfiles: {
        type: Array,
        default: () => [],
    },
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDetails = ref(false);
const showRemoteModal = ref(false);
const selectedRouter = ref(null);
const remoteLinks = ref({});
const pinging = ref({});
const testing = ref({});
const formError = ref('');
const actionsOpen = ref({});
const routersList = ref(props.routers || []);
let statusPollInterval = null;

function toggleActions(id) {
    actionsOpen.value[id] = !actionsOpen.value[id];
}

// Watch for props changes and update local list
watch(() => props.routers, (newRouters) => {
    routersList.value = newRouters || [];
}, { immediate: true, deep: true });

onMounted(() => {
    window.addEventListener('click', handleClickOutside);
    // Start polling for router status updates every 30 seconds
    startStatusPolling();
});

onUnmounted(() => {
    window.removeEventListener('click', handleClickOutside);
    stopStatusPolling();
});
function handleClickOutside(e) {
    if (!e.target.closest('.router-actions-toggle')) {
        closeAllActions();
    }
}

function closeAllActions() {
    actionsOpen.value = {};
}

// Add event listener to close actions on outside click
if (typeof window !== 'undefined') {
    window.addEventListener('click', (e) => {
        if (!e.target.closest('.router-actions-toggle')) {
            closeAllActions();
        }
    });
}

const form = useForm({
    name: '',
    router_username: '',
    router_password: '',
    notes: '',
    ip_address: '',
    api_port: '',
    ssh_port: '',
    connection_type: 'api',
    openvpn_profile_id: null,
});

function closeModal() {
    showAddModal.value = false;
    showEditModal.value = false;
    form.reset();
    formError.value = '';
}

async function submitForm() {
    await form.post(route('mikrotiks.store'), {
        onSuccess: () => {
            // Do not close the modal here; let Inertia handle the redirect to SetupScript.vue
            // closeModal() removed
        },
        onError: (errors) => {
            const errorMessage = 'Error adding router: ' + Object.values(errors).flat().join(', ');
            formError.value = errorMessage;
            window.toast?.error(errorMessage) || console.error(errorMessage);
        },
    });
}

function editForm() {
    if (selectedRouter.value) {
        form.put(route('mikrotiks.update', selectedRouter.value.id), {
            onSuccess: () => {
                closeModal();
                Inertia.reload({ 
                    only: ['routers'], 
                    preserveScroll: true,
                    onSuccess: () => {
                        routersList.value = props.routers || [];
                    }
                });
            },
            onError: (errors) => {
                formError.value =
                    'Error updating router: ' +
                    Object.values(errors).flat().join(', ');
            },
        });
    }
}

function editRouter(router) {
    selectedRouter.value = router;
    form.name = router.name;
    form.ip_address = router.ip_address || '';
    form.api_port = router.api_port || '';
    form.ssh_port = router.ssh_port || '';
    form.router_username = router.router_username;
    form.router_password = '';
    form.connection_type = router.connection_type || 'api';
    form.openvpn_profile_id = router.openvpn_profile_id || null;
    form.notes = router.notes;
    showEditModal.value = true;
    formError.value = '';
}

function viewRouter(router) {
    selectedRouter.value = router;
    showDetails.value = true;
}

function deleteRouter(mikrotik) {
    if (confirm('Delete this router?')) {
        Inertia.delete(route('mikrotiks.destroy', mikrotik.id), {
            onSuccess: () => {
                // Remove from local list
                routersList.value = routersList.value.filter(r => r.id !== mikrotik.id);
            },
        });
    }
}

async function pingRouter(router) {
    pinging.value[router.id] = true;
    formError.value = '';

    try {
        const response = await fetch(route('mikrotiks.ping', router.id));
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to reach router.');
        }

        router.status = data.status;
        router.last_seen_at = data.last_seen_at;
        
        // Update the routers list to reflect the change
        const index = routersList.value.findIndex(r => r.id === router.id);
        if (index !== -1) {
            routersList.value[index] = { ...router };
        }

        // Use a nicer non-blocking feedback
        window.toast?.success(data.message) || console.log(data.message);
    } catch (err) {
        formError.value = `Error pinging router: ${err.message}`;
        window.toast?.error(formError.value) || console.error(formError.value);
    } finally {
        pinging.value[router.id] = false;
    }
}


async function testRouterConnection(router) {
    testing.value[router.id] = true;
    formError.value = '';

    try {
        const response = await fetch(route('mikrotiks.testConnection', router.id));
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to test connection.');
        }

        // Update router status in the UI
        router.status = data.status;
        router.last_seen_at = data.last_seen_at;
        
        // Update the routers list to reflect the change
        const index = routersList.value.findIndex(r => r.id === router.id);
        if (index !== -1) {
            routersList.value[index] = { ...router };
        }

        // Use toast notification if available, otherwise console
        window.toast?.success(data.message) || console.log(data.message);
    } catch (err) {
        formError.value = `Error testing connection: ${err.message}`;
        window.toast?.error(formError.value) || console.error(formError.value);
    } finally {
        testing.value[router.id] = false;
    }
}

function showRemote(router) {
    formError.value = '';
    fetch(route('mikrotiks.remoteManagement', router.id))
        .then(async (res) => {
            if (!res.ok) {
                const data = await res.json();
                throw new Error(data.message || 'Unknown error');
            }
            return res.json();
        })
        .then((data) => {
            remoteLinks.value = data;
            showRemoteModal.value = true;
        })
        .catch((err) => {
            formError.value =
                'Error loading remote management links: ' + err.message;
        });
}

function formatUptime(uptime) {
    if (!uptime) return '-';
    const days = Math.floor(uptime / 86400);
    const hours = Math.floor((uptime % 86400) / 3600);
    const minutes = Math.floor((uptime % 3600) / 60);
    return `${days}d ${hours}h ${minutes}m`;
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '-';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
    pow = Math.min(pow, units.length - 1);
    const val = bytes / Math.pow(1024, pow);
    return `${val.toFixed(2)} ${units[pow]}`;
}

function downloadAdvancedConfig(router) {
    formError.value = '';
    try {
        // Create a link and trigger download
        const url = route('mikrotiks.downloadAdvancedConfig', router.id);
        const link = document.createElement('a');
        link.href = url;
        link.download = `advanced_config_router_${router.id}.rsc`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        window.toast?.success('Advanced configuration script download started') || 
            console.log('Advanced configuration script download started');
    } catch (err) {
        formError.value = `Error downloading advanced config: ${err.message}`;
        window.toast?.error(formError.value) || console.error(formError.value);
    }
}

// Status polling functions
function startStatusPolling() {
    // Poll every 30 seconds to get updated router status
    // This matches the backend's 3-minute check cycle (we poll more frequently for better UX)
    statusPollInterval = setInterval(() => {
        refreshRouterStatus();
    }, 30000); // 30 seconds
}

function stopStatusPolling() {
    if (statusPollInterval) {
        clearInterval(statusPollInterval);
        statusPollInterval = null;
    }
}

async function refreshRouterStatus() {
    try {
        // Reload only the routers data to get updated status
        await Inertia.reload({
            only: ['routers'],
            preserveScroll: true,
            preserveState: true,
        });
        // Update the local routers list
        routersList.value = props.routers || [];
    } catch (err) {
        // Silently fail - don't interrupt user experience
        console.debug('Status refresh failed:', err);
    }
}
</script>

<template>
    <Head title="Mikrotik Routers" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="flex items-center gap-2 text-2xl font-semibold text-gray-800"
                >
                    <Wifi class="h-6 w-6 text-blue-600" />
                    Mikrotik Routers
                </h2>
                <PrimaryButton
                    @click="showAddModal = true"
                    class="flex items-center gap-2"
                >
                    <Plus class="h-4 w-4" />
                    Add Router
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden border border-dashed border-blue-400 bg-white shadow-sm sm:rounded-lg dark:bg-black"
                >
                    <div
                        class="border-b border-gray-200 bg-white p-6 dark:bg-black"
                    >
                        <!-- Router Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 dark:bg-black">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                        >
                                            Name
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                        >
                                            IP Address
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                        >
                                            Status
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                        >
                                            Model
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                        >
                                            OS Version
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider"
                                        >
                                            Last Seen
                                        </th>
                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-gray-200 bg-white dark:bg-black dark:text-white"
                                >
                                    <tr
                                        v-for="router in routersList"
                                        :key="router.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-sm font-extrabold text-gray-900 dark:text-gray-400"
                                        >
                                            {{ router.name }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-sm text-blue-700 dark:text-blue-400"
                                        >
                                            {{ router.ip_address }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                :class="[
                                                    'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                                    router.status === 'online'
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800',
                                                ]"
                                            >
                                                {{ router.status }}
                                            </span>
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-sm"
                                        >
                                            {{ router.model || '-' }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-sm"
                                        >
                                            {{ router.os_version || '-' }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-sm"
                                        >
                                            {{
                                                router.last_seen_at
                                                    ? new Date(
                                                          router.last_seen_at,
                                                      ).toLocaleString()
                                                    : '-'
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                                        >
                                            <div
                                                class="relative flex items-center justify-end"
                                            >
                                                <button
                                                    @click.stop="
                                                        toggleActions(router.id)
                                                    "
                                                    class="router-actions-toggle rounded p-2 hover:bg-green-400"
                                                    :aria-expanded="
                                                        actionsOpen[router.id]
                                                            ? 'true'
                                                            : 'false'
                                                    "
                                                    title="Show actions"
                                                >
                                                    <MoreHorizontal
                                                        class="h-5 w-5 text-blue-600"
                                                    />
                                                </button>
                                                <transition name="fade">
                                                    <div
                                                        v-if="
                                                            actionsOpen[
                                                                router.id
                                                            ]
                                                        "
                                                        class="absolute right-0 z-50 mt-2 flex space-x-2 rounded border bg-white p-2 shadow-lg"
                                                    >
                                                        <button
                                                            @click="
                                                                viewRouter(
                                                                    router,
                                                                );
                                                                closeAllActions();
                                                            "
                                                            title="View"
                                                            class="rounded p-2 hover:bg-gray-100"
                                                        >
                                                            <Eye
                                                                class="h-5 w-5 text-blue-600"
                                                            />
                                                        </button>
                                                        <button
                                                            @click="
                                                                editRouter(
                                                                    router,
                                                                );
                                                                closeAllActions();
                                                            "
                                                            title="Edit"
                                                            class="rounded p-2 hover:bg-gray-100"
                                                        >
                                                            <Edit
                                                                class="h-5 w-5 text-yellow-600"
                                                            />
                                                        </button>
                                                        <button
                                                            @click="
                                                                pingRouter(
                                                                    router,
                                                                );
                                                                closeAllActions();
                                                            "
                                                            :disabled="
                                                                pinging[
                                                                    router.id
                                                                ]
                                                            "
                                                            title="Ping Router"
                                                            class="rounded p-2 hover:bg-gray-100"
                                                        >
                                                            <Activity
                                                                class="h-5 w-5 text-green-600"
                                                            />
                                                        </button>
                                                        <button
                                                            @click="
                                                                testRouterConnection(
                                                                    router,
                                                                );
                                                                closeAllActions();
                                                            "
                                                            :disabled="
                                                                testing[
                                                                    router.id
                                                                ]
                                                            "
                                                            title="Test Connection"
                                                            class="rounded p-2 hover:bg-gray-100"
                                                        >
                                                            <TestTube
                                                                class="h-5 w-5 text-blue-600"
                                                            />
                                                        </button>
                                                        <button
                                                            @click="
                                                                showRemote(
                                                                    router,
                                                                );
                                                                closeAllActions();
                                                            "
                                                            title="Remote Management"
                                                            class="rounded p-2 hover:bg-gray-100"
                                                        >
                                                            <ExternalLink
                                                                class="h-5 w-5 text-purple-600"
                                                            />
                                                        </button>
                                                        <button
                                                            @click="
                                                                Inertia.visit(
                                                                    route(
                                                                        'mikrotiks.reprovision',
                                                                        router.id,
                                                                    ),
                                                                );
                                                                closeAllActions();
                                                            "
                                                            title="Reprovision/Show Script"
                                                            class="rounded p-2 hover:bg-gray-100"
                                                        >
                                                            <RotateCcw
                                                                class="h-5 w-5 text-indigo-600"
                                                            />
                                                        </button>
                                                        <button
                                                            @click="
                                                                downloadAdvancedConfig(
                                                                    router,
                                                                );
                                                                closeAllActions();
                                                            "
                                                            title="Download Advanced Config Script"
                                                            class="rounded p-2 hover:bg-gray-100"
                                                        >
                                                            <Download
                                                                class="h-5 w-5 text-purple-600"
                                                            />
                                                        </button>
                                                        <button
                                                            @click="
                                                                deleteRouter(
                                                                    router,
                                                                );
                                                                closeAllActions();
                                                            "
                                                            title="Delete"
                                                            class="rounded p-2 hover:bg-red-50"
                                                        >
                                                            <Trash2
                                                                class="h-5 w-5 text-red-600"
                                                            />
                                                        </button>
                                                    </div>
                                                </transition>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!routersList.length">
                                        <td
                                            colspan="7"
                                            class="px-6 py-4 text-center text-gray-500"
                                        >
                                            No Mikrotik routers found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="formError" class="mb-4 rounded bg-red-100 p-2 text-red-700">
            {{ formError }}
        </div>

        <!-- Add Router Wizard Modal -->
        <Modal :show="showAddModal" @close="closeModal">
            <div class="p-6">
                <h3 class="mb-4 text-lg font-semibold">Add Mikrotik Router</h3>
                <form @submit.prevent="submitForm">
                    <div class="mb-4">
                        <InputLabel for="name" value="Router Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="router_username" value="Username" />
                        <TextInput
                            id="router_username"
                            v-model="form.router_username"
                            class="mt-1 block w-full"
                            required
                            autocomplete="username"
                        />
                        <InputError :message="form.errors.router_username" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="router_password" value="Password" />
                        <TextInput
                            id="router_password"
                            v-model="form.router_password"
                            class="mt-1 block w-full"
                            type="password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError :message="form.errors.router_password" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="notes" value="Notes (optional)" />
                        <TextArea
                            id="notes"
                            v-model="form.notes"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <PrimaryButton type="submit">Add Router</PrimaryButton>
                        <DangerButton type="button" @click="closeModal"
                            >Cancel</DangerButton
                        >
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Edit Router Modal -->
        <Modal :show="showEditModal" @close="closeModal">
            <div class="p-6">
                <h2 class="mb-4 text-lg font-semibold">Edit Mikrotik Router</h2>
                <form @submit.prevent="editForm">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Router Name" />
                            <TextInput
                                v-model="form.name"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="IP Address" />
                            <TextInput
                                v-model="form.ip_address"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.ip_address" />
                        </div>
                        <div>
                            <InputLabel value="API Port" />
                            <TextInput
                                v-model="form.api_port"
                                type="number"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.api_port" />
                        </div>
                        <div>
                            <InputLabel value="SSH Port" />
                            <TextInput
                                v-model="form.ssh_port"
                                type="number"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.ssh_port" />
                        </div>
                        <div>
                            <InputLabel value="Username" />
                            <TextInput
                                v-model="form.router_username"
                                class="mt-1 block w-full"
                                autocomplete="username"
                            />
                            <InputError
                                :message="form.errors.router_username"
                            />
                        </div>
                        <div>
                            <InputLabel
                                value="Password (leave blank to keep current)"
                            />
                            <TextInput
                                v-model="form.router_password"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="current-password"
                            />
                            <InputError
                                :message="form.errors.router_password"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Connection Type" />
                            <select
                                v-model="form.connection_type"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:bg-black"
                            >
                                <option value="api">API</option>
                                <option value="ssh">SSH</option>
                                <option value="ovpn">OVPN</option>
                            </select>
                            <InputError
                                :message="form.errors.connection_type"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="OpenVPN Profile (optional)" />
                            <select
                                v-model="form.openvpn_profile_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:bg-black"
                            >
                                <option :value="null">None</option>
                                <option
                                    v-for="profile in openvpnProfiles"
                                    :key="profile.id"
                                    :value="profile.id"
                                >
                                    {{ profile.config_path }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.openvpn_profile_id"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Notes" />
                            <TextArea
                                v-model="form.notes"
                                class="mt-1 block w-full"
                                rows="3"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-3">
                        <DangerButton type="button" @click="closeModal">
                            Cancel
                        </DangerButton>
                        <PrimaryButton :disabled="form.processing">
                            Update
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Router Details Modal -->
        <Modal :show="showDetails" @close="showDetails = false">
            <div class="rounded-xl border border-dashed border-green-400 p-6">
                <h2 class="mb-4 text-lg font-semibold">
                    Router Details: {{ selectedRouter?.name }}
                </h2>
                <div class="mb-4 grid grid-cols-2 gap-4">
                    <div
                        class="rounded-xl border border-dashed border-blue-400 bg-gray-50 p-2 dark:bg-black"
                    >
                        <span class="font-medium">IP:</span>
                        {{ selectedRouter?.ip_address }}
                    </div>
                    <div
                        class="rounded-xl border border-dashed border-blue-400 bg-gray-50 p-2 dark:bg-black"
                    >
                        <span class="font-medium">Model:</span>
                        {{ selectedRouter?.model || '-' }}
                    </div>
                    <div
                        class="rounded-xl border border-dashed border-blue-400 bg-gray-50 p-2 dark:bg-black"
                    >
                        <span class="font-medium">OS Version:</span>
                        {{ selectedRouter?.os_version || '-' }}
                    </div>
                    <div
                        class="rounded-xl border border-dashed border-blue-400 bg-gray-50 p-2 dark:bg-black"
                    >
                        <span class="font-medium">Uptime:</span>
                        {{
                            selectedRouter?.uptime
                                ? formatUptime(selectedRouter.uptime)
                                : '-'
                        }}
                    </div>
                    <div
                        class="rounded-xl border border-dashed border-blue-400 bg-gray-50 p-2 dark:bg-black"
                    >
                        <span class="font-medium">CPU Usage:</span>
                        {{ selectedRouter?.cpu_usage ?? '-' }}%
                    </div>
                    <div
                        class="rounded-xl border border-dashed border-blue-400 bg-gray-50 p-2 dark:bg-black"
                    >
                        <span class="font-medium">Memory Usage:</span>
                        {{ selectedRouter?.memory_usage ?? '-' }}%
                    </div>
                    <div v-if="selectedRouter?.temperature">
                        <span class="font-medium">Temperature:</span>
                        {{ selectedRouter.temperature }}°C
                    </div>
                    <div
                        class="rounded-xl border border-dashed border-blue-400 bg-gray-50 p-2 dark:bg-black"
                    >
                        <span class="font-medium">Last Seen:</span>
                        {{
                            selectedRouter?.last_seen_at
                                ? new Date(
                                      selectedRouter.last_seen_at,
                                  ).toLocaleString()
                                : '-'
                        }}
                    </div>
                </div>
                <div v-if="selectedRouter?.notes" class="mb-4">
                    <span class="font-medium">Notes:</span>
                    {{ selectedRouter.notes }}
                </div>

                <div class="mb-4">
                    <h3 class="mb-2 font-extrabold text-blue-400">Logs</h3>
                    <div
                        class="max-h-32 overflow-y-auto rounded-xl bg-gray-50 p-2 dark:bg-black"
                    >
                        <div
                            v-for="log in selectedRouter?.logs"
                            :key="log.id"
                            class="mb-1 text-sm"
                        >
                            [{{
                                log.created_at
                                    ? new Date(log.created_at).toLocaleString()
                                    : ''
                            }}] {{ log.action }}: {{ log.message }} ({{
                                log.status
                            }})
                        </div>
                        <div
                            v-if="!selectedRouter?.logs?.length"
                            class="text-gray-500"
                        >
                            No logs found.
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="mb-2 font-semibold text-blue-500">
                        Bandwidth Usage (last 5 records)
                    </h3>
                    <div
                        class="max-h-32 overflow-y-auto rounded-xl bg-gray-50 p-2 dark:bg-black"
                    >
                        <div
                            v-for="bw in selectedRouter?.bandwidth_usage?.slice(
                                0,
                                5,
                            )"
                            :key="bw.id"
                            class="mb-1 text-sm"
                        >
                            {{ bw.interface_name }}:
                            {{ formatBytes(bw.bytes_in) }} in /
                            {{ formatBytes(bw.bytes_out) }} out @
                            {{ new Date(bw.timestamp).toLocaleString() }}
                        </div>
                        <div
                            v-if="!selectedRouter?.bandwidth_usage?.length"
                            class="text-gray-500"
                        >
                            No bandwidth data.
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="mb-2 font-semibold text-blue-500">Alerts</h3>
                    <div
                        class="max-h-32 overflow-y-auto rounded-xl bg-gray-50 p-2 dark:bg-black"
                    >
                        <div
                            v-for="alert in selectedRouter?.alerts"
                            :key="alert.id"
                            class="mb-1 text-sm"
                        >
                            [{{ alert.severity }}] {{ alert.alert_type }}:
                            {{ alert.message }}
                        </div>
                        <div
                            v-if="!selectedRouter?.alerts?.length"
                            class="text-gray-500"
                        >
                            No alerts.
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <PrimaryButton @click="showDetails = false"
                        >Close</PrimaryButton
                    >
                </div>
            </div>
        </Modal>

        <!-- Remote Management Modal -->
        <Modal :show="showRemoteModal" @close="showRemoteModal = false">
            <div class="p-6">
                <h2 class="mb-4 text-lg font-semibold">
                    Remote Management Links
                </h2>
                <div class="space-y-2">
                    <a
                        :href="remoteLinks.winbox"
                        target="_blank"
                        class="block w-full rounded bg-blue-600 px-4 py-2 text-center font-bold text-white hover:bg-blue-700"
                    >
                        Open in Winbox
                    </a>
                    <a
                        :href="remoteLinks.ssh"
                        target="_blank"
                        class="block w-full rounded bg-gray-600 px-4 py-2 text-center font-bold text-white hover:bg-gray-700"
                    >
                        Open SSH
                    </a>
                    <a
                        :href="remoteLinks.api"
                        target="_blank"
                        class="block w-full rounded bg-green-600 px-4 py-2 text-center font-bold text-white hover:bg-green-700"
                    >
                        Open API
                    </a>
                </div>
                <div class="mt-4 flex justify-end">
                    <PrimaryButton @click="showRemoteModal = false"
                        >Close</PrimaryButton
                    >
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
