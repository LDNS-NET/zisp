<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    MessageSquare, Search, Filter, Trash2, Send, 
    Users, MapPin, Box, CheckCircle, AlertCircle, 
    X, Phone, Calendar, Clock, RefreshCw, Smartphone
} from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';
import debounce from 'lodash/debounce';

const props = defineProps({
    smsLogs: Object,
    filters: Object,
    renters: Array,
    templates: Array,
    packages: Array,
    locations: Array,
});

const toast = useToast();

// --- State ---
const search = ref(props.filters?.search || '');
const showCompose = ref(false);
const showViewModal = ref(false);
const selectedSms = ref(null);
const selectedLogs = ref([]);
const selectAllLogs = ref(false);

// Filter State for Logs
const logFilters = ref({
    status: '',
    date: '',
});

// Compose Form State
const composeMode = ref('filter'); // 'filter' or 'manual'
const recipientCount = ref(0);
const isCalculating = ref(false);

const availableVariables = [
    { label: 'Full Name', value: '{full_name}' },
    { label: 'Phone', value: '{phone}' },
    { label: 'Account No.', value: '{account_number}' },
    { label: 'Expiry Date', value: '{expiry_date}' },
    { label: 'Package Name', value: '{package}' },
    { label: 'Username', value: '{username}' },
    { label: 'Password', value: '{password}' },
    { label: 'Support No.', value: '{support_number}' },
];

const form = useForm({
    recipients: [], // For manual selection
    filters: {
        location: '',
        package_id: '',
        status: '', // 'active', 'expired', 'expiring_soon'
        search: '',
    },
    message: '',
});

// --- Watchers ---
watch(search, debounce((value) => {
    router.get(route('sms.index'), { search: value }, { 
        preserveState: true, 
        preserveScroll: true, 
        replace: true 
    });
}, 300));

watch(selectAllLogs, (val) => {
    if (val) {
        selectedLogs.value = props.smsLogs.data.map(log => log.id);
    } else {
        selectedLogs.value = [];
    }
});

// Watch compose filters to update recipient count
watch(() => form.filters, debounce(async (newFilters) => {
    if (composeMode.value !== 'filter') return;
    
    isCalculating.value = true;
    try {
        const response = await axios.get(route('sms.count'), { params: newFilters });
        recipientCount.value = response.data.count;
    } catch (error) {
        console.error('Error counting recipients:', error);
    } finally {
        isCalculating.value = false;
    }
}, 500), { deep: true });

// --- Actions ---

const openCompose = () => {
    form.reset();
    showCompose.value = true;
    // Trigger initial count
    if (composeMode.value === 'filter') {
        form.filters = { location: '', package_id: '', status: '', search: '' }; // Trigger watcher
    }
};

const closeCompose = () => {
    showCompose.value = false;
};

const sendSms = () => {
    // If manual mode, clear filters
    if (composeMode.value === 'manual') {
        form.filters = null;
    } else {
        form.recipients = null;
    }

    form.post(route('sms.store'), {
        onSuccess: () => {
            closeCompose();
            toast.success('Campaign started successfully!');
        },
        onError: () => {
            toast.error('Failed to start campaign. Please check inputs.');
        },
    });
};

const applyTemplate = (content) => {
    form.message = content;
};

