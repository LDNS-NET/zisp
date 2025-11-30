<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TextArea from '@/Components/TextArea.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import { useToast } from 'vue-toastification';
import {
    Plus,
    Search,
    Wifi,
    Server,
    Activity,
    Clock,
    MoreVertical,
    Eye,
    Edit,
    Trash2,
    Download,
    ExternalLink,
    RotateCcw,
    CheckCircle,
    XCircle,
    Cpu,
    HardDrive,
    Terminal
} from 'lucide-vue-next';

const toast = useToast();

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
const showActionsModal = ref(false);
const selectedRouter = ref(null);
const remoteLinks = ref({});
const pinging = ref({});
const formError = ref('');
const routersList = ref(props.routers || []);
const search = ref('');
let statusPollInterval = null;

// Watch for props changes
watch(() => props.routers, (newRouters) => {
    routersList.value = newRouters || [];
}, { immediate: true, deep: true });

onMounted(() => {
    startStatusPolling();
});

onUnmounted(() => {
    stopStatusPolling();
});

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
            toast.success('Router added successfully');
            closeModal();
        },
        onError: () => {
            toast.error('Error adding router');
        },
    });
}

function editForm() {
    if (selectedRouter.value) {
        form.put(route('mikrotiks.update', selectedRouter.value.id), {
            onSuccess: () => {
                toast.success('Router updated successfully');
                closeModal();
            },
            onError: () => {
                toast.error('Error updating router');
            },
        });
    }
}

