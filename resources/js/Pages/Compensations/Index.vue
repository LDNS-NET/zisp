<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { 
    Search, Filter, Plus, Clock, Users, MapPin, 
    Router, Calendar, ChevronRight, CheckCircle2,
    AlertCircle, History
} from 'lucide-vue-next';
import debounce from 'lodash/debounce';

const props = defineProps({
    users: Object,
    compensations: Object,
    locations: Array,
    routers: Array,
    filters: Object,
});

const search = ref(props.filters.search || '');
const location = ref(props.filters.location || '');
const routerId = ref(props.filters.router_id || '');
const showCompensateModal = ref(false);
const selectedUsers = ref([]);
const activeTab = ref('users'); // 'users' or 'history'

const compensateForm = useForm({
    user_ids: [],
    duration_value: 1,
    duration_unit: 'days',
    reason: '',
});

const updateFilters = debounce(() => {
    router.get(route('compensations.index'), {
        search: search.value,
        location: location.value,
        router_id: routerId.value,
    }, {
        preserveState: true,
        replace: true,
    });
}, 500);

watch([search, location, routerId], () => {
    updateFilters();
});

const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedUsers.value = props.users.data.map(u => u.id);
    } else {
        selectedUsers.value = [];
    }
};

const toggleSelectUser = (id) => {
    const index = selectedUsers.value.indexOf(id);
    if (index === -1) {
        selectedUsers.value.push(id);
    } else {
        selectedUsers.value.splice(index, 1);
    }
};

const openCompensateModal = () => {
    if (selectedUsers.value.length === 0) return;
    compensateForm.user_ids = selectedUsers.value;
    showCompensateModal.value = true;
};

const submitCompensation = () => {
    compensateForm.post(route('compensations.store'), {
        onSuccess: () => {
            showCompensateModal.value = false;
            selectedUsers.value = [];
            compensateForm.reset();
        }
    });
};

