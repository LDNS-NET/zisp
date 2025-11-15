<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    mikrotik: Object,
});

const showScript = ref(false);
const loading = ref(false);
const loadingScript = ref(false);
const scriptContent = ref(null);

const downloadScript = () => {
    window.location.href = props.mikrotik.download_script_url;
};

const fetchScriptContent = async () => {
    if (scriptContent.value) {
        showScript.value = !showScript.value;
        return;
    }

    loadingScript.value = true;
    try {
        const res = await fetch(props.mikrotik.download_script_url, { credentials: 'same-origin' });
        if (!res.ok) {
            alert('Failed to fetch onboarding script');
            return;
        }

        scriptContent.value = await res.text();
        showScript.value = true;
    } catch (e) {
        console.error(e);
        alert('Error fetching script');
    } finally {
        loadingScript.value = false;
    }
};

const regenerateScript = () => {
    if (confirm('This will generate a new onboarding script. The device will need to run it again to reconnect.')) {
        router.post(route('mikrotiks.regenerate-script', props.mikrotik.id));
    }
};

const testConnection = () => {
    loading.value = true;
    router.post(route('mikrotiks.test-connection', props.mikrotik.id), {}, {
        onFinish: () => {
            loading.value = false;
        },
    });
};

const editDevice = () => {
    router.visit(route('mikrotiks.edit', props.mikrotik.id));
};

const deleteDevice = () => {
    if (confirm(`Are you sure you want to delete "${props.mikrotik.name}"?`)) {
        router.delete(route('mikrotiks.destroy', props.mikrotik.id));
    }
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    alert('Copied to clipboard!');
};

const formatDate = (date) => {
    if (!date) return 'Never';
    return new Date(date).toLocaleString();
};
</script>

