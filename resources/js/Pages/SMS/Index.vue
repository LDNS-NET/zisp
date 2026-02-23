<script setup>
import { ref, watch, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    MessageSquare, Search, Filter, Trash2, Send, 
    Users, MapPin, Box, CheckCircle, AlertCircle, 
    X, Phone, Calendar, Clock, RefreshCw, Smartphone,
    CreditCard, Coins, Zap
} from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { useToast } from 'vue-toastification';
import debounce from 'lodash/debounce';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    smsLogs: Object,
    filters: Object,
    templates: Array,
    packages: Array,
    locations: Array,
    sms_balance: [Number, String],
    is_using_system_gateway: Boolean,
});

const toast = useToast();

const showBuySmsModal = ref(false);
const buyAmount = ref(100);
const customAmount = ref('');
const showCustomAmount = ref(false);
const isInitializing = ref(false);

const handlePurchase = async () => {
    if (isInitializing.value) return;
    
    const finalAmount = showCustomAmount.value ? parseFloat(customAmount.value) : buyAmount.value;
    
    if (!finalAmount || finalAmount < 50) {
        toast.error('Minimum purchase amount is KES 50');
        return;
    }

    isInitializing.value = true;
    
    try {
        const response = await axios.post(route('sms.purchase.initialize'), { amount: finalAmount });
        if (response.data.success && response.data.authorization_url) {
            window.location.href = response.data.authorization_url;
        } else {
            toast.error(response.data.message || 'Failed to initialize payment');
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'An error occurred during payment initialization');
    } finally {
        isInitializing.value = false;
    }
};

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
    recipients: [], // Array of {id, full_name, phone} objects
    filters: {
        location: '',
        package_id: '',
        status: '', // 'active', 'expired', 'expiring_soon'
        search: '',
    },
    message: '',
});

// --- Manual Search State ---
const userSearchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showSearchResults = ref(false);

const searchUsers = debounce(async (query) => {
    isSearching.value = true;
    try {
        const response = await axios.get(route('sms.search-users'), { params: { q: query } });
        searchResults.value = response.data;
        showSearchResults.value = true;
    } catch (error) {
        console.error('Error searching users:', error);
    } finally {
        isSearching.value = false;
    }
}, 300);

watch(userSearchQuery, (val) => {
    searchUsers(val);
});

watch(composeMode, (newVal) => {
    if (newVal === 'manual' && searchResults.value.length === 0) {
        searchUsers('');
    }
});

const vClickOutside = {
    mounted(el, binding) {
        el.clickOutsideEvent = (event) => {
            if (!(el === event.target || el.contains(event.target))) {
                binding.value(event);
            }
        };
        document.addEventListener('click', el.clickOutsideEvent);
    },
    unmounted(el) {
        document.removeEventListener('click', el.clickOutsideEvent);
    },
};

const selectUser = (user) => {
    if (!form.recipients.find(r => r.id === user.id)) {
        form.recipients.push(user);
    }
    userSearchQuery.value = '';
    showSearchResults.value = false;
};

const removeRecipient = (index) => {
    form.recipients.splice(index, 1);
};


const resendForm = useForm({
    duration: '',
});

// --- Watchers ---
watch(search, debounce((value) => {
    router.get(route('sms.index'), { search: value }, { 
        preserveState: true, 
        preserveScroll: true, 
        replace: true 
    });
}, 500));

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
        // Transform recipients to IDs before sending
        form.recipients = form.recipients.map(r => r.id);
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