const deleteSelected = () => {
    if (!confirm(`Delete ${selectedLogs.value.length} logs?`)) return;
    
    router.delete(route('sms.bulk-delete'), {
        data: { ids: selectedLogs.value },
        onSuccess: () => {
            selectedLogs.value = [];
            selectAllLogs.value = false;
            toast.success('Logs deleted successfully');
        }
    });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

// Start calc on mount if needed, or wait for open
</script>

<template>
    <Head title="SMS Dashboard" />

    <AuthenticatedLayout>
        <div class="h-[calc(100vh-65px)] flex flex-col md:flex-row overflow-hidden bg-gray-50 dark:bg-gray-900">
            
            <!-- Sidebar / Filter Panel (Desktop) -->
            <!-- We keep it simple: Main Content + Slide-over for Compose -->

            <!-- Main Content: Logs & Stats -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                <!-- Header Stats -->
                <div class="p-6 pb-0 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Sent</p>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ smsLogs.total }}</h3>
                        </div>
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-blue-600 dark:text-blue-400">
                            <Send class="w-6 h-6" />
                        </div>
                    </div>
                    <!-- Add more stats here if available (Balance, Failed, etc) -->
                </div>

                <!-- Toolbar -->
                <div class="p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="relative w-full sm:w-80">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input 
                            v-model="search"
                            type="text" 
                            placeholder="Search logs..." 
                            class="w-full pl-10 pr-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        >
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <button 
                            v-if="selectedLogs.length > 0"
                            @click="deleteSelected"
                            class="flex items-center px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition-colors border border-red-100 text-sm font-medium"
                        >
                            <Trash2 class="w-4 h-4 mr-2" />
                            Delete ({{ selectedLogs.length }})
                        </button>

                        <button 
                            @click="openCompose"
                            class="flex-1 sm:flex-none flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-lg hover:shadow-xl transition-all font-medium"
                        >
                            <MessageSquare class="w-4 h-4 mr-2" />
                            New Campaign
                        </button>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="flex-1 overflow-auto px-6 pb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left">
                                        <input 
                                            type="checkbox" 
                                            v-model="selectAllLogs"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipient</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Message</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sent At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="log in smsLogs.data" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer" @click="selectedSms = log; showViewModal = true">
                                    <td class="px-6 py-4" @click.stop>
                                        <input 
                                            type="checkbox" 
                                            v-model="selectedLogs"
                                            :value="log.id"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600"
                                        >
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ log.recipient_name || 'Unknown' }}</span>
                                            <span class="text-xs text-gray-500">{{ log.phone_number }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-1 max-w-md">{{ log.message }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                            :class="{
                                                'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': log.status === 'sent',
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': log.status === 'pending',
                                                'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': log.status === 'failed'
                                            }"
                                        >
                                            {{ log.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(log.created_at) }}
                                    </td>
                                </tr>
                                <tr v-if="smsLogs.data.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        No SMS logs found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <Pagination :links="smsLogs.links" />
                    </div>
                </div>
            </div>

            <!-- Slide-over Compose Window -->
            <transition 
                enter-active-class="transform transition ease-in-out duration-300 sm:duration-500" 
                enter-from-class="translate-x-full" 
                enter-to-class="translate-x-0" 
                leave-active-class="transform transition ease-in-out duration-300 sm:duration-500" 
                leave-from-class="translate-x-0" 
                leave-to-class="translate-x-full"
            >
                <div v-if="showCompose" class="fixed inset-y-0 right-0 max-w-2xl w-full bg-white dark:bg-gray-800 shadow-2xl z-50 flex flex-col border-l border-gray-200 dark:border-gray-700">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-900">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <MessageSquare class="w-5 h-5 text-blue-600" />
                            Compose Campaign
                        </h2>
                        <button @click="closeCompose" class="p-2 text-gray-400 hover:text-gray-600 bg-white dark:bg-gray-800 rounded-full shadow-sm border border-gray-200 dark:border-gray-700">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        
                        <!-- Mode Selection -->
                        <div class="p-1 bg-gray-100 dark:bg-gray-700 rounded-lg flex space-x-1">
                            <button 
                                @click="composeMode = 'filter'" 
                                class="flex-1 py-2 text-sm font-medium rounded-md transition-all flex items-center justify-center gap-2"
                                :class="composeMode === 'filter' ? 'bg-white dark:bg-gray-600 shadow text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                            >
                                <Filter class="w-4 h-4" />
                                Smart Filters
                            </button>
                            <button 
                                @click="composeMode = 'manual'" 
                                class="flex-1 py-2 text-sm font-medium rounded-md transition-all flex items-center justify-center gap-2"
                                :class="composeMode === 'manual' ? 'bg-white dark:bg-gray-600 shadow text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                            >
                                <Users class="w-4 h-4" />
                                Manual Selection
                            </button>
                        </div>

                        <!-- Smart Filters Section -->
                        <div v-show="composeMode === 'filter'" class="space-y-4 animate-in fade-in slide-in-from-top-2 duration-300">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        <MapPin class="w-3 h-3 inline mr-1" /> Location / Building
                                    </label>
                                    <select v-model="form.filters.location" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                        <option value="">All Locations</option>
                                        <option v-for="loc in locations" :key="loc" :value="loc">{{ loc }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        <Box class="w-3 h-3 inline mr-1" /> Package
                                    </label>
                                    <select v-model="form.filters.package_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                                        <option value="">All Packages</option>
                                        <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User Status</label>
                                <div class="flex flex-wrap gap-2">
                                    <button 
                                        type="button"
                                        @click="form.filters.status = form.filters.status === 'active' ? '' : 'active'" 
                                        class="px-3 py-1.5 rounded-full border text-sm font-medium transition-all"
                                        :class="form.filters.status === 'active' ? 'bg-green-100 border-green-200 text-green-700' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'"
                                    >Active Users</button>
                                    <button 
                                        type="button"
                                        @click="form.filters.status = form.filters.status === 'expired' ? '' : 'expired'" 
                                        class="px-3 py-1.5 rounded-full border text-sm font-medium transition-all"
                                        :class="form.filters.status === 'expired' ? 'bg-red-100 border-red-200 text-red-700' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'"
                                    >Expired Users</button>
                                    <button 
                                        type="button"
                                        @click="form.filters.status = form.filters.status === 'expiring_soon' ? '' : 'expiring_soon'" 
                                        class="px-3 py-1.5 rounded-full border text-sm font-medium transition-all"
                                        :class="form.filters.status === 'expiring_soon' ? 'bg-yellow-100 border-yellow-200 text-yellow-700' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'"
                                    >Expiring Soon (3 days)</button>
                                </div>
                            </div>

                            <!-- Live Filter Results -->
                            <div class="bg-blue-50 dark:bg-blue-900/10 p-4 rounded-xl border border-blue-100 dark:border-blue-800 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-blue-100 dark:bg-blue-800 rounded-lg text-blue-600 dark:text-blue-300">
                                        <RefreshCw v-if="isCalculating" class="w-5 h-5 animate-spin" />
                                        <Users v-else class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-blue-900 dark:text-blue-100">Target Audience</p>
                                        <p class="text-xs text-blue-600 dark:text-blue-300">Based on current filters</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ recipientCount }}</p>
                                    <p class="text-xs text-blue-600 dark:text-blue-300">Recipients</p>
                                </div>
                            </div>

                        </div>

                        <!-- Manual Selection Section -->
                        <div v-show="composeMode === 'manual'" class="space-y-4 animate-in fade-in slide-in-from-top-2 duration-300">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Recipients</label>
                            <select 
                                v-model="form.recipients" 
                                multiple 
                                class="w-full h-48 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm"
                            >
                                <option v-for="r in renters" :key="r.id" :value="r.id">
                                    {{ r.full_name }} ({{ r.phone }})
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 text-right">Hold Ctrl/Cmd to select multiple</p>
                        </div>

                            <div class="space-y-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                                
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message Content</label>
                                    <select @change="e => applyTemplate(e.target.value)" class="text-xs rounded-lg border-gray-200 dark:border-gray-700 py-1 pl-2 pr-8 bg-gray-50 dark:bg-gray-800 dark:text-gray-200">
                                        <option value="">✨ Load Template...</option>
                                        <option v-for="t in templates" :key="t.id" :value="t.content">{{ t.name }}</option>
                                    </select>
                                </div>

                                <!-- Variables Toolbar -->
                                <div class="flex flex-wrap gap-1.5 p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                    <span class="text-xs text-gray-400 font-medium px-1 flex items-center">Insert:</span>
                                    <button 
                                        v-for="v in availableVariables" 
                                        :key="v.value"
                                        type="button"
                                        @click="form.message += v.value"
                                        class="px-2 py-1 bg-white dark:bg-gray-600 border border-gray-200 dark:border-gray-500 rounded text-xs font-medium text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-300 transition-colors shadow-sm"
                                        :title="'Insert ' + v.value"
                                    >
                                        {{ v.label }}
                                    </button>
                                </div>

                                <textarea 
                                    v-model="form.message" 
                                    rows="6" 
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 focus:ring-blue-500 focus:border-blue-500 shadow-sm font-mono text-sm"
                                    placeholder="Type your message here..."
                                ></textarea>

                                <div class="flex justify-between items-start">
                                    <p class="text-xs text-gray-500 italic">
                                        Variables will be replaced with actual user data upon sending.
                                    </p>
                                    <div class="text-xs text-right" :class="form.message.length > 160 ? 'text-orange-500' : 'text-gray-500'">
                                        {{ form.message.length }} chars 
                                        <span v-if="form.message.length > 0">({{ Math.ceil(form.message.length / 160) }} segments)</span>
                                    </div>
                                </div>
                            </div>

                    </div>

                    <!-- Footer -->
                    <div class="p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex justify-between items-center">
                        <button @click="closeCompose" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-sm font-medium">Cancel</button>
                        <button 
                            @click="sendSms" 
                            :disabled="form.processing || (composeMode === 'manual' && !form.recipients.length) || (composeMode === 'filter' && recipientCount === 0)"
                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-lg hover:shadow-xl transition-all font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            <Send class="w-4 h-4" />
                            Send Campaign
                        </button>
                    </div>
                </div>
            </transition>
            
            <!-- Backdrop -->
            <div 
                v-if="showCompose" 
                @click="closeCompose" 
                class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 transition-opacity"
            ></div>

            <!-- View Modal (Reuse existing Modal component) -->
            <Modal :show="showViewModal" @close="showViewModal = false">
                <div class="p-6 dark:bg-gray-800 dark:text-white" v-if="selectedSms">
                    <h3 class="text-lg font-bold mb-4">Message Details</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs uppercase text-gray-500 dark:text-gray-400">Recipient</label>
                            <p class="font-medium">{{ selectedSms.recipient_name }} ({{ selectedSms.phone_number }})</p>
                        </div>
                        <div>
                            <label class="text-xs uppercase text-gray-500 dark:text-gray-400">Message</label>
                            <p class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-sm">{{ selectedSms.message }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button @click="showViewModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Close</button>
                    </div>
                </div>
            </Modal>

        </div>
    </AuthenticatedLayout>
</template>
