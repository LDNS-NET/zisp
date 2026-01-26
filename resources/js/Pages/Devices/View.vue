<script setup>
import { ref } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import { 
    ChevronLeft, 
    Wifi, 
    Settings, 
    History, 
    Zap, 
    RefreshCcw, 
    Trash2,
    Cpu,
    Network,
    Clock,
    User
} from 'lucide-vue-next'

const props = defineProps({
    device: Object,
    subscribers: Array,
})

const activeTab = ref('overview')

const actionForm = useForm({
    action: '',
    payload: {},
})

const triggerAction = (action, payload = {}) => {
    if (!confirm(`Are you sure you want to trigger ${action}?`)) return
    
    actionForm.action = action
    actionForm.payload = payload
    actionForm.post(route('devices.action', props.device.id), {
        preserveScroll: true,
    })
}

const wifiForm = useForm({
    ssid: '',
    password: '',
})

const updateWifi = () => {
    triggerAction('update_wifi', {
        ssid: wifiForm.ssid,
        password: wifiForm.password
    })
}

const pppoeForm = useForm({
    username: '',
    password: '',
})

const updatePppoe = () => {
    triggerAction('update_pppoe', {
        username: pppoeForm.username,
        password: pppoeForm.password
    })
}

const subscriberForm = useForm({
    subscriber_id: props.device.subscriber_id,
})

const updateSubscriber = () => {
    subscriberForm.post(route('devices.link-subscriber', props.device.id), {
        preserveScroll: true,
    })
}

