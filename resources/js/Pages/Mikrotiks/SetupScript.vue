<script setup>
import { ref, onUnmounted, watch, computed } from 'vue';
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

const copied = ref(false);
const waiting = ref(false);
const online = ref(false);
const pollingError = ref('');
const ipAddress = ref(props.router?.ip_address || '');
const ipError = ref('');
const settingIp = ref(false);
let statusCheckInterval = null;
let timeoutTimer = null;

const STATUS_CHECK_INTERVAL = 3000; // Check every 3 seconds
const MAX_WAIT_TIME = 5 * 60 * 1000; // 5 minutes in milliseconds
const hasIpAddress = computed(() => ipAddress.value && ipAddress.value.trim() !== '');

// -------------------
// Copy & Download
// -------------------
function copyScript() {
    navigator.clipboard.writeText(props.script);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function downloadScript() {
    const blob = new Blob([props.script], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    // use .rsc.txt to make it openable on Windows
    a.download = `setup_router_${props.router.id}.rsc.txt`;
    a.click();
    URL.revokeObjectURL(url);
}

// -------------------
// Set IP Address
// -------------------
async function setIpAddress() {
    if (!ipAddress.value || !ipAddress.value.trim()) {
        ipError.value = 'Please enter a valid IP address';
        return;
    }

    // Basic IP validation
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

        // Update IP address with the cleaned version from server
        if (data.ip_address) {
            ipAddress.value = data.ip_address;
        }
    } catch (err) {
        ipError.value = err.message || 'Failed to set IP address';
    } finally {
        settingIp.value = false;
    }
}

// -------------------
// Status Checking Logic (checks database, not API)
// -------------------
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
                // Update IP address if returned from server
                if (data.ip_address) {
                    ipAddress.value = data.ip_address;
                }
                // Show success notification
                window.toast?.success('Router is online and ready!') || console.log('Router is online!');
            } else {
                // Keep checking if status is pending or offline
                // Don't show error yet, just keep checking
                pollingError.value = '';
            }
        })
        .catch((err) => {
            // Silently continue checking on error
            console.debug('Status check error:', err);
        });
}

async function startStatusChecking() {
    if (statusCheckInterval) return; // prevent duplicate intervals
    
    // Check if IP address is set
    if (!hasIpAddress.value) {
        pollingError.value = 'Please enter the router IP address first';
        return;
    }

    // Ensure IP is saved before starting to check
    // If we have an IP but it might not be saved, save it first
    if (!props.router?.ip_address || ipAddress.value.trim() !== props.router.ip_address) {
        await setIpAddress();
        if (ipError.value) {
            return; // Don't start checking if IP save failed
        }
    }
    
    startStatusCheckingInternal();
}

function startStatusCheckingInternal() {
    waiting.value = true;
    pollingError.value = '';
    online.value = false;
    
    const startTime = Date.now();
    
    // Check immediately first
    checkRouterStatus();
    
    // Then check every 3 seconds
    statusCheckInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        
        // If 5 minutes have passed, stop checking and show error
        if (elapsed >= MAX_WAIT_TIME) {
            stopStatusChecking();
            waiting.value = false;
            pollingError.value = 'Router is offline. Please check if the router is connected to the internet and the script was run successfully.';
            window.toast?.error('Router is offline. Please check if the router is connected to the internet.') || 
                alert('Router is offline. Please check if the router is connected to the internet and the script was run successfully.');
            return;
        }
        
        checkRouterStatus();
    }, STATUS_CHECK_INTERVAL);
    
    // Set timeout as backup (in case interval doesn't catch it)
    timeoutTimer = setTimeout(() => {
        if (!online.value) {
            stopStatusChecking();
            waiting.value = false;
            pollingError.value = 'Router is offline. Please check if the router is connected to the internet and the script was run successfully.';
            window.toast?.error('Router is offline. Please check if the router is connected to the internet.') || 
                alert('Router is offline. Please check if the router is connected to the internet and the script was run successfully.');
        }
    }, MAX_WAIT_TIME);
}

function stopStatusChecking() {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
        statusCheckInterval = null;
    }
    if (timeoutTimer) {
        clearTimeout(timeoutTimer);
        timeoutTimer = null;
    }
}

onUnmounted(() => {
    stopStatusChecking();
});

// Optional user feedback when router becomes online
watch(online, (isOnline) => {
    if (isOnline) {
        // Success notification is already shown in checkRouterStatus()
        // No need for duplicate alert
    }
});

function proceed() {
    // Redirect to router index or next onboarding step
    router.visit(route('mikrotiks.index'));
}
</script>