function openEdit(router) {
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

function openActions(router) {
    selectedRouter.value = router;
    showActionsModal.value = true;
}

function deleteRouter(mikrotik) {
    if (confirm('Are you sure you want to delete this router?')) {
        router.delete(route('mikrotiks.destroy', mikrotik.id), {
            onSuccess: () => {
                toast.success('Router deleted successfully');
                routersList.value = routersList.value.filter(r => r.id !== mikrotik.id);
            },
        });
    }
}

async function pingRouter(router) {
    const vpnIp = router.wireguard_address ?? router.ip_address ?? 'unknown';
    toast.info(`Pinging router via RouterOS API (${vpnIp}) ...`);
    
    pinging.value[router.id] = true;

    try {
        const response = await fetch(route('mikrotiks.ping', router.id));
        const data = await response.json();

        if (!response.ok) {
            toast.error(data.message || 'Error pinging router');
            return;
        }

        // Update local state
        const index = routersList.value.findIndex(r => r.id === router.id);
        if (index !== -1) {
            routersList.value[index] = { 
                ...routersList.value[index],
                status: data.status,
                online: data.online,
                last_seen_at: data.last_seen_at
            };
        }

        const message = data.latency 
            ? `${data.message} (Latency: ${data.latency}ms)`
            : data.message;
        
        toast.success(message);
    } catch (err) {
        toast.error('Error pinging router');
    } finally {
        pinging.value[router.id] = false;
    }
}

function showRemote(router) {
    formError.value = '';
    fetch(route('mikrotiks.remoteManagement', router.id))
        .then(async (res) => {
            if (!res.ok) throw new Error('Failed to load links');
            return res.json();
        })
        .then((data) => {
            remoteLinks.value = data;
            showRemoteModal.value = true;
        })
        .catch(() => {
            toast.error('Error loading remote management links');
        });
}

function downloadAdvancedConfig(router) {
    try {
        const url = route('mikrotiks.downloadAdvancedConfig', router.id);
        const link = document.createElement('a');
        link.href = url;
        link.download = `pppoe_hotspot_${router.id}.rsc`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast.success('Download started');
    } catch (err) {
        toast.error('Error downloading config');
    }
}

function startStatusPolling() {
    statusPollInterval = setInterval(refreshRouterStatus, 5000);
}

function stopStatusPolling() {
    if (statusPollInterval) {
        clearInterval(statusPollInterval);
        statusPollInterval = null;
    }
}

async function refreshRouterStatus() {
    try {
        const response = await fetch(route('mikrotiks.statusAll'));
        if (response.ok) {
            const data = await response.json();
            if (data.success && Array.isArray(data.routers)) {
                data.routers.forEach((routerStatus) => {
                    const index = routersList.value.findIndex(r => r.id === routerStatus.id);
                    if (index !== -1) {
                        routersList.value[index] = {
                            ...routersList.value[index],
                            status: routerStatus.status,
                            online: routerStatus.online,
                            last_seen_at: routerStatus.last_seen_at,
                            cpu: routerStatus.cpu,
                            memory: routerStatus.memory,
                            uptime: routerStatus.uptime,
                        };
                    }
                });
            }
        }
    } catch (err) {
        console.debug('Status refresh failed:', err);
    }
}

function formatUptime(uptime) {
    if (!uptime) return '-';
    const days = Math.floor(uptime / 86400);
    const hours = Math.floor((uptime % 86400) / 3600);
    const minutes = Math.floor((uptime % 3600) / 60);
    return `${days}d ${hours}h ${minutes}m`;
}

// Filtered routers based on search
const filteredRouters = ref(routersList.value);
watch([routersList, search], () => {
    if (!search.value) {
        filteredRouters.value = routersList.value;
        return;
    }
    const q = search.value.toLowerCase();
    filteredRouters.value = routersList.value.filter(r => 
        r.name.toLowerCase().includes(q) || 
        (r.ip_address && r.ip_address.includes(q)) ||
        (r.wireguard_address && r.wireguard_address.includes(q))
    );
});
</script>

<template>
    <Head title="Mikrotik Routers" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Wifi class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        Mikrotik Routers
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your network infrastructure and connectivity
                    </p>
                </div>
                <PrimaryButton @click="showAddModal = true" class="flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    <span>Add Router</span>
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Search -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search routers..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out"
                    />
                </div>
            </div>

            <!-- Routers Table (Desktop) / Cards (Mobile) -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Router Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">VPN IP</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Resources</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Uptime</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="router in filteredRouters" :key="router.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                            <Server class="w-5 h-5" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ router.name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ router.model || 'Unknown Model' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-600 dark:text-gray-300">
                                        {{ router.wireguard_address || router.ip_address || 'Not configured' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        router.status === 'online'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                    ]">
                                        {{ router.status === 'online' ? 'Online' : 'Offline' }}
                                    </span>
                                    <div class="text-xs text-gray-400 mt-1">
                                        Last seen: {{ router.last_seen_at ? new Date(router.last_seen_at).toLocaleTimeString() : '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                            <Cpu class="w-3 h-3" />
                                            <span>CPU: {{ router.cpu ? router.cpu + '%' : '-' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                            <HardDrive class="w-3 h-3" />
                                            <span>Mem: {{ router.memory ? router.memory + '%' : '-' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center gap-1">
                                        <Clock class="w-3 h-3" />
                                        {{ router.uptime ? formatUptime(router.uptime) : '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openActions(router)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700" title="Manage Router">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredRouters.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <Wifi class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                        <p class="text-lg font-medium">No routers found</p>
                                        <p class="text-sm">Try adding a new router</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-gray-200 dark:divide-slate-700">
                    <div v-for="router in filteredRouters" :key="router.id" class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                    <Server class="w-5 h-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ router.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ router.wireguard_address || router.ip_address || 'No IP' }}</div>
                                </div>
                            </div>
                            <span :class="[
                                'px-2 py-0.5 text-xs font-semibold rounded-full',
                                router.status === 'online'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                            ]">
                                {{ router.status === 'online' ? 'Online' : 'Offline' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-slate-900/50 p-2 rounded-lg">
                            <div class="flex flex-col items-center justify-center p-1">
                                <span class="uppercase tracking-wider text-[10px] opacity-70">CPU</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ router.cpu ? router.cpu + '%' : '-' }}</span>
                            </div>
                            <div class="flex flex-col items-center justify-center p-1 border-l border-gray-200 dark:border-slate-700">
                                <span class="uppercase tracking-wider text-[10px] opacity-70">Mem</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ router.memory ? router.memory + '%' : '-' }}</span>
                            </div>
                            <div class="flex flex-col items-center justify-center p-1 border-l border-gray-200 dark:border-slate-700">
                                <span class="uppercase tracking-wider text-[10px] opacity-70">Uptime</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ router.uptime ? formatUptime(router.uptime).split(' ')[0] : '-' }}</span>
                            </div>
                        </div>

                        <button @click="openActions(router)" class="w-full flex items-center justify-center gap-2 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                            <MoreVertical class="w-4 h-4" /> Manage Router
                        </button>
                    </div>
                    <div v-if="filteredRouters.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No routers found.
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Router Modal -->
        <Modal :show="showAddModal" @close="closeModal">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Add Mikrotik Router</h3>
                <form @submit.prevent="submitForm">
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="name" value="Router Name" />
                            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autofocus />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="router_username" value="Username" />
                            <TextInput id="router_username" v-model="form.router_username" class="mt-1 block w-full" required autocomplete="username" />
                            <InputError :message="form.errors.router_username" />
                        </div>
                        <div>
                            <InputLabel for="router_password" value="Password" />
                            <TextInput id="router_password" v-model="form.router_password" type="password" class="mt-1 block w-full" required autocomplete="current-password" />
                            <InputError :message="form.errors.router_password" />
                        </div>
                        <div>
                            <InputLabel for="notes" value="Notes (optional)" />
                            <TextArea id="notes" v-model="form.notes" class="mt-1 block w-full" rows="3" />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="closeModal">Cancel</DangerButton>
                        <PrimaryButton :disabled="form.processing">Add Router</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Edit Router Modal -->
        <Modal :show="showEditModal" @close="closeModal">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Edit Mikrotik Router</h3>
                <form @submit.prevent="editForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Router Name" />
                            <TextInput v-model="form.name" class="mt-1 block w-full" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel value="VPN IP Address (10.100.0.0/16)" />
                            <TextInput v-model="form.ip_address" placeholder="e.g. 10.100.0.2" class="mt-1 block w-full" />
                            <InputError :message="form.errors.ip_address" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Communication uses VPN tunnel IP only</p>
                        </div>
                        <div>
                            <InputLabel value="API Port" />
                            <TextInput v-model="form.api_port" type="number" class="mt-1 block w-full" />
                            <InputError :message="form.errors.api_port" />
                        </div>
                        <div>
                            <InputLabel value="SSH Port" />
                            <TextInput v-model="form.ssh_port" type="number" class="mt-1 block w-full" />
                            <InputError :message="form.errors.ssh_port" />
                        </div>
                        <div>
                            <InputLabel value="Username" />
                            <TextInput v-model="form.router_username" class="mt-1 block w-full" autocomplete="username" />
                            <InputError :message="form.errors.router_username" />
                        </div>
                        <div>
                            <InputLabel value="Password (leave blank to keep)" />
                            <TextInput v-model="form.router_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                            <InputError :message="form.errors.router_password" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Connection Type" />
                            <select v-model="form.connection_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="api">API</option>
                                <option value="ssh">SSH</option>
                                <option value="ovpn">OVPN</option>
                            </select>
                            <InputError :message="form.errors.connection_type" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="OpenVPN Profile (optional)" />
                            <select v-model="form.openvpn_profile_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option :value="null">None</option>
                                <option v-for="profile in openvpnProfiles" :key="profile.id" :value="profile.id">
                                    {{ profile.config_path }}
                                </option>
                            </select>
                            <InputError :message="form.errors.openvpn_profile_id" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Notes" />
                            <TextArea v-model="form.notes" class="mt-1 block w-full" rows="3" />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="closeModal">Cancel</DangerButton>
                        <PrimaryButton :disabled="form.processing">Update Router</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Actions Modal -->
        <Modal :show="showActionsModal" @close="showActionsModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white" v-if="selectedRouter">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Manage {{ selectedRouter.name }}</h3>
                    <button @click="showActionsModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-6 h-6" />
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <button @click="viewRouter(selectedRouter); showActionsModal = false" class="flex flex-col items-center justify-center p-4 bg-gray-50 dark:bg-slate-700 rounded-xl hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:text-blue-600 dark:hover:text-blue-400 transition-all group">
                        <Eye class="w-8 h-8 mb-2 text-gray-400 group-hover:text-blue-500" />
                        <span class="font-medium">View Details</span>
                    </button>

                    <button @click="openEdit(selectedRouter); showActionsModal = false" class="flex flex-col items-center justify-center p-4 bg-gray-50 dark:bg-slate-700 rounded-xl hover:bg-amber-50 dark:hover:bg-amber-900/20 hover:text-amber-600 dark:hover:text-amber-400 transition-all group">
                        <Edit class="w-8 h-8 mb-2 text-gray-400 group-hover:text-amber-500" />
                        <span class="font-medium">Edit Router</span>
                    </button>

                    <button @click="pingRouter(selectedRouter); showActionsModal = false" :disabled="pinging[selectedRouter.id]" class="flex flex-col items-center justify-center p-4 bg-gray-50 dark:bg-slate-700 rounded-xl hover:bg-green-50 dark:hover:bg-green-900/20 hover:text-green-600 dark:hover:text-green-400 transition-all group">
                        <Activity class="w-8 h-8 mb-2 text-gray-400 group-hover:text-green-500" />
                        <span class="font-medium">Ping Router</span>
                    </button>

                    <button @click="showRemote(selectedRouter); showActionsModal = false" class="flex flex-col items-center justify-center p-4 bg-gray-50 dark:bg-slate-700 rounded-xl hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-all group">
                        <ExternalLink class="w-8 h-8 mb-2 text-gray-400 group-hover:text-purple-500" />
                        <span class="font-medium">Remote Mgmt</span>
                    </button>

                    <button @click="selectedRouter.visit(route('mikrotiks.reprovision', selectedRouter.id)); showActionsModal = false" class="flex flex-col items-center justify-center p-4 bg-gray-50 dark:bg-slate-700 rounded-xl hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all group">
                        <RotateCcw class="w-8 h-8 mb-2 text-gray-400 group-hover:text-indigo-500" />
                        <span class="font-medium">Reprovision</span>
                    </button>

                    <button @click="downloadAdvancedConfig(selectedRouter); showActionsModal = false" class="flex flex-col items-center justify-center p-4 bg-gray-50 dark:bg-slate-700 rounded-xl hover:bg-cyan-50 dark:hover:bg-cyan-900/20 hover:text-cyan-600 dark:hover:text-cyan-400 transition-all group">
                        <Download class="w-8 h-8 mb-2 text-gray-400 group-hover:text-cyan-500" />
                        <span class="font-medium">Download Config</span>
                    </button>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
                    <button @click="deleteRouter(selectedRouter); showActionsModal = false" class="w-full flex items-center justify-center gap-2 p-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-xl hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors font-medium">
                        <Trash2 class="w-5 h-5" /> Delete Router
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Remote Management Modal -->
        <Modal :show="showRemoteModal" @close="showRemoteModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Remote Management Links</h3>
                <div v-if="remoteLinks" class="space-y-4">
                    <div v-for="(link, type) in remoteLinks" :key="type" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700 rounded-lg">
                        <span class="font-medium capitalize">{{ type }}</span>
                        <a :href="link" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1">
                            Open <ExternalLink class="w-3 h-3" />
                        </a>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <PrimaryButton @click="showRemoteModal = false">Close</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- View Details Modal (Simple) -->
        <Modal :show="showDetails" @close="showDetails = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white" v-if="selectedRouter">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ selectedRouter.name }} Details</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="block text-gray-500 dark:text-gray-400">IP Address</span>
                        <span class="font-medium">{{ selectedRouter.ip_address || '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 dark:text-gray-400">VPN IP</span>
                        <span class="font-medium">{{ selectedRouter.wireguard_address || '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 dark:text-gray-400">Model</span>
                        <span class="font-medium">{{ selectedRouter.model || '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-gray-500 dark:text-gray-400">OS Version</span>
                        <span class="font-medium">{{ selectedRouter.os_version || '-' }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="block text-gray-500 dark:text-gray-400">Notes</span>
                        <p class="mt-1 text-gray-700 dark:text-gray-300">{{ selectedRouter.notes || 'No notes' }}</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <PrimaryButton @click="showDetails = false">Close</PrimaryButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
