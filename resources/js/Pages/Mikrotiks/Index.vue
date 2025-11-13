<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    mikrotiks: Array,
});

const showAddDevice = ref(false);
const deviceName = ref('');
const selectedDevice = ref(null);
const loading = ref(false);

const devices = computed(() => {
    return props.mikrotiks.map(m => ({
        ...m,
        status_badge: m.status === 'connected' ? 'bg-green-100 text-green-800' : 
                     m.status === 'disconnected' ? 'bg-red-100 text-red-800' : 
                     m.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                     'bg-gray-100 text-gray-800',
        onboarding_badge: m.onboarding_status === 'completed' ? 'bg-green-100 text-green-800' :
                         m.onboarding_status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                         m.onboarding_status === 'failed' ? 'bg-red-100 text-red-800' :
                         'bg-gray-100 text-gray-800',
    }));
});

const createNewDevice = () => {
    router.post(route('mikrotiks.store'), {
        name: deviceName.value,
    }, {
        onSuccess: () => {
            showAddDevice.value = false;
            deviceName.value = '';
        },
    });
};

const downloadScript = (mikrotikId) => {
    window.location.href = route('mikrotiks.download-script', mikrotikId);
};

const viewDetails = (device) => {
    router.visit(route('mikrotiks.show', device.id));
};

const deleteDevice = (device) => {
    if (confirm(`Are you sure you want to delete "${device.name}"? This cannot be undone.`)) {
        router.delete(route('mikrotiks.destroy', device.id));
    }
};

const formatDate = (date) => {
    if (!date) return 'Never';
    return new Date(date).toLocaleString();
};
</script>

<template>
    <Head title="Mikrotiks" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Mikrotiks
                </h2>
                <PrimaryButton @click="showAddDevice = !showAddDevice">
                    + Add Device
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Create New Device Section -->
                <div v-if="showAddDevice" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Add New Mikrotik Device</h3>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <InputLabel for="device_name" value="Device Name" />
                            <TextInput
                                id="device_name"
                                v-model="deviceName"
                                type="text"
                                placeholder="e.g., Main Router, Branch Office"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div class="flex gap-2 items-end">
                            <PrimaryButton @click="createNewDevice" :disabled="!deviceName.trim()">
                                Create Device
                            </PrimaryButton>
                            <SecondaryButton @click="showAddDevice = false">
                                Cancel
                            </SecondaryButton>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="bg-blue-50 dark:bg-slate-800 border border-blue-200 dark:border-slate-700 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-blue-900 dark:text-white mb-2">🚀 Getting Started</h3>
                    <ol class="list-decimal list-inside text-sm text-blue-800 dark:text-slate-300 space-y-1">
                        <li>Click "Add Device" to register a new Mikrotik device</li>
                        <li>Download the onboarding script provided</li>
                        <li>Run the script on your Mikrotik device via terminal</li>
                        <li>Your device will automatically appear as "Connected" when setup is complete</li>
                    </ol>
                </div>

                <!-- Devices List -->
                <div v-if="devices.length === 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-gray-500 dark:text-slate-300 text-center">No devices registered yet. Add one to get started!</p>
                </div>

                <div v-else class="grid gap-6">
                    <div v-for="device in devices" :key="device.id" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ device.name }}</h3>
                                    <p v-if="device.hostname" class="text-sm text-gray-600 dark:text-slate-300">{{ device.hostname }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <span :class="['px-3 py-1 rounded-full text-xs font-semibold', device.status_badge]">
                                        {{ device.status }}
                                    </span>
                                    <span :class="['px-3 py-1 rounded-full text-xs font-semibold', device.onboarding_badge]">
                                        {{ device.onboarding_status }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm">
                                <div>
                                    <p class="text-gray-600">IP Address</p>
                                    <p class="font-mono">{{ device.ip_address || 'Not set' }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-600">Last Seen</p>
                                    <p class="text-xs">{{ formatDate(device.last_seen_at) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-600">Status</p>
                                    <p class="font-semibold" :class="device.is_online ? 'text-green-600' : 'text-red-600'">
                                        {{ device.is_online ? '🟢 Online' : '🔴 Offline' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-600">Interfaces</p>
                                    <p>{{ device.interface_count || '-' }}</p>
                                </div>
                            </div>

                            <div v-if="device.onboarding_status !== 'completed'" class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-4 text-sm">
                                <p class="text-yellow-800 font-semibold mb-2">⚠️ Device Onboarding Pending</p>
                                <p class="text-yellow-700 mb-3">
                                    This device has not completed the onboarding process yet. 
                                    Download and run the setup script on your Mikrotik device.
                                </p>
                                <PrimaryButton @click="downloadScript(device.id)" class="text-sm">
                                    📥 Download Onboarding Script
                                </PrimaryButton>
                            </div>

                            <div class="flex gap-2">
                                <PrimaryButton @click="viewDetails(device)">
                                    View Details
                                </PrimaryButton>
                                <SecondaryButton @click="downloadScript(device.id)">
                                    Download Script
                                </SecondaryButton>
                                <button
                                    @click="deleteDevice(device)"
                                    class="px-4 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 text-sm font-semibold"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>