const getStatusColor = (status) => {
    switch (status) {
        case 'active': return 'text-green-500 bg-green-500/10';
        case 'suspended': return 'text-red-500 bg-red-500/10';
        case 'expired': return 'text-amber-500 bg-amber-500/10';
        default: return 'text-slate-500 bg-slate-500/10';
    }
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="User Compensations" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">User Compensations</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-1">Add duration to users or compensate for downtime</p>
                </div>
                
                <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg self-start md:self-auto">
                    <button 
                        @click="activeTab = 'users'"
                        :class="[
                            'px-4 py-2 rounded-md transition-all text-sm font-medium flex items-center gap-2',
                            activeTab === 'users' ? 'bg-white dark:bg-slate-700 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                        ]"
                    >
                        <Users class="w-4 h-4" />
                        Manage Users
                    </button>
                    <button 
                        @click="activeTab = 'history'"
                        :class="[
                            'px-4 py-2 rounded-md transition-all text-sm font-medium flex items-center gap-2',
                            activeTab === 'history' ? 'bg-white dark:bg-slate-700 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                        ]"
                    >
                        <History class="w-4 h-4" />
                        Compensation History
                    </button>
                </div>
            </div>

            <template v-if="activeTab === 'users'">
                <!-- Filters Section -->
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <input 
                                v-model="search"
                                type="text"
                                placeholder="Search Name, Account, Phone..."
                                class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white placeholder:text-slate-400"
                            />
                        </div>

                        <div class="relative">
                            <MapPin class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <select 
                                v-model="location"
                                class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            >
                                <option value="">All Locations</option>
                                <option v-for="loc in locations" :key="loc" :value="loc">{{ loc }}</option>
                            </select>
                        </div>

                        <div class="relative">
                            <Router class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                            <select 
                                v-model="routerId"
                                class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            >
                                <option value="">All Mikrotiks</option>
                                <option v-for="router in routers" :key="router.id" :value="router.id">{{ router.name }}</option>
                            </select>
                        </div>

                        <button 
                            @click="openCompensateModal"
                            :disabled="selectedUsers.length === 0"
                            class="flex items-center justify-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition-colors shadow-lg shadow-primary-500/20"
                        >
                            <Clock class="w-4 h-4" />
                            Add Time ({{ selectedUsers.length }})
                        </button>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                                    <th class="px-6 py-4">
                                        <input type="checkbox" @change="toggleSelectAll" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 cursor-pointer" />
                                    </th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Name / Account</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Package</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Location</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Status</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Expiry</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <input 
                                            type="checkbox" 
                                            :checked="selectedUsers.includes(user.id)"
                                            @change="toggleSelectUser(user.id)"
                                            class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 cursor-pointer" 
                                        />
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">
                                        <div class="font-bold">{{ user.full_name }}</div>
                                        <div class="text-xs text-slate-500">ACC: {{ user.account_number }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ user.package_name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ user.location || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span :class="['px-2.5 py-1 rounded-full text-xs font-semibold capitalize', getStatusColor(user.status)]">
                                            {{ user.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ formatDate(user.expires_at) }}
                                    </td>
                                </tr>
                                <tr v-if="users.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                        No users found matching your filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination Placeholder - Add proper pagination component if available -->
            </template>

            <template v-else>
                <!-- History Table -->
                <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Date</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">User</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Duration</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Old Expiry</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">New Expiry</th>
                                    <th class="px-6 py-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Compensated By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="comp in compensations.data" :key="comp.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ formatDate(comp.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">
                                        {{ comp.user?.full_name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ comp.duration_value }} {{ comp.duration_unit }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ formatDate(comp.old_expires_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ formatDate(comp.new_expires_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ comp.creator?.name || 'System' }}
                                    </td>
                                </tr>
                                <tr v-if="compensations.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                        No compensation history found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>

        <!-- Add Compensation Modal -->
        <div v-if="showCompensateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 dark:border-slate-800 overflow-hidden transform transition-all animate-in zoom-in-95 duration-200">
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-primary-50 dark:bg-primary-900/10">
                    <div class="flex items-center gap-3 font-semibold text-slate-900 dark:text-white">
                        <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400">
                            <Clock class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="block">Add Time</span>
                            <span class="text-xs font-normal text-slate-500 dark:text-slate-400">Applying to {{ selectedUsers.length }} users</span>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2 text-left">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300 px-1">Value</label>
                            <input 
                                v-model="compensateForm.duration_value"
                                type="number"
                                min="1"
                                class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            />
                            <p v-if="compensateForm.errors.duration_value" class="text-xs text-red-500 mt-1">{{ compensateForm.errors.duration_value }}</p>
                        </div>
                        <div class="space-y-2 text-left">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300 px-1">Unit</label>
                            <select 
                                v-model="compensateForm.duration_unit"
                                class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                            >
                                <option value="minutes">Minutes</option>
                                <option value="hours">Hours</option>
                                <option value="days">Days</option>
                                <option value="weeks">Weeks</option>
                                <option value="months">Months</option>
                            </select>
                            <p v-if="compensateForm.errors.duration_unit" class="text-xs text-red-500 mt-1">{{ compensateForm.errors.duration_unit }}</p>
                        </div>
                    </div>

                    <div class="space-y-2 text-left">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300 px-1">Reason (Optional)</label>
                        <textarea 
                            v-model="compensateForm.reason"
                            rows="3"
                            placeholder="e.g. Compensation for network outage..."
                            class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-primary-500 text-slate-900 dark:text-white"
                        ></textarea>
                    </div>

                    <div class="bg-amber-50 dark:bg-amber-900/10 p-4 rounded-xl border border-amber-100 dark:border-amber-900/20 flex gap-3 text-left">
                        <AlertCircle class="w-5 h-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" />
                        <p class="text-xs text-amber-700 dark:text-amber-400">
                            Time will be added to the current expiry date if users are active. For expired users, time starts from current time.
                        </p>
                    </div>
                </div>

                <div class="p-6 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                    <button 
                        @click="showCompensateModal = false"
                        class="px-5 py-2.5 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-sm font-medium transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="submitCompensation"
                        :disabled="compensateForm.processing"
                        class="px-8 py-2.5 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg shadow-primary-500/30 disabled:opacity-50 transition-all hover:-translate-y-0.5"
                    >
                        <Plus v-if="!compensateForm.processing" class="w-4 h-4" />
                        {{ compensateForm.processing ? 'Processing...' : 'Apply Compensation' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Any custom styles localized to this page */
</style>