<template>
    <Head title="Mikrotik Onboarding" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                Mikrotik System Onboarding Script
            </h2>
        </template>

        <div class="mx-auto max-w-3xl py-8">
            <div
                class="rounded-xl border border-dashed border-blue-400 bg-white p-6 shadow dark:bg-black"
            >
                <p class="mb-4">
                    <b class="text-blue-600">Step 1:<br /></b>
                    Copy or download the script below and run it in your
                    Mikrotik terminal (Winbox, WebFig, or SSH).<br />
                    <b class="text-blue-500">Step 2:<br /></b>
                    After running the script, copy one of the
                    <b class="text-blue-500">IP addresses</b> shown in the script output
                    and paste it in the IP address field below.<br />
                    <b class="text-blue-600">Step 3:<br /></b>
                    Click <b class="text-blue-600">"I've run the script"</b> to
                    check the router status. The system will monitor the router status
                    for up to 5 minutes. If the router doesn't come online, please check
                    if the router is connected to the internet.
                </p>

                <div
                    class="mb-4 rounded border-l-4 border-blue-400 bg-blue-50 p-4 text-blue-900 dark:border-green-600 dark:bg-blue-900 dark:text-blue-200"
                >
                    <b>What does this script do?</b>
                    <ul class="ml-6 mt-2 list-disc text-sm">
                        <li>Sets the router's identity (name)</li>
                        <li>Prints all current IP addresses for onboarding</li>
                        <li>Enables API and adds a system API user</li>
                        <li>
                            Restricts API/Winbox/SSH to trusted IPs (edit as
                            needed)
                        </li>
                        <li>
                            Enables OVPN server and RADIUS authentication for
                            PPP, Hotspot, and OVPN
                        </li>
                        <li>
                            Adds a RADIUS client for full system authentication
                        </li>
                        <li>
                            Adds a <b>disconnect-user</b> script for remote user
                            management
                        </li>
                        <li>
                            Adds a <b>health-check</b> scheduler for system
                            monitoring
                        </li>
                        <li>Follows best security and automation practices</li>
                    </ul>
                </div>

                <!-- Copy / Download Buttons -->
                <div class="mb-4 flex gap-2">
                    <PrimaryButton @click="copyScript">
                        {{ copied ? 'Copied!' : 'Copy Script' }}
                    </PrimaryButton>
                    <PrimaryButton @click="downloadScript">
                        Download Script
                    </PrimaryButton>
                </div>

                <!-- Script Display -->
                <pre
                    class="mb-6 overflow-x-auto rounded bg-gray-900 p-4 text-xs text-green-200"
                    style="min-height: 300px"
                    >{{ script }}</pre
                >

                <!-- IP Address Input -->
                <div class="mb-4 rounded border border-gray-300 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-800">
                    <InputLabel for="ip_address" value="Router IP Address" class="mb-2" />
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                        Copy one of the IP addresses shown in the script output above and paste it here.
                    </p>
                    <div class="flex gap-2">
                        <TextInput
                            id="ip_address"
                            v-model="ipAddress"
                            type="text"
                            placeholder="e.g., 192.168.88.1 or 10.10.24.1/16"
                            class="flex-1"
                            :class="{ 'border-red-500': ipError }"
                            @keyup.enter="setIpAddress"
                        />
                        <PrimaryButton
                            @click="setIpAddress"
                            :disabled="settingIp || !ipAddress"
                        >
                            {{ settingIp ? 'Saving...' : 'Set IP' }}
                        </PrimaryButton>
                    </div>
                    <InputError :message="ipError" class="mt-2" />
                    <p v-if="ipAddress && !ipError" class="mt-2 text-sm text-green-600 dark:text-green-400">
                        ✓ IP Address set: {{ ipAddress }}
                    </p>
                </div>

                <!-- Polling Controls -->
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <PrimaryButton
                        @click="startStatusChecking"
                        :disabled="waiting || online || !hasIpAddress"
                    >
                        {{ waiting ? 'Checking status...' : "I've run the script" }}
                    </PrimaryButton>

                    <!-- Status Feedback -->
                    <template v-if="waiting">
                        <span class="flex items-center gap-2 text-blue-600">
                            <svg
                                class="h-5 w-5 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8v8z"
                                ></path>
                            </svg>
                            Checking router status... (waiting up to 5 minutes)
                        </span>
                    </template>

                    <template v-if="online">
                        <span
                            class="flex items-center gap-2 font-semibold text-green-600"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                            Router is online! You can continue.
                        </span>
                    </template>

                    <template v-if="pollingError">
                        <span class="text-red-600">{{ pollingError }}</span>
                    </template>
                </div>

                <!-- Proceed -->
                <div class="flex justify-end">
                    <PrimaryButton @click="proceed" :disabled="!online">
                        Next
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
