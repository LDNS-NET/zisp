<script setup>
import { ref, onUnmounted, computed } from 'vue';
import { route } from 'ziggy-js';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    router: Object,
    script: String,
});

const fetchCommandCopied = ref(false);
const waiting = ref(false);
const online = ref(false);
const pollingError = ref('');
const ipAddress = ref(props.router?.ip_address || '');
const ipError = ref('');
const settingIp = ref(false);
let statusCheckInterval = null;

const STATUS_CHECK_INTERVAL = 3000;
const MAX_WAIT_TIME = 5 * 60 * 1000;
const hasIpAddress = computed(() => ipAddress.value && ipAddress.value.trim() !== '');

// Generate the Mikrotik fetch command using public route with token
const scriptUrl = computed(() => {
    const url = route('mikrotiks.downloadScriptPublic', props.router.id);
    const token = props.router.sync_token;
    return token ? `${url}?token=${token}` : url;
});
const fetchCommand = computed(() => `/tool fetch url="${scriptUrl.value}" mode=https dst-path=onboard_${props.router.id}.rsc; :delay 2s; /import onboard_${props.router.id}.rsc`);

function copyFetchCommand() {
    navigator.clipboard.writeText(fetchCommand.value);
    fetchCommandCopied.value = true;
    setTimeout(() => (fetchCommandCopied.value = false), 2000);
}

function downloadScript() {
    window.location.href = scriptUrl.value;
}

async function setIpAddress() {
    if (!ipAddress.value || !ipAddress.value.trim()) {
        ipError.value = 'Please enter a valid IP address';
        return;
    }

    const ipRegex = /^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/;
    if (!ipRegex.test(ipAddress.value.trim())) {
        ipError.value = 'Please enter a valid IP address (e.g., 192.168.88.1)';
        return;
    }

    settingIp.value = true;
    ipError.value = '';

    try {
        const response = await fetch(route('mikrotiks.setIp', props.router.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ ip_address: ipAddress.value.trim() }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Failed to set IP address');
        }

        if (data.ip_address) {
            ipAddress.value = data.ip_address;
        }
    } catch (err) {
        ipError.value = err.message || 'Failed to set IP address';
    } finally {
        settingIp.value = false;
    }
}

function checkRouterStatus() {
    fetch(route('mikrotiks.status', props.router.id))
        .then((res) => {
            if (!res.ok) {
                return res.json().then(data => {
                    throw new Error(data.message || 'Failed to check router status.');
                });
            }
            return res.json();
        })
        .then((data) => {
            if (data.status === 'online') {
                online.value = true;
                waiting.value = false;
                stopStatusChecking();
                if (data.ip_address) {
                    ipAddress.value = data.ip_address;
                }
                window.toast?.success('Router is online and ready!') || console.log('Router is online!');
            } else {
                pollingError.value = '';
            }
        })
        .catch((err) => {
            console.debug('Status check error:', err);
        });
}

async function startStatusChecking() {
    if (statusCheckInterval) return;
    
    if (!hasIpAddress.value) {
        pollingError.value = 'Please enter the router IP address first';
        return;
    }

    if (!props.router?.ip_address || ipAddress.value.trim() !== props.router.ip_address) {
        await setIpAddress();
        if (ipError.value) {
            return;
        }
    }
    
    waiting.value = true;
    pollingError.value = '';
    online.value = false;
    
    const startTime = Date.now();
    checkRouterStatus();
    
    statusCheckInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        
        if (elapsed >= MAX_WAIT_TIME) {
            stopStatusChecking();
            waiting.value = false;
            pollingError.value = 'Router did not come online. Please check your internet connection and try again.';
            window.toast?.error('Router is offline. Please check the connection.') || 
                alert('Router is offline. Please check if the router is connected to the internet.');
            return;
        }
        
        checkRouterStatus();
    }, STATUS_CHECK_INTERVAL);
}

function stopStatusChecking() {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
        statusCheckInterval = null;
    }
}

onUnmounted(() => {
    stopStatusChecking();
});

function proceed() {
    router.visit(route('mikrotiks.index'));
}
</script>

