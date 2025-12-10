<script setup>
import { ref, onUnmounted, computed } from 'vue';
import { route } from 'ziggy-js';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    router: Object,
    script: String,
});

const fetchCommandCopied = ref(false);
const waiting = ref(false);
const online = ref(false);
const pollingError = ref('');
const detectedIp = ref(props.router?.wireguard_address || '');
let statusCheckInterval = null;

const STATUS_CHECK_INTERVAL = 3000;
const MAX_WAIT_TIME = 5 * 60 * 1000;

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
    
    // Start polling automatically when command is copied
    if (!waiting.value && !online.value) {
        startStatusChecking();
    }
}

function downloadScript() {
    window.location.href = scriptUrl.value;
    
    // Start polling automatically when script is downloaded
    if (!waiting.value && !online.value) {
        setTimeout(() => startStatusChecking(), 500);
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
                
                // Update detected IP
                if (data.wireguard_address) {
                    detectedIp.value = data.wireguard_address;
                }
                
                window.toast?.success('Router is online and ready!') || console.log('Router is online!');
            } else {
                // Check if WireGuard address was registered (phone-home happened)
                if (data.wireguard_address) {
                    detectedIp.value = data.wireguard_address;
                }
                pollingError.value = '';
            }
        })
        .catch((err) => {
            console.debug('Status check error:', err);
        });
}

function startStatusChecking() {
    if (statusCheckInterval) return;
    
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
            pollingError.value = 'Router did not come online within 5 minutes. Please check your connection and try again.';
            window.toast?.error('Router connection timeout. Please verify the script ran successfully.') || 
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

                    <!-- Device Mode Warning -->
                    <div class="rounded-lg border-l-4 border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 p-4">
                        <div class="flex gap-3">
                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div class="flex-1">
                                <p class="font-semibold text-yellow-800 dark:text-yellow-300 mb-1">Mikrotik v7 Device Mode Issue</p>
                                <p class="text-sm text-yellow-700 dark:text-yellow-400 mb-2">
                                    If the command fails with <span class="font-mono bg-yellow-100 dark:bg-yellow-900/40 px-1 rounded">"device mode not allowed"</span>, follow these steps:
                                </p>
                                <ol class="text-sm text-yellow-700 dark:text-yellow-400 space-y-1 list-decimal list-inside">
                                    <li>Run: <code class="font-mono bg-yellow-100 dark:bg-yellow-900/40 px-1 rounded">/system/device-mode update mode=advanced</code></li>
                                    <li>Unplug the power cord for 10 seconds</li>
                                    <li>Restore power and wait for the router to boot</li>
                                    <li>Retry the provisioning command above</li>
                                </ol>
                            </div>
                        </div>
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

            <!-- Step 2: Wait for Connection -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-white/20 text-white font-bold">2</span>
                        Waiting for Router Connection
                    </h3>
                </div>
                
                <div class="p-6 space-y-4">
                    <p class="text-gray-700 dark:text-gray-300">
                        The system is automatically monitoring for your router to come online...
                    </p>

                    <!-- Status Display -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 space-y-3">
                        <!-- Waiting State -->
                        <div v-if="waiting" class="flex items-center gap-3">
                            <svg class="w-6 h-6 animate-spin text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 dark:text-gray-100">Checking for router...</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Waiting for phone-home signal (up to 5 minutes)</p>
                            </div>
                        </div>

                        <!-- Detected IP -->
                        <div v-if="detectedIp" class="flex items-center gap-3 text-green-600 dark:text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="font-medium">WireGuard VPN IP Detected</p>
                                <p class="text-sm font-mono">{{ detectedIp }}</p>
                            </div>
                        </div>

                        <!-- Online State -->
                        <div v-if="online" class="flex items-center gap-3 text-green-600 dark:text-green-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-lg font-semibold">Router is Online!</p>
                                <p class="text-sm">Successfully connected and ready for management</p>
                            </div>
                        </div>

                        <!-- Error State -->
                        <div v-if="pollingError" class="flex items-center gap-3 text-red-600 dark:text-red-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="font-medium">Connection Timeout</p>
                                <p class="text-sm">{{ pollingError }}</p>
                            </div>
                        </div>

                        <!-- Idle State -->
                        <div v-if="!waiting && !online && !pollingError" class="flex items-center gap-3 text-gray-600 dark:text-gray-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>Copy or download the script above to begin monitoring</p>
                        </div>
                    </div>

                    <!-- Manual retry -->
                    <div v-if="pollingError" class="flex justify-center">
                        <button
                            @click="startStatusChecking"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                        >
                            Retry Connection Check
                        </button>
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
