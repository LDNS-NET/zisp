<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import Modal from '@/Components/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import { 
    Router, Activity, Power, RefreshCw, Wifi, Key, 
    Clock, CheckCircle, XCircle, Loader, Trash2,
    ChevronDown, ChevronUp, User
} from 'lucide-vue-next'
import { useToast } from 'vue-toastification'

const toast = useToast()

const props = defineProps({
    device: Object,
    logs: Object,
    pendingActions: Array,
    subscribers: Array
})

// State
const showWifiModal = ref(false)
const showPPPoEModal = ref(false)
const showLinkSubscriberModal = ref(false)
const showRawParams = ref(false)
const isProcessing = ref(false)

// Forms
const wifiForm = useForm({
    ssid: '',
    password: ''
})

const pppoeForm = useForm({
    username: '',
    password: ''
})

const linkForm = useForm({
    subscriber_id: props.device.subscriber_id || ''
})

// Computed
const statusColor = computed(() => {
    return props.device.online 
        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
        : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
})

const actionStatusIcon = (status) => {
    const icons = {
        'pending': Loader,
        'sent': Loader,
        'completed': CheckCircle,
        'failed': XCircle
    }
    return icons[status] || Activity
}

const actionStatusColor = (status) => {
    const colors = {
        'pending': 'text-yellow-600 dark:text-yellow-400',
        'sent': 'text-blue-600 dark:text-blue-400',
        'completed': 'text-green-600 dark:text-green-400',
        'failed': 'text-red-600 dark:text-red-400'
    }
    return colors[status] || 'text-gray-600 dark:text-gray-400'
}

const formatDate = (date) => {
    if (!date) return 'Never'
    return new Date(date).toLocaleString()
}

const timeAgo = (date) => {
    if (!date) return 'Never'
    const seconds = Math.floor((new Date() - new Date(date)) / 1000)
    
    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    }
    
    for (const [unit, value] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / value)
        if (interval >= 1) {
            return `${interval} ${unit}${interval !== 1 ? 's' : ''} ago`
        }
    }
    return 'Just now'
}

// Actions
const queueAction = (action, payload = {}) => {
    isProcessing.value = true
    router.post(route('devices.queue-action', props.device.id), {
        action,
        payload
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`${action} action queued successfully`)
        },
        onError: () => {
            toast.error('Failed to queue action')
        },
        onFinish: () => {
            isProcessing.value = false
        }
    })
}

const rebootDevice = () => {
    if (confirm('Are you sure you want to reboot this device? This will temporarily disconnect the user.')) {
        queueAction('reboot')
    }
}

const factoryReset = () => {
    if (confirm('⚠️ WARNING: Factory reset will erase ALL device configuration. Are you absolutely sure?')) {
        if (confirm('This action cannot be undone. Click OK to proceed with factory reset.')) {
            queueAction('factory_reset')
        }
    }
}

const configureWiFi = () => {
    wifiForm.post(route('devices.queue-action', props.device.id), {
        preserveScroll: true,
        onSuccess: () => {
            showWifiModal.value = false
            wifiForm.reset()
            toast.success('WiFi configuration queued')
        }
    })
}

const configurePPPoE = () => {
    pppoeForm.post(route('devices.queue-action', props.device.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPPPoEModal.value = false
            pppoeForm.reset()
            toast.success('PPPoE configuration queued')
        }
    })
}

const linkSubscriber = () => {
    linkForm.post(route('devices.link-subscriber', props.device.id), {
        preserveScroll: true,
        onSuccess: () => {
            showLinkSubscriberModal.value = false
            toast.success('Device linked to subscriber')
        }
    })
}

const syncNow = () => {
    queueAction('sync_params')
}

