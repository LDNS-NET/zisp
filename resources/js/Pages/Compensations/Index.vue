<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { 
    Search, Filter, Plus, Clock, Users, MapPin, 
    Router as RouterIcon, Calendar, ChevronRight, CheckCircle2,
    AlertCircle, History, TrendingUp, UserMinus, ShieldCheck,
    ArrowUpRight, MoreHorizontal, ListFilter, X, ChevronDown
} from 'lucide-vue-next';
import debounce from 'lodash/debounce';

const props = defineProps({
    users: Object,
    compensations: Object,
    locations: Array,
    routers: Array,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters.search || '');
const location = ref(props.filters.location || '');
const routerId = ref(props.filters.router_id || '');
const showCompensateModal = ref(false);
const selectedUsers = ref([]);
const activeTab = ref('users');
const isBulkMode = ref(false);

const compensateForm = useForm({
    user_ids: [],
    duration_value: 1,
    duration_unit: 'days',
    reason: '',
    apply_to_all: false,
    search: '',
    location: '',
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

const openCompensateModal = (bulk = false) => {
    isBulkMode.value = bulk;
    if (bulk) {
        compensateForm.user_ids = [];
        compensateForm.apply_to_all = true;
        compensateForm.search = search.value;
        compensateForm.location = location.value;
    } else {
        if (selectedUsers.value.length === 0) return;
        compensateForm.user_ids = selectedUsers.value;
        compensateForm.apply_to_all = false;
        compensateForm.search = '';
        compensateForm.location = '';
    }
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
        case 'active': return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 border-emerald-100 dark:border-emerald-500/20';
        case 'suspended': return 'text-rose-600 bg-rose-50 dark:bg-rose-500/10 border-rose-100 dark:border-rose-500/20';
        case 'expired': return 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 border-amber-100 dark:border-amber-500/20';
        default: return 'text-slate-600 bg-slate-50 dark:bg-slate-500/10 border-slate-100 dark:border-slate-500/20';
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
        <div class="min-h-screen bg-slate-50/50 dark:bg-slate-950/50 py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header with Glassmorphism Effect -->
                <div class="relative mb-8 p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl shadow-slate-200/50 dark:shadow-none overflow-hidden">
                    <div class="absolute top-0 right-0 -mt-10 -mr-10 w-64 h-64 bg-primary-500/5 rounded-full blur-3xl"></div>
                    <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-48 h-48 bg-emerald-500/5 rounded-full blur-3xl"></div>
                    
                    <div class="relative flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                                    <Clock class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">User Compensations</h1>
                            </div>
                            <p class="text-lg text-slate-500 dark:text-slate-400 font-medium">Manage duration extensions and network downtime recovery</p>
                        </div>
                        
                        <div class="flex items-center gap-2 p-1.5 bg-slate-100/50 dark:bg-slate-800/50 rounded-2xl border border-slate-200/50 dark:border-slate-700/50 backdrop-blur-sm shadow-inner overflow-hidden">
                            <button 
                                @click="activeTab = 'users'"
                                :class="[
                                    'px-6 py-2.5 rounded-xl transition-all duration-300 text-sm font-bold flex items-center gap-2.5',
                                    activeTab === 'users' 
                                        ? 'bg-white dark:bg-slate-700 text-primary dark:text-primary' 
                                        : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                                ]"
                            >
                                <Users class="w-4.5 h-4.5" />
                                Manage Users
                            </button>
                            <button 
                                @click="activeTab = 'history'"
                                :class="[
                                    'px-6 py-2.5 rounded-xl transition-all duration-300 text-sm font-bold flex items-center gap-2.5',
                                    activeTab === 'history' 
                                        ? 'bg-white dark:bg-slate-700 text-primary dark:text-primary' 
                                        : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                                ]"
                            >
                                <History class="w-4.5 h-4.5" />
                                Activity Log
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Strategic Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Users -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:hover:shadow-none group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-2xl group-hover:scale-110 transition-transform duration-300">
                                <Users class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded-lg">Total Users</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-white">{{ stats.total_users }}</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">All network accounts</div>
                    </div>

                    <!-- Suspended Users -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:hover:shadow-none group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-rose-50 dark:bg-rose-900/20 rounded-2xl group-hover:scale-110 transition-transform duration-300">
                                <UserMinus class="w-6 h-6 text-rose-600 dark:text-rose-400" />
                            </div>
                            <span class="text-xs font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/20 px-2 py-1 rounded-lg">Suspended</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-white">{{ stats.suspended_users }}</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium italic underline decoration-rose-200 dark:decoration-rose-900 underline-offset-4">Needs action</div>
                    </div>

                    <!-- Total Compensations -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:hover:shadow-none group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-2xl group-hover:scale-110 transition-transform duration-300">
                                <ShieldCheck class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-lg">All Time</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-white">{{ stats.total_compensations }}</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">Compensations issued</div>
                    </div>

                    <!-- Today's Impact -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:hover:shadow-none group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-2xl group-hover:scale-110 transition-transform duration-300">
                                <TrendingUp class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                            </div>
                            <span class="text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded-lg">Today</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-white">{{ stats.today_compensations }}</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-medium">Updated in last 24h</div>
                    </div>
                </div>

                <template v-if="activeTab === 'users'">
                    <!-- Advanced Filters & Actions -->
                    <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-2xl shadow-slate-200/50 dark:shadow-none border border-slate-200 dark:border-slate-800 p-6 mb-8">
                        <div class="flex flex-col lg:flex-row items-center gap-6">
                            <div class="w-full lg:w-1/3 relative group">
                                <Search class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within:text-primary-500 transition-colors" />
                                <input 
                                    v-model="search"
                                    type="text"
                                    placeholder="Search by name, account or phone..."
                                    class="w-full pl-12 pr-4 py-3.5 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl text-base focus:ring-4 focus:ring-primary-500/10 text-slate-900 dark:text-white placeholder:text-slate-400 font-medium transition-all"
                                />
                            </div>

                            <div class="w-full lg:flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="relative group">
                                    <MapPin class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within:text-primary-500 transition-colors" />
                                    <select 
                                        v-model="location"
                                        class="w-full pl-12 pr-4 py-3.5 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl text-sm font-bold focus:ring-4 focus:ring-primary-500/10 text-slate-900 dark:text-white appearance-none cursor-pointer"
                                    >
                                        <option value="">All Regions</option>
                                        <option v-for="loc in locations" :key="loc" :value="loc">{{ loc }}</option>
                                    </select>
                                    <ChevronRight class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 rotate-90 pointer-events-none" />
                                </div>

                                <div class="relative group">
                                    <RouterIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within:text-primary-500 transition-colors" />
                                    <select 
                                        v-model="routerId"
                                        class="w-full pl-12 pr-4 py-3.5 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl text-sm font-bold focus:ring-4 focus:ring-primary-500/10 text-slate-900 dark:text-white appearance-none cursor-pointer"
                                    >
                                        <option value="">All Controllers</option>
                                        <option v-for="router in routers" :key="router.id" :value="router.id">{{ router.name }}</option>
                                    </select>
                                    <ChevronRight class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 rotate-90 pointer-events-none" />
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-3 w-full lg:w-auto">
                                <button 
                                    @click="openCompensateModal(false)"
                                    :disabled="selectedUsers.length === 0"
                                    class="flex-1 lg:w-auto flex items-center justify-center gap-3 px-6 py-4 bg-primary hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-2xl text-sm font-black transition-all duration-300 shadow-xl active:scale-95 group"
                                >
                                    <Plus class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
                                    Selected ({{ selectedUsers.length }})
                                </button>
                                
                                <button 
                                    v-if="search || location || routerId"
                                    @click="openCompensateModal(true)"
                                    class="flex-1 lg:w-auto flex items-center justify-center gap-3 px-6 py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-sm font-black transition-all duration-300 shadow-xl shadow-emerald-500/20 active:scale-95 group"
                                >
                                    <ListFilter class="w-5 h-5 group-hover:scale-110 transition-transform duration-300" />
                                    Compensate All ({{ stats.filtered_count }})
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Table Wrapper -->
                    <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-xl shadow-slate-200/30 dark:shadow-none border border-slate-200 dark:border-slate-800 overflow-hidden backdrop-blur-3xl">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800">
                                        <th class="px-8 py-5 w-10">
                                            <input type="checkbox" @change="toggleSelectAll" class="w-5 h-5 border-2 border-slate-300 dark:border-slate-700 rounded-lg text-primary-600 focus:ring-primary-500 cursor-pointer bg-white dark:bg-slate-800 transition-all" />
                                        </th>
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest">Subscriber Info</th>
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest text-center">Service Plan</th>
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest">Expiration</th>
                                        <th class="px-8 py-5"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="user in users.data" :key="user.id" class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all duration-300">
                                        <td class="px-8 py-6">
                                            <input 
                                                type="checkbox" 
                                                :checked="selectedUsers.includes(user.id)"
                                                @change="toggleSelectUser(user.id)"
                                                class="w-5 h-5 border-2 border-slate-300 dark:border-slate-700 rounded-lg text-primary-600 focus:ring-primary-500 cursor-pointer bg-white dark:bg-slate-800 transition-all" 
                                            />
                                        </td>
                                        <td class="px-8 py-6">
                                            <div class="flex items-center gap-4">
                                                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-black text-slate-400 text-lg group-hover:bg-primary-100 dark:group-hover:bg-primary-900/30 group-hover:text-primary-600 transition-all">
                                                    {{ user.full_name.charAt(0) }}
                                                </div>
                                                <div>
                                                    <div class="text-base font-bold text-slate-900 dark:text-white group-hover:text-primary-600 transition-colors">{{ user.full_name }}</div>
                                                    <div class="flex items-center gap-2 mt-0.5">
                                                        <span class="text-xs font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded-md tracking-tighter uppercase">ID: {{ user.account_number }}</span>
                                                        <span v-if="user.location" class="text-xs font-medium text-slate-400 flex items-center gap-1">
                                                            <MapPin class="w-3 h-3" /> {{ user.location }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6 text-center">
                                            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-700/50">
                                                <RouterIcon class="w-4 h-4 text-slate-400" />
                                                <span class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-tight">{{ user.package_name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6 text-center">
                                            <span :class="['px-4 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider border transition-all duration-300', getStatusColor(user.status)]">
                                                {{ user.status }}
                                            </span>
                                        </td>
                                        <td class="px-8 py-6">
                                            <div class="flex flex-col">
                                                <span class="text-sm font-bold text-slate-900 dark:text-white">{{ formatDate(user.expires_at) }}</span>
                                                <span class="text-[10px] font-black text-slate-400 uppercase mt-0.5">Absolute Expiry</span>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6">
                                            <button class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors text-slate-400">
                                                <MoreHorizontal class="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="users.data.length === 0">
                                        <td colspan="6" class="px-8 py-24 text-center">
                                            <div class="flex flex-col items-center gap-4 max-w-sm mx-auto">
                                                <div class="w-20 h-20 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center">
                                                    <Search class="w-10 h-10 text-slate-300" />
                                                </div>
                                                <div>
                                                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">No matches found</h3>
                                                    <p class="text-slate-500 mt-1">Refine your search parameters or location filters to find the right users.</p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <!-- Enhanced Activity Log Table -->
                    <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-xl shadow-slate-200/30 dark:shadow-none border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800">
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest">Timeline</th>
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest">Account Holder</th>
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest text-center">Value Added</th>
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest">Range Transition</th>
                                        <th class="px-8 py-5 text-xs font-black text-slate-400 uppercase tracking-widest">Admin Signature</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="comp in compensations.data" :key="comp.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-all duration-300">
                                        <td class="px-8 py-6">
                                            <div class="flex items-center gap-3">
                                                <Calendar class="w-4.5 h-4.5 text-primary-500" />
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-bold text-slate-900 dark:text-white">{{ formatDate(comp.created_at) }}</span>
                                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">System Recorded</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6">
                                            <span class="text-base font-bold text-slate-900 dark:text-white">{{ comp.user?.full_name || 'Legacy Account' }}</span>
                                        </td>
                                        <td class="px-8 py-6 text-center">
                                            <span class="inline-flex items-center px-4 py-1.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-sm font-black italic rounded-xl border border-emerald-100 dark:border-emerald-500/20">
                                                +{{ comp.duration_value }} {{ comp.duration_unit }}
                                            </span>
                                        </td>
                                        <td class="px-8 py-6">
                                            <div class="flex items-center gap-3">
                                                <div class="flex flex-col">
                                                    <span class="text-xs font-bold text-slate-400 underline decoration-slate-200 dark:decoration-slate-700 underline-offset-4">{{ formatDate(comp.old_expires_at) }}</span>
                                                    <ArrowUpRight class="w-4 h-4 text-emerald-500 mx-auto my-1" />
                                                    <span class="text-[13px] font-black text-slate-900 dark:text-white">{{ formatDate(comp.new_expires_at) }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-black text-xs text-slate-500 uppercase">
                                                    {{ comp.creator?.name ? comp.creator.name.charAt(0) : 'S' }}
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ comp.creator?.name || 'System' }}</span>
                                                    <span class="text-[10px] text-slate-400 font-medium italic truncate max-w-[150px]">{{ comp.reason || 'Manual override' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="compensations.data.length === 0">
                                        <td colspan="5" class="px-8 py-24 text-center text-slate-400 font-medium">
                                            The audit trail is currently empty. All future compensations will be logged here securely.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Premium Add Compensation Modal -->
        <div v-if="showCompensateModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-md" @click="showCompensateModal = false"></div>
            
            <div class="relative bg-white dark:bg-slate-900 rounded-[3rem] shadow-2xl w-full max-w-xl border border-white/20 dark:border-slate-800 overflow-hidden transform transition-all animate-in zoom-in-95 duration-300">
                <div class="absolute top-0 right-0 -mt-16 -mr-16 w-48 h-48 bg-primary-500/10 rounded-full blur-3xl"></div>
                
                <div class="relative p-10">
                    <div class="flex items-center justify-between mb-10">
                        <div class="flex items-center gap-5">
                            <div class="w-16 h-16 rounded-[2rem] bg-gradient-to-br from-primary-500 to-indigo-600 flex items-center justify-center text-white shadow-2xl shadow-primary-500/40 transform -rotate-6">
                                <Clock class="w-8 h-8" />
                            </div>
                            <div>
                                <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Apply Extension</h2>
                                <p class="text-slate-500 dark:text-slate-400 font-bold tracking-tight uppercase text-xs mt-1 underline decoration-primary-200 dark:decoration-primary-900 underline-offset-4">
                                    Targeting {{ isBulkMode ? stats.filtered_count : selectedUsers.length }} Network Accounts
                                    <span v-if="isBulkMode" class="ml-2 lowercase font-medium text-emerald-500">(based on active filters)</span>
                                </p>
                            </div>
                        </div>
                        <button @click="showCompensateModal = false" class="p-3 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full transition-colors text-slate-400 group">
                            <X class="w-6 h-6 group-hover:rotate-90 transition-transform duration-300" />
                        </button>
                    </div>

                    <div class="space-y-8">
                        <div class="grid grid-cols-2 gap-8">
                            <div class="space-y-3">
                                <label class="text-xs font-black text-slate-400 uppercase tracking-widest pl-1">Duration Value</label>
                                <input 
                                    v-model="compensateForm.duration_value"
                                    type="number"
                                    min="1"
                                    class="w-full px-6 py-4 bg-slate-50 dark:bg-slate-800/80 border-2 border-transparent focus:border-primary-500/20 focus:ring-4 focus:ring-primary-500/10 rounded-2xl text-lg font-black text-slate-900 dark:text-white transition-all outline-none"
                                />
                                <p v-if="compensateForm.errors.duration_value" class="text-xs font-bold text-rose-500 ml-1 mt-1">{{ compensateForm.errors.duration_value }}</p>
                            </div>
                            <div class="space-y-3">
                                <label class="text-xs font-black text-slate-400 uppercase tracking-widest pl-1">Time Dimension</label>
                                <div class="relative group">
                                    <select 
                                        v-model="compensateForm.duration_unit"
                                        class="w-full px-6 py-4 bg-slate-50 dark:bg-slate-800/80 border-2 border-transparent focus:border-primary-500/20 focus:ring-4 focus:ring-primary-500/10 rounded-2xl text-lg font-black text-slate-900 dark:text-white transition-all outline-none appearance-none cursor-pointer"
                                    >
                                        <option value="minutes">Minutes</option>
                                        <option value="hours">Hours</option>
                                        <option value="days">Days</option>
                                        <option value="weeks">Weeks</option>
                                        <option value="months">Months</option>
                                    </select>
                                    <ChevronDown class="absolute right-5 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 pointer-events-none group-focus-within:rotate-180 transition-transform duration-300" />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest pl-1">Operational Justification</label>
                            <textarea 
                                v-model="compensateForm.reason"
                                rows="3"
                                placeholder="State the reason for this extension (e.g. Backbone repair downtime...)"
                                class="w-full px-6 py-4 bg-slate-50 dark:bg-slate-800/80 border-2 border-transparent focus:border-primary-500/20 focus:ring-4 focus:ring-primary-500/10 rounded-3xl text-sm font-medium text-slate-900 dark:text-white transition-all outline-none resize-none"
                            ></textarea>
                        </div>

                        <div class="group relative overflow-hidden bg-primary-50 dark:bg-primary-900/10 p-6 rounded-3xl border border-primary-100 dark:border-primary-900/20">
                            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-125 group-hover:rotate-12 transition-transform duration-700">
                                <ShieldCheck class="w-16 h-16 text-primary-600" />
                            </div>
                            <div class="flex gap-4 items-start relative z-10">
                                <AlertCircle class="w-6 h-6 text-primary-600 dark:text-primary-400 flex-shrink-0 mt-0.5" />
                                <div class="text-sm font-medium text-primary-900 dark:text-primary-300 leading-relaxed">
                                    <span class="font-black italic">Strategic Override Notice:</span>
                                    This operation will recalculate expiry dates instantly. Active accounts will be extended, while expired accounts will start their new tenure from this precise moment.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-6 mt-12">
                        <button 
                            @click="showCompensateModal = false"
                            class="flex-1 px-8 py-5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-[2rem] text-sm font-black transition-all active:scale-95"
                        >
                            Back out
                        </button>
                        <button 
                            @click="submitCompensation"
                            :disabled="compensateForm.processing"
                            class="flex-[2] px-8 py-5 bg-primary hover:opacity-90 text-white rounded-[2rem] text-sm font-black flex items-center justify-center gap-3 shadow-2xl disabled:opacity-50 transition-all hover:-translate-y-1 active:scale-95 group"
                        >
                            <Plus v-if="!compensateForm.processing" class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300" />
                            <span v-if="!compensateForm.processing">Deploy Compensation</span>
                            <span v-else>Processing Deployment...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom checkbox styling for that extra premium feel */
input[type="checkbox"] {
    @apply appearance-none;
}
input[type="checkbox"]:checked {
    @apply bg-primary border-primary;
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
    background-size: 100% 100%;
    background-position: center;
    background-repeat: no-repeat;
}
</style>

<style scoped>
/* Any custom styles localized to this page */
</style>