<template>
    <Head title="Mikrotik Onboarding" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                Mikrotik Router Onboarding
            </h2>
        </template>

        <div class="mx-auto max-w-4xl py-8 space-y-6">
            <!-- Step 1: Run Script -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-white/20 text-white font-bold">1</span>
                        Run Setup Script on Router
                    </h3>
                </div>
                
                <div class="p-6 space-y-4">
                    <p class="text-gray-700 dark:text-gray-300">
                        Connect to your Mikrotik router terminal (Winbox, SSH, or WebFig) and run this command:
                    </p>

                    <!-- Fetch Command -->
                    <div class="bg-gray-900 rounded-lg p-4 relative group">
                        <code class="text-green-400 text-sm font-mono break-all">{{ fetchCommand }}</code>
                        <button
                            @click="copyFetchCommand"
                            class="absolute top-2 right-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded transition-colors"
                        >
                            {{ fetchCommandCopied ? '✓ Copied!' : 'Copy' }}
                        </button>
                    </div>

                    <!-- Alternative: Download -->
                    <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                        <span>Or</span>
                        <button
                            @click="downloadScript"
                            class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium underline"
                        >
                            download the script manually
                        </button>
                        <span>and paste it into the terminal</span>
                    </div>

                    <!-- What it does -->
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                            What does this script do?
                        </summary>
                        <ul class="mt-3 ml-6 space-y-1 list-disc text-sm text-gray-600 dark:text-gray-400">
                            <li>Configures router identity and system settings</li>
                            <li>Sets up WireGuard VPN tunnel to management server</li>
                            <li>Configures NAT and firewall rules for secure communication</li>
                            <li>Enables API access for remote management</li>
                            <li>Configures RADIUS authentication</li>
                            <li>Adds monitoring and health-check schedulers</li>
                        </ul>
                    </details>
                </div>
            </div>

            <!-- Step 2: Enter IP -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-white/20 text-white font-bold">2</span>
                        Enter Router IP Address
                    </h3>
                </div>
                
                <div class="p-6 space-y-4">
                    <p class="text-gray-700 dark:text-gray-300">
                        After running the script, copy one of the IP addresses shown in the output and enter it below:
                    </p>

                    <div class="flex gap-3">
                        <div class="flex-1">
                            <TextInput
                                id="ip_address"
                                v-model="ipAddress"
                                type="text"
                                placeholder="e.g., 192.168.88.1"
                                class="w-full"
                                :class="{ 'border-red-500': ipError }"
                                @keyup.enter="setIpAddress"
                            />
                            <InputError :message="ipError" class="mt-2" />
                            <p v-if="ipAddress && !ipError" class="mt-2 text-sm text-green-600 dark:text-green-400 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                IP Address saved: {{ ipAddress }}
                            </p>
                        </div>
                        <PrimaryButton
                            @click="setIpAddress"
                            :disabled="settingIp || !ipAddress"
                            class="self-start"
                        >
                            {{ settingIp ? 'Saving...' : 'Save IP' }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- Step 3: Verify -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-white/20 text-white font-bold">3</span>
                        Verify Connection
                    </h3>
                </div>
                
                <div class="p-6 space-y-4">
                    <p class="text-gray-700 dark:text-gray-300">
                        Click the button below to verify the router is online and connected to our system:
                    </p>

                    <div class="flex flex-wrap items-center gap-4">
                        <PrimaryButton
                            @click="startStatusChecking"
                            :disabled="waiting || online || !hasIpAddress"
                            class="px-6"
                        >
                            {{ waiting ? 'Checking...' : online ? 'Verified ✓' : 'Verify Connection' }}
                        </PrimaryButton>

                        <!-- Status Indicators -->
                        <div v-if="waiting" class="flex items-center gap-2 text-blue-600 dark:text-blue-400">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            <span class="text-sm">Checking status... (up to 5 minutes)</span>
                        </div>

                        <div v-if="online" class="flex items-center gap-2 text-green-600 dark:text-green-400 font-semibold">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Router is online!</span>
                        </div>

                        <div v-if="pollingError" class="text-red-600 dark:text-red-400 text-sm">
                            {{ pollingError }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex justify-end">
                <PrimaryButton
                    @click="proceed"
                    :disabled="!online"
                    class="px-8 py-3 text-lg"
                >
                    Continue to Dashboard →
                </PrimaryButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