<template>
    <Head :title="mikrotik.name" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ mikrotik.name }}
                </h2>
                <SecondaryButton @click="() => $inertia.visit(route('mikrotiks.index'))">
                    ← Back
                </SecondaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

                <!-- Status Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <p class="text-gray-600 text-sm">Connection Status</p>
                        <p class="text-2xl font-bold" :class="mikrotik.is_online ? 'text-green-600' : 'text-red-600'">
                            {{ mikrotik.is_online ? '🟢 Online' : '🔴 Offline' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-slate-300 mt-1">{{ formatDate(mikrotik.last_seen_at) }}</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <p class="text-gray-600 text-sm">Onboarding Status</p>
                        <p class="text-2xl font-bold" :class="
                            mikrotik.onboarding_status === 'completed' ? 'text-green-600' :
                            mikrotik.onboarding_status === 'in_progress' ? 'text-blue-600' :
                            mikrotik.onboarding_status === 'failed' ? 'text-red-600' :
                            'text-gray-600'
                        ">
                            {{ mikrotik.onboarding_status }}
                        </p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <p class="text-gray-600 text-sm">Sync Status</p>
                        <p class="text-2xl font-bold text-blue-600">{{ mikrotik.sync_attempts }}</p>
                        <p class="text-xs text-gray-500 mt-1">sync attempts</p>
                    </div>
                </div>

                <!-- Device Information -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Device Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600 text-sm">Device ID</p>
                            <p class="font-mono text-sm">{{ mikrotik.device_id || 'Not available' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Hostname</p>
                            <p>{{ mikrotik.hostname || 'Not set' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Board Name</p>
                            <p>{{ mikrotik.board_name || 'Not available' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">System Version</p>
                            <p>{{ mikrotik.system_version || 'Not available' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Interface Count</p>
                            <p>{{ mikrotik.interface_count || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">IP Address</p>
                            <p class="font-mono">{{ mikrotik.ip_address || 'Not set' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Connection Type</p>
                            <p>{{ mikrotik.connection_type || 'Not set' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">SSH Port</p>
                            <p>{{ mikrotik.ssh_port || '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Health & Performance -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Health &amp; Performance</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600 text-sm">Uptime (seconds)</p>
                            <p>{{ mikrotik.uptime ?? 'Unknown' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">CPU Usage (%)</p>
                            <p>{{ mikrotik.cpu_usage ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Memory Usage (%)</p>
                            <p>{{ mikrotik.memory_usage ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Temperature (°C)</p>
                            <p>{{ mikrotik.temperature ?? '-' }}</p>
                        </div>
                    </div>
                </div>

                <!-- WireGuard Configuration -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">WireGuard Configuration</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600 text-sm mb-2">Public Key</p>
                            <code class="bg-gray-100 dark:bg-slate-900 p-2 rounded text-xs block overflow-auto min-h-[2.5rem]">
                                {{ mikrotik.wireguard_public_key || 'Not configured' }}
                            </code>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm mb-2">Allowed IPs</p>
                            <code class="bg-gray-100 dark:bg-slate-900 p-2 rounded text-xs block overflow-auto min-h-[2.5rem]">
                                {{ mikrotik.wireguard_allowed_ips || 'Not configured' }}
                            </code>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">WireGuard Address</p>
                            <p class="font-mono">{{ mikrotik.wireguard_address || 'Not set' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">WireGuard Port</p>
                            <p>{{ mikrotik.wireguard_port || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">WireGuard Status</p>
                            <p>{{ mikrotik.wireguard_status || 'Unknown' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm">Last Handshake</p>
                            <p>{{ mikrotik.wireguard_last_handshake ? formatDate(mikrotik.wireguard_last_handshake) : 'Never' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Authentication Tokens -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Authentication Tokens</h3>
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-600 text-sm mb-2">Sync Token</p>
                            <div class="flex gap-2 items-center">
                                <code class="bg-gray-100 dark:bg-slate-900 p-2 rounded text-xs flex-1 overflow-auto">{{ mikrotik.sync_token }}</code>
                                <button @click="copyToClipboard(mikrotik.sync_token)" class="text-blue-600 hover:text-blue-800 text-sm">Copy</button>
                            </div>
                        </div>
                        <div>
                            <p class="text-gray-600 text-sm mb-2">Onboarding Token</p>
                            <div class="flex gap-2 items-center">
                                <code class="bg-gray-100 dark:bg-slate-900 p-2 rounded text-xs flex-1 overflow-auto">{{ mikrotik.onboarding_token }}</code>
                                <button @click="copyToClipboard(mikrotik.onboarding_token)" class="text-blue-600 hover:text-blue-800 text-sm">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onboarding Script Section -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Onboarding Script</h3>
                    <p class="text-gray-600 mb-4 text-sm">
                        Download the onboarding script and run it on your Mikrotik device terminal to complete the setup.
                    </p>
                    <div class="flex gap-2 mb-4">
                        <PrimaryButton @click="downloadScript">
                            📥 Download Script
                        </PrimaryButton>
                        <SecondaryButton @click="fetchScriptContent" :disabled="loadingScript">
                            {{ loadingScript ? 'Loading…' : (showScript ? 'Hide' : 'View') + ' Script' }}
                        </SecondaryButton>
                    </div>

                    <div v-if="showScript" class="bg-gray-900 text-green-400 p-4 rounded font-mono text-xs overflow-auto max-h-96">
                        <pre>{{ scriptContent || 'No script available. Use Download to fetch the file.' }}</pre>
                    </div>
                </div>

                <!-- Error Information -->
                <div v-if="mikrotik.last_error" class="bg-red-50 dark:bg-slate-800 border border-red-200 dark:border-slate-700 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-red-900 dark:text-red-300 mb-2">Last Error</h3>
                    <p class="text-red-700 dark:text-red-400">{{ mikrotik.last_error }}</p>
                </div>

                <!-- Action Buttons -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Actions</h3>
                    <div class="flex gap-2 flex-wrap">
                        <PrimaryButton @click="testConnection" :disabled="loading">
                            {{ loading ? 'Testing...' : '🔗 Test Connection' }}
                        </PrimaryButton>
                        <SecondaryButton @click="regenerateScript">
                            🔄 Regenerate Script
                        </SecondaryButton>
                        <SecondaryButton @click="editDevice">
                            ✏️ Edit Device
                        </SecondaryButton>
                        <button
                            @click="deleteDevice"
                            class="px-4 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 font-semibold"
                        >
                            🗑️ Delete Device
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
code {
    user-select: all;
}
</style>