const removeDevice = () => {
    if (confirm('Are you sure you want to remove this device from the system?')) {
        router.delete(route('devices.destroy', props.device.id), {
            onSuccess: () => {
                toast.success('Device removed')
            }
        })
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Device - ${device.serial_number}`" />

        <div class="max-w-7xl mx-auto p-6 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <Router class="h-8 w-8 text-blue-600" />
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ device.manufacturer }} {{ device.model }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">SN: {{ device.serial_number }}</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <span :class="['px-3 py-1.5 rounded-full text-sm font-medium', statusColor]">
                        {{ device.online ? 'Online' : 'Offline' }}
                    </span>
                    <SecondaryButton @click="syncNow" :disabled="isProcessing">
                        <RefreshCw :class="['h-4 w-4 mr-2', isProcessing ? 'animate-spin' : '']" />
                        Sync Now
                    </SecondaryButton>
                </div>
            </div>

            <!-- Device Info Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Firmware</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                        {{ device.firmware || device.software_version || 'Unknown' }}
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">WAN IP</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white mt-1 font-mono">
                        {{ device.wan_ip || 'N/A' }}
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">LAN IP</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white mt-1 font-mono">
                        {{ device.lan_ip || 'N/A' }}
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Last Contact</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                        {{ timeAgo(device.last_contact_at) }}
                    </div>
                </div>
            </div>

            <!-- Subscriber Info & Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Subscriber Panel -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <User class="h-5 w-5 text-gray-400" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Subscriber</h3>
                        </div>
                        <SecondaryButton @click="showLinkSubscriberModal = true" class="text-xs px-3 py-1">
                            {{ device.subscriber ? 'Change' : 'Link' }}
                        </SecondaryButton>
                    </div>
                    
                    <div v-if="device.subscriber" class="space-y-2">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ device.subscriber.name }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Account: {{ device.subscriber.account_number }}
                        </div>
                        <div v-if="device.subscriber.package" class="text-xs text-gray-500 dark:text-gray-400">
                            Plan: {{ device.subscriber.package.name }}
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 italic">
                        No subscriber linked
                    </div>
                </div>

                <!-- Quick Actions Panel -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <Activity class="h-5 w-5 text-gray-400" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Quick Actions</h3>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <button
                            @click="rebootDevice"
                            :disabled="isProcessing"
                            class="flex flex-col items-center gap-2 p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-blue-500 dark:hover:border-blue-500 transition-colors disabled:opacity-50"
                        >
                            <Power class="h-6 w-6 text-blue-600" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Reboot</span>
                        </button>
                        
                        <button
                            @click="factoryReset"
                            :disabled="isProcessing"
                            class="flex flex-col items-center gap-2 p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-red-500 dark:hover:border-red-500 transition-colors disabled:opacity-50"
                        >
                            <Trash2 class="h-6 w-6 text-red-600" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Reset</span>
                        </button>
                        
                        <button
                            @click="showWifiModal = true"
                            :disabled="isProcessing"
                            class="flex flex-col items-center gap-2 p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-green-500 dark:hover:border-green-500 transition-colors disabled:opacity-50"
                        >
                            <Wifi class="h-6 w-6 text-green-600" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">WiFi</span>
                        </button>
                        
                        <button
                            @click="showPPPoEModal = true"
                            :disabled="isProcessing"
                            class="flex flex-col items-center gap-2 p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 hover:border-purple-500 dark:hover:border-purple-500 transition-colors disabled:opacity-50"
                        >
                            <Key class="h-6 w-6 text-purple-600" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">PPPoE</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pending Actions -->
            <div v-if="pendingActions && pendingActions.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Active & Pending Actions</h3>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <div v-for="action in pendingActions" :key="action.id" class="px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <component 
                                :is="actionStatusIcon(action.status)" 
                                :class="['h-5 w-5', actionStatusColor(action.status), action.status === 'sent' || action.status === 'pending' ? 'animate-spin' : '']"
                            />
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white capitalize">
                                    {{ action.action.replace('_', ' ') }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ action.status }} • {{ formatDate(action.created_at) }}
                                </div>
                            </div>
                        </div>
                        <div v-if="action.error_message" class="text-xs text-red-600 dark:text-red-400 max-w-xs truncate">
                            {{ action.error_message }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <Clock class="h-5 w-5 text-gray-400" />
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Activity Log</h3>
                    </div>
                </div>
                <div class="p-6">
                    <div v-if="logs && logs.data && logs.data.length > 0" class="space-y-4">
                        <div v-for="log in logs.data" :key="log.id" class="flex gap-4">
                            <div class="flex-shrink-0">
                                <div :class="[
                                    'h-8 w-8 rounded-full flex items-center justify-center',
                                    log.log_type === 'success' ? 'bg-green-100 dark:bg-green-900/30' :
                                    log.log_type === 'error' ? 'bg-red-100 dark:bg-red-900/30' :
                                    log.log_type === 'warning' ? 'bg-yellow-100 dark:bg-yellow-900/30' :
                                    'bg-blue-100 dark:bg-blue-900/30'
                                ]">
                                    <Activity :class="[
                                        'h-4 w-4',
                                        log.log_type === 'success' ? 'text-green-600 dark:text-green-400' :
                                        log.log_type === 'error' ? 'text-red-600 dark:text-red-400' :
                                        log.log_type === 'warning' ? 'text-yellow-600 dark:text-yellow-400' :
                                        'text-blue-600 dark:text-blue-400'
                                    ]" />
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm text-gray-900 dark:text-white">{{ log.message }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ formatDate(log.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center text-gray-500 dark:text-gray-400 py-8">
                        No activity logs yet
                    </div>
                </div>
            </div>

            <!-- Raw Parameters (Collapsible) -->
            <div v-if="device.raw_parameters" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <button 
                    @click="showRawParams = !showRawParams"
                    class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                >
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Raw Device Parameters</h3>
                    <component :is="showRawParams ? ChevronUp : ChevronDown" class="h-5 w-5 text-gray-400" />
                </button>
                <div v-show="showRawParams" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-gray-900 dark:text-gray-100">{{ JSON.stringify(device.raw_parameters, null, 2) }}</pre>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
                <h3 class="text-lg font-medium text-red-900 dark:text-red-400 mb-2">Danger Zone</h3>
                <p class="text-sm text-red-700 dark:text-red-300 mb-4">
                    Removing this device will delete all associated logs and action history. This action cannot be undone.
                </p>
                <SecondaryButton @click="removeDevice" class="bg-red-600 hover:bg-red-700 text-white border-red-600">
                    <Trash2 class="h-4 w-4 mr-2" />
                    Remove Device
                </SecondaryButton>
            </div>
        </div>

        <!-- WiFi Configuration Modal -->
        <Modal :show="showWifiModal" @close="showWifiModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Configure WiFi</h2>
                <form @submit.prevent="configureWiFi" class="space-y-4">
                    <div>
                        <InputLabel for="ssid" value="SSID (Network Name)" />
                        <TextInput
                            id="ssid"
                            v-model="wifiForm.ssid"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="wifiForm.errors.ssid" class="mt-2" />
                    </div>
                    
                    <div>
                        <InputLabel for="wifi_password" value="Password" />
                        <TextInput
                            id="wifi_password"
                            v-model="wifiForm.password"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="wifiForm.errors.password" class="mt-2" />
                    </div>
                    
                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showWifiModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="wifiForm.processing">
                            Apply Configuration
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- PPPoE Configuration Modal -->
        <Modal :show="showPPPoEModal" @close="showPPPoEModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Configure PPPoE</h2>
                <form @submit.prevent="configurePPPoE" class="space-y-4">
                    <div>
                        <InputLabel for="pppoe_username" value="Username" />
                        <TextInput
                            id="pppoe_username"
                            v-model="pppoeForm.username"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="pppoeForm.errors.username" class="mt-2" />
                    </div>
                    
                    <div>
                        <InputLabel for="pppoe_password" value="Password" />
                        <TextInput
                            id="pppoe_password"
                            v-model="pppoeForm.password"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="pppoeForm.errors.password" class="mt-2" />
                    </div>
                    
                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showPPPoEModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="pppoeForm.processing">
                            Apply Configuration
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Link Subscriber Modal -->
        <Modal :show="showLinkSubscriberModal" @close="showLinkSubscriberModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Link to Subscriber</h2>
                <form @submit.prevent="linkSubscriber" class="space-y-4">
                    <div>
                        <InputLabel for="subscriber" value="Select Subscriber" />
                        <select
                            id="subscriber"
                            v-model="linkForm.subscriber_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            required
                        >
                            <option value="">-- Select Subscriber --</option>
                            <option v-for="subscriber in subscribers" :key="subscriber.id" :value="subscriber.id">
                                {{ subscriber.name }} ({{ subscriber.account_number }})
                            </option>
                        </select>
                        <InputError :message="linkForm.errors.subscriber_id" class="mt-2" />
                    </div>
                    
                    <div class="flex justify-end gap-3">
                        <SecondaryButton type="button" @click="showLinkSubscriberModal = false">Cancel</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="linkForm.processing">
                            Link Device
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