const resendFailed = (duration) => {
    if (!confirm(`Resend failed/stuck messages from the last ${duration}?`)) return;
    
    resendForm.duration = duration;
    resendForm.post(route('sms.resend-failed'), {
        onSuccess: () => {
            toast.success('Messages have been requeued for resending.');
        },
        onError: () => {
            toast.error('Failed to initiate resending.');
        }
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
                    <div v-if="is_using_system_gateway" class="relative overflow-hidden bg-gradient-to-br from-indigo-600 to-blue-700 p-5 rounded-2xl shadow-xl shadow-blue-500/20 flex flex-col justify-between min-h-[140px]">
                        <!-- Decorative background icon -->
                        <Smartphone class="absolute -right-4 -bottom-4 w-32 h-32 text-white/10 rotate-12 pointer-events-none" />
                        
                        <div class="relative z-10 flex justify-between items-start">
                            <div>
                                <p class="text-xs font-bold text-blue-100 uppercase tracking-widest opacity-80">Wallet Balance</p>
                                <h3 class="text-3xl font-black text-white mt-1">
                                    <span class="text-sm font-medium opacity-70 mr-1">KES</span>{{ Number(sms_balance).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </h3>
                            </div>
                            <div class="p-2 bg-white/20 backdrop-blur-md rounded-lg text-white">
                                <Coins class="w-5 h-5" />
                            </div>
                        </div>

                        <div class="relative z-10 mt-4">
                            <button 
                                @click="showBuySmsModal = true; showCustomAmount = true; customAmount = ''" 
                                class="w-full py-2 bg-white text-blue-700 text-xs font-bold rounded-xl shadow-lg hover:bg-blue-50 transition-all active:scale-95 flex items-center justify-center gap-2"
                            >
                                <Zap class="w-3.5 h-3.5 fill-current" />
                                Buy SMS Credits
                            </button>
                        </div>
                    </div>
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

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button class="flex items-center px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <RefreshCw class="w-4 h-4 mr-2" />
                                    Resend Failed
                                    <ChevronDown class="w-4 h-4 ml-2" />
                                </button>
                            </template>
                            <template #content>
                                <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                                    Select Duration
                                </div>
                                <DropdownLink @click="resendFailed('1h')" as="button">Last 1 Hour</DropdownLink>
                                <DropdownLink @click="resendFailed('3h')" as="button">Last 3 Hours</DropdownLink>
                                <DropdownLink @click="resendFailed('6h')" as="button">Last 6 Hours</DropdownLink>
                                <DropdownLink @click="resendFailed('12h')" as="button">Last 12 Hours</DropdownLink>
                                <DropdownLink @click="resendFailed('24h')" as="button">Last 24 Hours</DropdownLink>
                            </template>
                        </Dropdown>

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
                                                'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400': log.status === 'delivered',
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': log.status === 'pending',
                                                'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': log.status === 'failed'
                                            }"
                                        >
                                            <CheckCircle v-if="log.status === 'sent' || log.status === 'delivered'" class="w-3 h-3 mr-1" />
                                            <Clock v-if="log.status === 'pending'" class="w-3 h-3 mr-1" />
                                            <AlertCircle v-if="log.status === 'failed'" class="w-3 h-3 mr-1" />
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
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search Recipients</label>
                                <div class="relative">
                                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input 
                                        v-model="userSearchQuery"
                                        type="text" 
                                        placeholder="Search by name, username, or phone..." 
                                        class="w-full pl-10 pr-10 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 transition-all text-sm"
                                        @focus="showSearchResults = true"
                                    >
                                    <RefreshCw v-if="isSearching" class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-blue-500 animate-spin" />
                                    
                                    <!-- Search Results Dropdown -->
                                    <div v-if="showSearchResults && searchResults.length > 0" v-click-outside="() => showSearchResults = false" class="absolute z-[60] left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl overflow-hidden max-h-60 overflow-y-auto">
                                        <div 
                                            v-for="user in searchResults" 
                                            :key="user.id"
                                            @click="selectUser(user)"
                                            class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer flex justify-between items-center border-b border-gray-100 dark:border-gray-700 last:border-0"
                                        >
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ user.full_name }}</span>
                                                <span class="text-xs text-gray-500">@{{ user.username }}</span>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded-md">{{ user.phone }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else-if="userSearchQuery.length >= 2 && !isSearching && searchResults.length === 0" class="absolute z-[60] left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg p-4 text-center text-sm text-gray-500">
                                        No users found matching "{{ userSearchQuery }}"
                                    </div>
                                </div>
                            </div>

                            <!-- Selected Recipients -->
                            <div v-if="form.recipients.length > 0" class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Selected ({{ form.recipients.length }})</label>
                                    <button @click="form.recipients = []" class="text-xs text-red-600 hover:text-red-700 font-medium">Clear All</button>
                                </div>
                                <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto p-1">
                                    <div 
                                        v-for="(recipient, index) in form.recipients" 
                                        :key="recipient.id"
                                        class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded-full border border-blue-100 dark:border-blue-800/50 text-xs font-medium group transition-all"
                                    >
                                        <span>{{ recipient.full_name }}</span>
                                        <button @click="removeRecipient(index)" class="hover:text-red-600 transition-colors">
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="p-8 border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-2xl text-center">
                                <Users class="w-8 h-8 text-gray-300 mx-auto mb-2" />
                                <p class="text-sm text-gray-400">Search and select users to add them to your campaign.</p>
                            </div>
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
                        <div v-if="selectedSms.status === 'failed' && selectedSms.error_message">
                            <label class="text-xs uppercase text-red-500 font-bold">Error Message</label>
                            <p class="p-3 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg text-sm border border-red-100 dark:border-red-900/50 italic">
                                {{ selectedSms.error_message }}
                            </p>
                        </div>
                        <div v-if="selectedSms.provider_message_id">
                            <label class="text-xs uppercase text-gray-500 dark:text-gray-400">Provider Message ID</label>
                            <p class="text-sm font-mono text-gray-600 dark:text-gray-300">{{ selectedSms.provider_message_id }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button @click="showViewModal = false" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">Close</button>
                    </div>
                </div>
            </Modal>

            <!-- Buy SMS Credits Modal -->
            <Modal :show="showBuySmsModal" @close="showBuySmsModal = false" max-width="md">
                <div class="relative overflow-hidden bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700">
                    <!-- Techy Background Decoration -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full blur-3xl -mr-16 -mt-16"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl -ml-12 -mb-12"></div>
                    
                    <div class="p-8 relative">
                        <div class="flex flex-col items-center text-center mb-8">
                            <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center text-blue-600 dark:text-blue-400 mb-4 shadow-inner">
                                <Smartphone class="w-8 h-8" />
                            </div>
                            <h3 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Top Up Wallet</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Enter an amount to recharge your SMS credits.</p>
                        </div>
                        
                        <div class="space-y-6">
                            <!-- Amount Input -->
                            <div class="relative group mt-8">
                                <div class="absolute -top-3 left-4 px-2 bg-white dark:bg-gray-800 text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest z-10 transition-all group-focus-within:text-blue-500">
                                    Purchase Amount (KES)
                                </div>
                                <div class="flex items-center bg-gray-50 dark:bg-gray-900/50 rounded-2xl border-2 border-gray-100 dark:border-gray-800 p-2 transition-all group-focus-within:border-blue-500 group-focus-within:bg-white dark:group-focus-within:bg-gray-900 shadow-sm">
                                    <div class="pl-4 pr-2 text-2xl font-bold text-gray-400">KES</div>
                                    <input 
                                        v-model="customAmount"
                                        type="number"
                                        min="50"
                                        placeholder="0.00"
                                        class="w-full bg-transparent border-0 focus:ring-0 text-3xl font-black p-2 text-gray-900 dark:text-white placeholder-gray-200 dark:placeholder-gray-700"
                                        autofocus
                                    />
                                </div>
                                <p v-if="customAmount && customAmount < 50" class="text-[10px] font-bold text-red-500 mt-2 px-4 uppercase tracking-wider flex items-center gap-1">
                                    <AlertCircle class="w-3 h-3" /> Minimum amount is KES 50.00
                                </p>
                            </div>

                            <!-- Value Calculator -->
                            <div v-if="customAmount >= 50" class="bg-blue-50/50 dark:bg-blue-900/10 rounded-2xl p-4 border border-blue-100/50 dark:border-blue-800/20 animate-in fade-in slide-in-from-top-2 duration-300">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest leading-none">Estimated Volume</p>
                                        <div class="flex items-baseline gap-1 mt-1">
                                            <span class="text-2xl font-black text-blue-900 dark:text-blue-100">
                                                {{ Math.floor(customAmount / 0.39).toLocaleString() }}
                                            </span>
                                            <span class="text-xs font-bold text-blue-600/60 dark:text-blue-400/60 uppercase">SMS Units</span>
                                        </div>
                                    </div>
                                    <div class="h-10 w-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/30">
                                        <Send class="w-5 h-5" />
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-t border-blue-100 dark:border-blue-800/30 text-[10px] text-blue-600/70 dark:text-blue-400/60 font-medium">
                                    Billed at <span class="font-bold text-blue-700 dark:text-blue-300">KES 0.39</span> per 40 characters.
                                </div>
                            </div>
                        </div>

                        <!-- Info Footer -->
                        <div class="mt-8 flex items-center gap-3 px-2">
                             <div class="p-2 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 flex-shrink-0">
                                <CreditCard class="w-4 h-4" />
                            </div>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 leading-tight font-medium uppercase tracking-wider">
                                Secure payments powered by Paystack. Credits are added instantly and do not expire.
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-3 mt-8">
                            <button 
                                class="relative group w-full py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl shadow-xl shadow-blue-500/25 font-black transition-all duration-300 disabled:opacity-50 disabled:shadow-none flex items-center justify-center gap-2 overflow-hidden active:scale-[0.98]" 
                                :disabled="isInitializing || !customAmount || customAmount < 50"
                                @click="handlePurchase"
                            >
                                <span class="relative z-10 flex items-center gap-2">
                                    <RefreshCw v-if="isInitializing" class="w-5 h-5 animate-spin" />
                                    {{ isInitializing ? 'Initializing...' : 'Proceed to Checkout' }}
                                </span>
                                <!-- Glow effect on hover -->
                                <div class="absolute inset-0 bg-gradient-to-r from-blue-400/0 via-white/10 to-blue-400/0 -translate-x-full group-hover:animate-shimmer pointer-events-none"></div>
                            </button>
                            
                            <button 
                                @click="showBuySmsModal = false" 
                                class="w-full py-3 text-xs font-bold text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors uppercase tracking-widest"
                            >
                                Not now, cancel
                            </button>
                        </div>
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