const getStatusColor = (online) => {
    return online ? 'text-green-500' : 'text-red-500';
}
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Device: ${device.serial_number}`" />

        <div class="max-w-7xl mx-auto p-6 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-4">
                    <Link 
                        :href="route('devices.index')"
                        class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors"
                    >
                        <ChevronLeft class="h-6 w-6 text-gray-500" />
                    </Link>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                            {{ device.manufacturer }} {{ device.model }}
                            <span :class="['w-3 h-3 rounded-full', device.online ? 'bg-green-500 animate-pulse' : 'bg-red-500']"></span>
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">SN: {{ device.serial_number }} | FW: {{ device.software_version || 'N/A' }}</p>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <SecondaryButton @click="triggerAction('reboot')" class="flex items-center gap-2">
                        <RefreshCcw class="h-4 w-4" /> Reboot
                    </SecondaryButton>
                    <DangerButton @click="triggerAction('reset')" class="flex items-center gap-2">
                        <Trash2 class="h-4 w-4" /> Factory Reset
                    </DangerButton>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex gap-8">
                    <button 
                        @click="activeTab = 'overview'"
                        :class="['pb-4 text-sm font-medium border-b-2 transition-colors', activeTab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']"
                    >
                        Overview
                    </button>
                    <button 
                        @click="activeTab = 'settings'"
                        :class="['pb-4 text-sm font-medium border-b-2 transition-colors', activeTab === 'settings' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']"
                    >
                        WiFi & PPPoE
                    </button>
                    <button 
                        @click="activeTab = 'logs'"
                        :class="['pb-4 text-sm font-medium border-b-2 transition-colors', activeTab === 'logs' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']"
                    >
                        Activity Logs
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="mt-6">
                <!-- Overview Tab -->
                <div v-if="activeTab === 'overview'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="col-span-1 md:col-span-2 space-y-6">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                                <Zap class="h-5 w-5 text-yellow-500" /> Device Vitality
                            </h3>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase tracking-wider">Status</p>
                                    <p :class="['text-sm font-bold', getStatusColor(device.online)]">
                                        {{ device.online ? 'Online (Connected to ACS)' : 'Offline' }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase tracking-wider">Last Interaction</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-2">
                                        <Clock class="h-4 w-4 text-gray-400" />
                                        {{ device.last_contact_at ? new Date(device.last_contact_at).toLocaleString() : 'Never' }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase tracking-wider">Subscriber</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-2">
                                        <User class="h-4 w-4 text-gray-400" />
                                        {{ device.subscriber?.name || 'Unassigned' }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase tracking-wider">Firmware Version</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white uppercase">{{ device.software_version || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                                <Network class="h-5 w-5 text-blue-500" /> Network Information
                            </h3>
                            <div class="grid grid-cols-2 gap-6 font-mono text-sm">
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase tracking-wider font-sans">WAN IP Address</p>
                                    <p class="text-gray-900 dark:text-white">{{ device.wan_ip || 'N/A' }}</p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase tracking-wider font-sans">LAN Gateway</p>
                                    <p class="text-gray-900 dark:text-white">{{ device.lan_ip || 'N/A' }}</p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs text-gray-500 uppercase tracking-wider font-sans">MAC Address</p>
                                    <p class="text-gray-900 dark:text-white uppercase">{{ device.mac_address || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl p-6 text-white shadow-lg">
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                                <Cpu class="h-5 w-5" /> Quick Actions
                            </h3>
                            <div class="grid grid-cols-1 gap-3">
                                <button 
                                    @click="triggerAction('reboot')"
                                    class="w-full text-left px-4 py-3 bg-white/10 hover:bg-white/20 rounded-lg transition-colors flex items-center justify-between group"
                                >
                                    <span>Soft Reboot</span>
                                    <RefreshCcw class="h-4 w-4 group-hover:rotate-180 transition-transform duration-500" />
                                </button>
                                <button 
                                    @click="triggerAction('sync_params')"
                                    class="w-full text-left px-4 py-3 bg-white/10 hover:bg-white/20 rounded-lg transition-colors flex items-center justify-between group"
                                >
                                    <span>Sync Parameters</span>
                                    <History class="h-4 w-4 group-hover:scale-110 transition-transform font-bold" />
                                </button>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">Assign Subscriber</h4>
                            <div class="space-y-4">
                                <select 
                                    v-model="subscriberForm.subscriber_id"
                                    class="w-full px-3 py-2 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 rounded-lg text-sm dark:text-white focus:ring-blue-500"
                                >
                                    <option :value="null">Unassigned</option>
                                    <option v-for="sub in subscribers" :key="sub.id" :value="sub.id">
                                        {{ sub.full_name }} ({{ sub.account_number }})
                                    </option>
                                </select>
                                <PrimaryButton @click="updateSubscriber" :disabled="subscriberForm.processing" class="w-full justify-center text-xs">
                                    {{ subscriberForm.processing ? 'Linking...' : 'Update Assignment' }}
                                </PrimaryButton>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-800 mt-6">
                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">Device Timeline</h4>
                            <div class="space-y-4">
                                <div v-for="log in device.logs.slice(0, 3)" :key="log.id" class="flex gap-3">
                                    <div class="w-1.5 h-1.5 rounded-full mt-1.5 bg-blue-500"></div>
                                    <div>
                                        <p class="text-xs text-gray-900 dark:text-white leading-tight">{{ log.message }}</p>
                                        <p class="text-[10px] text-gray-500 mt-1 uppercase">{{ new Date(log.created_at).toLocaleString() }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div v-if="activeTab === 'settings'" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- WiFi Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                                <Wifi class="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">WiFi Configuration</h3>
                                <p class="text-sm text-gray-500">Update SSID and WPA2 security</p>
                            </div>
                        </div>

                        <form @submit.prevent="updateWifi" class="space-y-6">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-500 uppercase">WiFi Name (SSID)</label>
                                <input 
                                    v-model="wifiForm.ssid" 
                                    type="text" 
                                    class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-blue-500 dark:text-white"
                                    placeholder="Enter new SSID"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-500 uppercase">WiFi Password</label>
                                <input 
                                    v-model="wifiForm.password" 
                                    type="password" 
                                    class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-blue-500 dark:text-white"
                                    placeholder="Min 8 characters"
                                />
                            </div>
                            <PrimaryButton :disabled="wifiForm.processing" class="w-full justify-center py-3">
                                Push WiFi Setup
                            </PrimaryButton>
                        </form>
                    </div>

                    <!-- PPPoE Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                                <Settings class="h-6 w-6 text-indigo-600" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">WAN / PPPoE Setup</h3>
                                <p class="text-sm text-gray-500">Remote provision internet credentials</p>
                            </div>
                        </div>

                        <form @submit.prevent="updatePppoe" class="space-y-6">
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-500 uppercase">PPPoE Username</label>
                                <input 
                                    v-model="pppoeForm.username" 
                                    type="text" 
                                    class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-blue-500 dark:text-white"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-bold text-gray-500 uppercase">PPPoE Password</label>
                                <input 
                                    v-model="pppoeForm.password" 
                                    type="password" 
                                    class="block w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 border-none rounded-lg focus:ring-2 focus:ring-blue-500 dark:text-white"
                                />
                            </div>
                            <PrimaryButton :disabled="pppoeForm.processing" class="w-full justify-center py-3 bg-indigo-600 hover:bg-indigo-700">
                                Apply PPPoE Credentials
                            </PrimaryButton>
                        </form>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div v-if="activeTab === 'logs'" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Date & Time</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase">Message</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="log in device.logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 font-mono">
                                    {{ new Date(log.created_at).toLocaleString() }}
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="[
                                        'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                                        log.log_type === 'error' ? 'bg-red-100 text-red-700' : 
                                        log.log_type === 'warning' ? 'bg-yellow-100 text-yellow-700' : 
                                        'bg-blue-100 text-blue-700'
                                    ]">
                                        {{ log.log_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    {{ log.message }}
                                </td>
                            </tr>
                            <tr v-if="device.logs.length === 0">
                                <td colspan="3" class="px-6 py-10 text-center text-gray-500 italic">No activity logs recorded for this device.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
