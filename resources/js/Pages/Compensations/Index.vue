<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    Search,
    Filter,
    Plus,
    Clock,
    Users,
    MapPin,
    Router as RouterIcon,
    Calendar,
    ChevronRight,
    CheckCircle2,
    AlertCircle,
    History,
    TrendingUp,
    UserMinus,
    ShieldCheck,
    ArrowUpRight,
    MoreHorizontal,
    ListFilter,
    X,
    ChevronDown,
} from 'lucide-vue-next';
import debounce from 'lodash/debounce';

const props = defineProps({
    users: Object,
    compensations: Object,
    locations: Array,
    routers: Array,
    stats: Object,
    default_template: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const location = ref(props.filters.location || '');
const routerId = ref(props.filters.router_id || '');
const showCompensateModal = ref(false);
const showDetailsModal = ref(false);
const selectedCompensation = ref(null);
const selectedUsers = ref([]);
const activeTab = ref('users');
const isBulkMode = ref(false);

const openDetailsModal = (comp) => {
    selectedCompensation.value = comp;
    showDetailsModal.value = true;
};

const compensateForm = useForm({
    user_ids: [],
    duration_value: 1,
    duration_unit: 'days',
    reason: '',
    apply_to_all: false,
    search: '',
    location: '',
    notify_users: false,
    compensation_type: 'system',
    sms_template: props.default_template?.content || '',
});

const updateFilters = debounce(() => {
    router.get(
        route('compensations.index'),
        {
            search: search.value,
            location: location.value,
            router_id: routerId.value,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}, 500);

watch([search, location, routerId], () => {
    updateFilters();
});

const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedUsers.value = props.users.data.map((u) => u.id);
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
        },
    });
};

const getStatusColor = (status) => {
    switch (status) {
        case 'active':
            return 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 border-emerald-100 dark:border-emerald-500/20';
        case 'suspended':
            return 'text-rose-600 bg-rose-50 dark:bg-rose-500/10 border-rose-100 dark:border-rose-500/20';
        case 'expired':
            return 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 border-amber-100 dark:border-amber-500/20';
        default:
            return 'text-slate-600 bg-slate-50 dark:bg-slate-500/10 border-slate-100 dark:border-slate-500/20';
    }
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="User Compensations" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-slate-50/50 py-8 dark:bg-slate-950/50">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header with Glassmorphism Effect -->
                <div
                    class="relative mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
                >
                    <div
                        class="bg-primary-500/5 absolute right-0 top-0 -mr-10 -mt-10 h-64 w-64 rounded-full blur-3xl"
                    ></div>
                    <div
                        class="absolute bottom-0 left-0 -mb-10 -ml-10 h-48 w-48 rounded-full bg-emerald-500/5 blur-3xl"
                    ></div>

                    <div
                        class="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-center"
                    >
                        <div class="flex-1">
                            <div class="mb-2 flex items-center gap-3">
                                <div
                                    class="rounded-lg bg-indigo-100 p-2 dark:bg-indigo-900/30"
                                >
                                    <Clock
                                        class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                                    />
                                </div>
                                <h1
                                    class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white"
                                >
                                    User Compensations
                                </h1>
                            </div>
                            <p
                                class="text-lg font-medium text-slate-500 dark:text-slate-400"
                            >
                                Manage duration extensions and network downtime
                                recovery
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-2 overflow-hidden rounded-2xl border border-slate-200/50 bg-slate-100/50 p-1.5 shadow-inner backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-800/50"
                        >
                            <button
                                @click="activeTab = 'users'"
                                :class="[
                                    'flex items-center gap-2.5 rounded-xl px-6 py-2.5 text-sm font-bold transition-all duration-300',
                                    activeTab === 'users'
                                        ? 'bg-white text-primary dark:bg-slate-700 dark:text-primary'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                                ]"
                            >
                                <Users class="w-4.5 h-4.5" />
                                Manage Users
                            </button>
                            <button
                                @click="activeTab = 'history'"
                                :class="[
                                    'flex items-center gap-2.5 rounded-xl px-6 py-2.5 text-sm font-bold transition-all duration-300',
                                    activeTab === 'history'
                                        ? 'bg-white text-primary dark:bg-slate-700 dark:text-primary'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200',
                                ]"
                            >
                                <History class="w-4.5 h-4.5" />
                                Activity Log
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Strategic Stats Grid -->
                <div
                    class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4"
                >
                    <!-- Total Users -->
                    <div
                        class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:hover:shadow-none"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div
                                class="rounded-2xl bg-blue-50 p-3 transition-transform duration-300 group-hover:scale-110 dark:bg-blue-900/20"
                            >
                                <Users
                                    class="h-6 w-6 text-blue-600 dark:text-blue-400"
                                />
                            </div>
                            <span
                                class="rounded-lg bg-blue-50 px-2 py-1 text-xs font-bold text-blue-600 dark:bg-blue-900/20 dark:text-blue-400"
                                >Total Users</span
                            >
                        </div>
                        <div
                            class="text-3xl font-black text-slate-900 dark:text-white"
                        >
                            {{ stats.total_users }}
                        </div>
                        <div
                            class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            All network accounts
                        </div>
                    </div>

                    <!-- Suspended Users -->
                    <div
                        class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:hover:shadow-none"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div
                                class="rounded-2xl bg-rose-50 p-3 transition-transform duration-300 group-hover:scale-110 dark:bg-rose-900/20"
                            >
                                <UserMinus
                                    class="h-6 w-6 text-rose-600 dark:text-rose-400"
                                />
                            </div>
                            <span
                                class="rounded-lg bg-rose-50 px-2 py-1 text-xs font-bold text-rose-600 dark:bg-rose-900/20 dark:text-rose-400"
                                >Suspended</span
                            >
                        </div>
                        <div
                            class="text-3xl font-black text-slate-900 dark:text-white"
                        >
                            {{ stats.suspended_users }}
                        </div>
                        <div
                            class="mt-1 text-sm font-medium italic text-slate-500 underline decoration-rose-200 underline-offset-4 dark:text-slate-400 dark:decoration-rose-900"
                        >
                            Needs action
                        </div>
                    </div>

                    <!-- Total Compensations -->
                    <div
                        class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:hover:shadow-none"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div
                                class="rounded-2xl bg-emerald-50 p-3 transition-transform duration-300 group-hover:scale-110 dark:bg-emerald-900/20"
                            >
                                <ShieldCheck
                                    class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                />
                            </div>
                            <span
                                class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400"
                                >All Time</span
                            >
                        </div>
                        <div
                            class="text-3xl font-black text-slate-900 dark:text-white"
                        >
                            {{ stats.total_compensations }}
                        </div>
                        <div
                            class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            Compensations issued
                        </div>
                    </div>

                    <!-- Today's Impact -->
                    <div
                        class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition-all duration-300 hover:shadow-xl hover:shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:hover:shadow-none"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div
                                class="rounded-2xl bg-amber-50 p-3 transition-transform duration-300 group-hover:scale-110 dark:bg-amber-900/20"
                            >
                                <TrendingUp
                                    class="h-6 w-6 text-amber-600 dark:text-amber-400"
                                />
                            </div>
                            <span
                                class="rounded-lg bg-amber-50 px-2 py-1 text-xs font-bold text-amber-600 dark:bg-amber-900/20 dark:text-amber-400"
                                >Today</span
                            >
                        </div>
                        <div
                            class="text-3xl font-black text-slate-900 dark:text-white"
                        >
                            {{ stats.today_compensations }}
                        </div>
                        <div
                            class="mt-1 text-sm font-medium text-slate-500 dark:text-slate-400"
                        >
                            Updated in last 24h
                        </div>
                    </div>
                </div>

                <template v-if="activeTab === 'users'">
                    <!-- Advanced Filters & Actions -->
                    <!-- Advanced Filters & Actions -->
                    <div
                        class="mb-8 rounded-[2.5rem] border border-slate-300 bg-white p-6 shadow-xl shadow-slate-300/60 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
                    >
                        <div
                            class="flex flex-col items-center gap-6 lg:flex-row"
                        >
                            <!-- Search Input -->
                            <div class="group relative w-full lg:w-1/3">
                                <Search
                                    class="group-focus-within:text-primary-500 absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 transition-all duration-200"
                                />
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search by name, account or phone..."
                                    class="focus:ring-primary-500/20 w-full rounded-2xl border border-slate-200 bg-slate-100 py-3.5 pl-12 pr-4 text-base font-medium text-slate-900 transition-all placeholder:text-slate-400 hover:border-slate-300 hover:bg-slate-200 focus:border-transparent focus:bg-white focus:ring-4 dark:border-slate-700 dark:bg-slate-800/80 dark:text-white dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:focus:border-transparent dark:focus:bg-slate-800"
                                />
                            </div>

                            <!-- Filter Selects -->
                            <div
                                class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:flex-1"
                            >
                                <!-- Region Select -->
                                <div class="group relative">
                                    <MapPin
                                        class="group-focus-within:text-primary-500 absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 transition-all duration-200"
                                    />
                                    <select
                                        v-model="location"
                                        class="focus:ring-primary-500/20 w-full cursor-pointer appearance-none rounded-2xl border border-slate-200 bg-slate-100 py-3.5 pl-12 pr-4 text-sm font-medium text-slate-900 transition-all hover:border-slate-300 hover:bg-slate-200 focus:border-transparent focus:bg-white focus:ring-4 dark:border-slate-700 dark:bg-slate-800/80 dark:text-white dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:focus:border-transparent dark:focus:bg-slate-800"
                                    >
                                        <option value="">All Regions</option>
                                        <option
                                            v-for="loc in locations"
                                            :key="loc"
                                            :value="loc"
                                        >
                                            {{ loc }}
                                        </option>
                                    </select>
                                    <ChevronRight
                                        class="group-focus-within:text-primary-500 pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 rotate-90 text-slate-400 transition-colors duration-200"
                                    />
                                </div>

                                <!-- Controller Select -->
                                <div class="group relative">
                                    <RouterIcon
                                        class="group-focus-within:text-primary-500 absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 transition-all duration-200"
                                    />
                                    <select
                                        v-model="routerId"
                                        class="focus:ring-primary-500/20 w-full cursor-pointer appearance-none rounded-2xl border border-slate-200 bg-slate-100 py-3.5 pl-12 pr-4 text-sm font-medium text-slate-900 transition-all hover:border-slate-300 hover:bg-slate-200 focus:border-transparent focus:bg-white focus:ring-4 dark:border-slate-700 dark:bg-slate-800/80 dark:text-white dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:focus:border-transparent dark:focus:bg-slate-800"
                                    >
                                        <option value="">
                                            All Controllers
                                        </option>
                                        <option
                                            v-for="router in routers"
                                            :key="router.id"
                                            :value="router.id"
                                        >
                                            {{ router.name }}
                                        </option>
                                    </select>
                                    <ChevronRight
                                        class="group-focus-within:text-primary-500 pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 rotate-90 text-slate-400 transition-colors duration-200"
                                    />
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div
                                class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto"
                            >
                                <button
                                    @click="openCompensateModal(false)"
                                    :disabled="selectedUsers.length === 0"
                                    class="hover:bg-primary/90 shadow-primary/20 group flex flex-1 items-center justify-center gap-3 rounded-2xl bg-primary px-6 py-4 text-sm font-bold text-white shadow-xl transition-all duration-300 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-primary disabled:active:scale-100 lg:w-auto"
                                >
                                    <Plus
                                        class="h-5 w-5 transition-transform duration-300 group-hover:rotate-90"
                                    />
                                    Selected ({{ selectedUsers.length }})
                                </button>

                                <button
                                    v-if="stats.is_filtered"
                                    @click="openCompensateModal(true)"
                                    class="group flex flex-1 items-center justify-center gap-3 rounded-2xl bg-emerald-600 px-6 py-4 text-sm font-bold text-white shadow-xl shadow-emerald-600/20 transition-all duration-300 hover:bg-emerald-700 active:scale-[0.98] lg:w-auto"
                                >
                                    <ListFilter
                                        class="h-5 w-5 transition-transform duration-300 group-hover:scale-110"
                                    />
                                    Compensate All ({{ stats.filtered_count }})
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Table Wrapper -->
                    <div
                        class="overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/30 backdrop-blur-3xl dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left">
                                <thead>
                                    <tr
                                        class="border-b border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-800/80"
                                    >
                                        <th class="w-10 px-8 py-5">
                                            <input
                                                type="checkbox"
                                                @change="toggleSelectAll"
                                                class="text-primary-600 focus:ring-primary-500 h-5 w-5 cursor-pointer rounded-lg border-2 border-slate-300 bg-white transition-all dark:border-slate-700 dark:bg-slate-800"
                                            />
                                        </th>
                                        <th
                                            class="px-8 py-5 text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Subscriber Info
                                        </th>
                                        <th
                                            class="px-8 py-5 text-center text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Service Plan
                                        </th>
                                        <th
                                            class="px-8 py-5 text-center text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Status
                                        </th>
                                        <th
                                            class="px-8 py-5 text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Expiration
                                        </th>
                                        <th class="px-8 py-5"></th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="user in users.data"
                                        :key="user.id"
                                        class="group transition-all duration-300 hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                                    >
                                        <td class="px-8 py-6">
                                            <input
                                                type="checkbox"
                                                :checked="
                                                    selectedUsers.includes(
                                                        user.id,
                                                    )
                                                "
                                                @change="
                                                    toggleSelectUser(user.id)
                                                "
                                                class="text-primary-600 focus:ring-primary-500 h-5 w-5 cursor-pointer rounded-lg border-2 border-slate-300 bg-white transition-all dark:border-slate-700 dark:bg-slate-800"
                                            />
                                        </td>
                                        <td class="px-8 py-6">
                                            <div
                                                class="flex items-center gap-4"
                                            >
                                                <div
                                                    class="group-hover:bg-primary-100 dark:group-hover:bg-primary-900/30 group-hover:text-primary-600 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-lg font-black text-slate-400 transition-all dark:bg-slate-800"
                                                >
                                                    {{
                                                        user.full_name.charAt(0)
                                                    }}
                                                </div>
                                                <div>
                                                    <div
                                                        class="group-hover:text-primary-600 text-base font-bold text-slate-900 transition-colors dark:text-white"
                                                    >
                                                        {{ user.full_name }}
                                                    </div>
                                                    <div
                                                        class="mt-0.5 flex items-center gap-2"
                                                    >
                                                        <span
                                                            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-bold uppercase tracking-tighter text-slate-400 dark:bg-slate-800"
                                                            >ID:
                                                            {{
                                                                user.account_number
                                                            }}</span
                                                        >
                                                        <span
                                                            v-if="user.location"
                                                            class="flex items-center gap-1 text-xs font-medium text-slate-400"
                                                        >
                                                            <MapPin
                                                                class="h-3 w-3"
                                                            />
                                                            {{ user.location }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6 text-center">
                                            <div
                                                class="inline-flex items-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-1.5 dark:border-slate-700/50 dark:bg-slate-800/50"
                                            >
                                                <RouterIcon
                                                    class="h-4 w-4 text-slate-400"
                                                />
                                                <span
                                                    class="text-sm font-bold uppercase tracking-tight text-slate-700 dark:text-slate-300"
                                                    >{{
                                                        user.package_name
                                                    }}</span
                                                >
                                            </div>
                                        </td>
                                        <td class="px-8 py-6 text-center">
                                            <span
                                                :class="[
                                                    'rounded-xl border px-4 py-1.5 text-xs font-black uppercase tracking-wider transition-all duration-300',
                                                    getStatusColor(user.status),
                                                ]"
                                            >
                                                {{ user.status }}
                                            </span>
                                        </td>
                                        <td class="px-8 py-6">
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-sm font-bold text-slate-900 dark:text-white"
                                                    >{{
                                                        formatDate(
                                                            user.expires_at,
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="mt-0.5 text-[10px] font-black uppercase text-slate-400"
                                                    >Absolute Expiry</span
                                                >
                                            </div>
                                        </td>
                                        <td class="px-8 py-6">
                                            <button
                                                class="rounded-xl p-2 text-slate-400 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                            >
                                                <MoreHorizontal
                                                    class="h-5 w-5"
                                                />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="users.data.length === 0">
                                        <td
                                            colspan="6"
                                            class="px-8 py-24 text-center"
                                        >
                                            <div
                                                class="mx-auto flex max-w-md flex-col items-center gap-4"
                                            >
                                                <div
                                                    class="flex h-24 w-24 rotate-3 items-center justify-center rounded-3xl bg-slate-100 transition-transform group-hover:rotate-6 dark:bg-slate-800"
                                                >
                                                    <Search
                                                        v-if="stats.is_filtered"
                                                        class="h-12 w-12 text-slate-300"
                                                    />
                                                    <Users
                                                        v-else
                                                        class="h-12 w-12 text-slate-300"
                                                    />
                                                </div>
                                                <div>
                                                    <h3
                                                        class="text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                                                    >
                                                        {{
                                                            stats.is_filtered
                                                                ? 'No subscribers found'
                                                                : 'Ready to Compensate?'
                                                        }}
                                                    </h3>
                                                    <p
                                                        class="mt-2 font-medium leading-relaxed text-slate-500"
                                                    >
                                                        {{
                                                            stats.is_filtered
                                                                ? "We couldn't find any users matching your active filters. Try broadening your search or choosing a different region."
                                                                : 'To ensure peak performance, please search for a user or select a region above to start issuing duration extensions.'
                                                        }}
                                                    </p>
                                                    <div
                                                        v-if="
                                                            !stats.is_filtered
                                                        "
                                                        class="mt-6 flex justify-center gap-4"
                                                    >
                                                        <div
                                                            class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold uppercase tracking-widest text-slate-400 dark:bg-slate-800"
                                                        >
                                                            Type to search
                                                        </div>
                                                        <div
                                                            class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold uppercase tracking-widest text-slate-400 dark:bg-slate-800"
                                                        >
                                                            Filter by region
                                                        </div>
                                                    </div>
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
                    <div
                        class="overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/30 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left">
                                <thead>
                                    <tr
                                        class="border-b border-slate-200 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-800/80"
                                    >
                                        <th
                                            class="px-8 py-5 text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Timeline
                                        </th>
                                        <th
                                            class="px-8 py-5 text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Account Holder
                                        </th>
                                        <th
                                            class="px-8 py-5 text-center text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Value Added
                                        </th>
                                        <th
                                            class="px-8 py-5 text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Range Transition
                                        </th>
                                        <th
                                            class="px-8 py-5 text-xs font-black uppercase tracking-widest text-slate-400"
                                        >
                                            Admin Signature
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="comp in compensations.data"
                                        :key="comp.id"
                                        @click="openDetailsModal(comp)"
                                        class="cursor-pointer transition-all duration-300 hover:bg-slate-50/50 dark:hover:bg-slate-800/30"
                                    >
                                        <td class="px-8 py-6">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <Calendar
                                                    class="w-4.5 h-4.5 text-primary-500"
                                                />
                                                <div class="flex flex-col">
                                                    <span
                                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                                        >{{
                                                            formatDate(
                                                                comp.created_at,
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        class="text-[10px] font-black uppercase tracking-tighter text-slate-400"
                                                        >System Recorded</span
                                                    >
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6">
                                            <span
                                                class="text-base font-bold text-slate-900 dark:text-white"
                                                >{{
                                                    comp.user?.full_name ||
                                                    'Legacy Account'
                                                }}</span
                                            >
                                        </td>
                                        <td
                                            class="px-8 py-6 text-center text-sm font-black italic"
                                        >
                                            <div
                                                class="flex flex-col items-center gap-2"
                                            >
                                                <span
                                                    class="inline-flex items-center rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-1.5 text-emerald-600 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400"
                                                >
                                                    +{{ comp.duration_value }}
                                                    {{ comp.duration_unit }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase"
                                                    :class="
                                                        comp.type === 'payment'
                                                            ? 'border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-400'
                                                            : 'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'
                                                    "
                                                >
                                                    {{
                                                        comp.type === 'payment'
                                                            ? 'Paid via Wallet'
                                                            : 'System Grant'
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div class="flex flex-col">
                                                    <span
                                                        class="text-xs font-bold text-slate-400 underline decoration-slate-200 underline-offset-4 dark:decoration-slate-700"
                                                        >{{
                                                            formatDate(
                                                                comp.old_expires_at,
                                                            )
                                                        }}</span
                                                    >
                                                    <ArrowUpRight
                                                        class="mx-auto my-1 h-4 w-4 text-emerald-500"
                                                    />
                                                    <span
                                                        class="text-[13px] font-black text-slate-900 dark:text-white"
                                                        >{{
                                                            formatDate(
                                                                comp.new_expires_at,
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-6">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <div
                                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-xs font-black uppercase text-slate-500 dark:bg-slate-800"
                                                >
                                                    {{
                                                        comp.creator?.name
                                                            ? comp.creator.name.charAt(
                                                                  0,
                                                              )
                                                            : 'S'
                                                    }}
                                                </div>
                                                <div class="flex flex-col">
                                                    <span
                                                        class="text-sm font-bold text-slate-700 dark:text-slate-300"
                                                        >{{
                                                            comp.creator
                                                                ?.name ||
                                                            'System'
                                                        }}</span
                                                    >
                                                    <span
                                                        class="max-w-[150px] truncate text-[10px] font-medium italic text-slate-400"
                                                        >{{
                                                            comp.reason ||
                                                            'Manual override'
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="compensations.data.length === 0">
                                        <td
                                            colspan="5"
                                            class="px-8 py-24 text-center font-medium text-slate-400"
                                        >
                                            The audit trail is currently empty.
                                            All future compensations will be
                                            logged here securely.
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
        <div
            v-if="showCompensateModal"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        >
            <div
                class="absolute inset-0 bg-slate-950/40 backdrop-blur-md"
                @click="showCompensateModal = false"
            ></div>

            <div
                class="animate-in zoom-in-95 relative w-full max-w-xl transform overflow-hidden rounded-[3rem] border border-white/20 bg-white shadow-2xl transition-all duration-300 dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="bg-primary-500/10 absolute right-0 top-0 -mr-16 -mt-16 h-48 w-48 rounded-full blur-3xl"
                ></div>

                <div class="relative p-10">
                    <div class="mb-10 flex items-center justify-between">
                        <div class="flex items-center gap-5">
                            <div
                                class="from-primary-500 shadow-primary-500/40 flex h-16 w-16 -rotate-6 transform items-center justify-center rounded-[2rem] bg-gradient-to-br to-indigo-600 text-white shadow-2xl"
                            >
                                <Clock class="h-8 w-8" />
                            </div>
                            <div>
                                <h2
                                    class="text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                                >
                                    Apply Extension
                                </h2>
                                <p
                                    class="decoration-primary-200 dark:decoration-primary-900 mt-1 text-xs font-bold uppercase tracking-tight text-slate-500 underline underline-offset-4 dark:text-slate-400"
                                >
                                    Targeting
                                    {{
                                        isBulkMode
                                            ? stats.filtered_count
                                            : selectedUsers.length
                                    }}
                                    Network Accounts
                                    <span
                                        v-if="isBulkMode"
                                        class="ml-2 font-medium lowercase text-emerald-500"
                                        >(based on active filters)</span
                                    >
                                </p>
                            </div>
                        </div>
                        <button
                            @click="showCompensateModal = false"
                            class="group rounded-full p-3 text-slate-400 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            <X
                                class="h-6 w-6 transition-transform duration-300 group-hover:rotate-90"
                            />
                        </button>
                    </div>

                    <div class="space-y-8">
                        <div class="space-y-3">
                            <label
                                class="pl-1 text-xs font-black uppercase tracking-widest text-slate-400"
                                >Compensation Type</label
                            >
                            <div class="grid grid-cols-2 gap-4">
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        v-model="
                                            compensateForm.compensation_type
                                        "
                                        value="system"
                                        class="peer sr-only"
                                    />
                                    <div
                                        class="peer-checked:bg-primary/5 flex items-center justify-center rounded-2xl border-2 border-transparent bg-slate-50 p-4 transition-all peer-checked:border-primary dark:bg-slate-800/80"
                                    >
                                        <div class="flex flex-col items-center">
                                            <span
                                                class="text-sm font-black text-slate-900 dark:text-white"
                                                >System Grant</span
                                            >
                                            <span
                                                class="text-[10px] uppercase tracking-widest text-slate-500"
                                                >Free Extension</span
                                            >
                                        </div>
                                    </div>
                                </label>
                                <label
                                    class="cursor-pointer"
                                    title="Deducts exact package price from user's wallet balance"
                                >
                                    <input
                                        type="radio"
                                        v-model="
                                            compensateForm.compensation_type
                                        "
                                        value="payment"
                                        class="peer sr-only"
                                    />
                                    <div
                                        class="flex items-center justify-center rounded-2xl border-2 border-transparent bg-slate-50 p-4 transition-all peer-checked:border-indigo-500 peer-checked:bg-indigo-500/5 dark:bg-slate-800/80"
                                    >
                                        <div class="flex flex-col items-center">
                                            <span
                                                class="text-sm font-black text-slate-900 dark:text-white"
                                                >Paid via Wallet</span
                                            >
                                            <span
                                                class="text-[10px] uppercase tracking-widest text-slate-500"
                                                >Deducts Balance</span
                                            >
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-8">
                            <div class="space-y-3">
                                <label
                                    class="pl-1 text-xs font-black uppercase tracking-widest text-slate-400"
                                    >Duration Value</label
                                >
                                <input
                                    v-model="compensateForm.duration_value"
                                    type="number"
                                    min="1"
                                    class="focus:border-primary-500/20 focus:ring-primary-500/10 w-full rounded-2xl border-2 border-transparent bg-slate-50 px-6 py-4 text-lg font-black text-slate-900 outline-none transition-all focus:ring-4 dark:bg-slate-800/80 dark:text-white"
                                />
                                <p
                                    v-if="compensateForm.errors.duration_value"
                                    class="ml-1 mt-1 text-xs font-bold text-rose-500"
                                >
                                    {{ compensateForm.errors.duration_value }}
                                </p>
                            </div>
                            <div class="space-y-3">
                                <label
                                    class="pl-1 text-xs font-black uppercase tracking-widest text-slate-400"
                                    >Time Dimension</label
                                >
                                <div class="group relative">
                                    <select
                                        v-model="compensateForm.duration_unit"
                                        class="focus:border-primary-500/20 focus:ring-primary-500/10 w-full cursor-pointer appearance-none rounded-2xl border-2 border-transparent bg-slate-50 px-6 py-4 text-lg font-black text-slate-900 outline-none transition-all focus:ring-4 dark:bg-slate-800/80 dark:text-white"
                                    >
                                        <option value="minutes">Minutes</option>
                                        <option value="hours">Hours</option>
                                        <option value="days">Days</option>
                                        <option value="weeks">Weeks</option>
                                        <option value="months">Months</option>
                                    </select>
                                    <ChevronDown
                                        class="pointer-events-none absolute right-5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 transition-transform duration-300 group-focus-within:rotate-180"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- SMS Notification Toggle -->
                        <div
                            class="space-y-4 rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="bg-primary/10 rounded-xl p-2">
                                        <History class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <span
                                            class="text-sm font-black text-slate-900 dark:text-white"
                                            >Send SMS Notification</span
                                        >
                                        <p
                                            class="text-[10px] font-bold uppercase tracking-tight text-slate-500"
                                        >
                                            Update users via text message
                                        </p>
                                    </div>
                                </div>
                                <label
                                    class="relative inline-flex cursor-pointer items-center"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="compensateForm.notify_users"
                                        class="peer sr-only"
                                    />
                                    <div
                                        class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none dark:border-gray-600 dark:bg-slate-700"
                                    ></div>
                                </label>
                            </div>

                            <div
                                v-if="compensateForm.notify_users"
                                class="animate-in fade-in slide-in-from-top-2 space-y-4 duration-300"
                            >
                                <div class="space-y-2">
                                    <label
                                        class="border-l-2 border-primary pl-2 text-[10px] font-black uppercase tracking-widest text-slate-400"
                                        >Message Template</label
                                    >
                                    <textarea
                                        v-model="compensateForm.sms_template"
                                        rows="4"
                                        class="focus:ring-primary/10 w-full resize-none rounded-2xl border-2 border-slate-200 bg-white px-5 py-4 text-sm font-medium text-slate-900 outline-none transition-all focus:border-primary focus:ring-4 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    ></textarea>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        class="hover:bg-primary-500 transform cursor-help rounded-lg bg-slate-200 px-2.5 py-1 text-[10px] font-black text-slate-600 transition-all hover:scale-105 hover:text-white dark:bg-slate-700 dark:text-slate-400"
                                        title="User's Full Name"
                                        >{{ name }}</span
                                    >
                                    <span
                                        class="hover:bg-primary-500 transform cursor-help rounded-lg bg-slate-200 px-2.5 py-1 text-[10px] font-black text-slate-600 transition-all hover:scale-105 hover:text-white dark:bg-slate-700 dark:text-slate-400"
                                        title="Numeric duration value (e.g. 5)"
                                        >{{ duration }}</span
                                    >
                                    <span
                                        class="hover:bg-primary-500 transform cursor-help rounded-lg bg-slate-200 px-2.5 py-1 text-[10px] font-black text-slate-600 transition-all hover:scale-105 hover:text-white dark:bg-slate-700 dark:text-slate-400"
                                        title="Time unit (e.g. days)"
                                        >{{ unit }}</span
                                    >
                                    <span
                                        class="hover:bg-primary-500 transform cursor-help rounded-lg bg-slate-200 px-2.5 py-1 text-[10px] font-black text-slate-600 transition-all hover:scale-105 hover:text-white dark:bg-slate-700 dark:text-slate-400"
                                        title="New expiration date/time"
                                        >{{ new_expiry }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label
                                class="pl-1 text-xs font-black uppercase tracking-widest text-slate-400"
                                >Operational Justification</label
                            >
                            <textarea
                                v-model="compensateForm.reason"
                                rows="3"
                                placeholder="State the reason for this extension (e.g. Backbone repair downtime...)"
                                class="focus:border-primary-500/20 focus:ring-primary-500/10 w-full resize-none rounded-3xl border-2 border-transparent bg-slate-50 px-6 py-4 text-sm font-medium text-slate-900 outline-none transition-all focus:ring-4 dark:bg-slate-800/80 dark:text-white"
                            ></textarea>
                        </div>

                        <div
                            class="bg-primary-50 dark:bg-primary-900/10 border-primary-100 dark:border-primary-900/20 group relative overflow-hidden rounded-3xl border p-6"
                        >
                            <div
                                class="absolute right-0 top-0 p-4 opacity-10 transition-transform duration-700 group-hover:rotate-12 group-hover:scale-125"
                            >
                                <ShieldCheck
                                    class="text-primary-600 h-16 w-16"
                                />
                            </div>
                            <div class="relative z-10 flex items-start gap-4">
                                <AlertCircle
                                    class="text-primary-600 dark:text-primary-400 mt-0.5 h-6 w-6 flex-shrink-0"
                                />
                                <div
                                    class="text-primary-900 dark:text-primary-300 text-sm font-medium leading-relaxed"
                                >
                                    <span class="font-black italic"
                                        >Strategic Override Notice:</span
                                    >
                                    This operation will recalculate expiry dates
                                    instantly. Active accounts will be extended,
                                    while expired accounts will start their new
                                    tenure from this precise moment.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-12 flex items-center gap-6">
                        <button
                            @click="showCompensateModal = false"
                            class="flex-1 rounded-[2rem] bg-slate-100 px-8 py-5 text-sm font-black text-slate-700 transition-all hover:bg-slate-200 active:scale-95 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            Back out
                        </button>
                        <button
                            @click="submitCompensation"
                            :disabled="compensateForm.processing"
                            class="group flex flex-[2] items-center justify-center gap-3 rounded-[2rem] bg-primary px-8 py-5 text-sm font-black text-white shadow-2xl transition-all hover:-translate-y-1 hover:opacity-90 active:scale-95 disabled:opacity-50"
                        >
                            <Plus
                                v-if="!compensateForm.processing"
                                class="h-5 w-5 transition-transform duration-300 group-hover:rotate-90"
                            />
                            <span v-if="!compensateForm.processing"
                                >Deploy Compensation</span
                            >
                            <span v-else>Processing Deployment...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compensation Details Modal -->
        <div
            v-if="showDetailsModal && selectedCompensation"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        >
            <div
                class="absolute inset-0 bg-slate-950/40 backdrop-blur-md"
                @click="showDetailsModal = false"
            ></div>

            <div
                class="animate-in zoom-in-95 relative w-full max-w-lg transform overflow-hidden rounded-[3rem] border border-white/20 bg-white shadow-2xl transition-all duration-300 dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="absolute left-0 top-0 -ml-16 -mt-16 h-48 w-48 rounded-full blur-3xl"
                    :class="
                        selectedCompensation.type === 'payment'
                            ? 'bg-indigo-500/10'
                            : 'bg-emerald-500/10'
                    "
                ></div>

                <div class="relative p-10">
                    <div class="mb-8 flex items-start justify-between">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-[1.5rem] bg-gradient-to-br text-white shadow-xl shadow-slate-500/20"
                                :class="
                                    selectedCompensation.type === 'payment'
                                        ? 'from-indigo-400 to-indigo-600'
                                        : 'from-emerald-400 to-emerald-600'
                                "
                            >
                                <History class="h-6 w-6" />
                            </div>
                            <div>
                                <h2
                                    class="text-xl font-black tracking-tight text-slate-900 dark:text-white"
                                >
                                    Timeline Event
                                </h2>
                                <span
                                    class="mt-1 flex w-max items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:bg-slate-800"
                                >
                                    {{
                                        formatDate(
                                            selectedCompensation.created_at,
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                        <button
                            @click="showDetailsModal = false"
                            class="rounded-full p-2 text-slate-400 transition-colors hover:bg-slate-100 dark:bg-slate-800"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50"
                            >
                                <span
                                    class="mb-1 block text-[10px] font-black uppercase leading-none tracking-widest text-slate-400"
                                    >Account Holder</span
                                >
                                <span
                                    class="text-sm font-bold text-slate-900 dark:text-white"
                                    >{{
                                        selectedCompensation.user?.full_name ||
                                        'N/A'
                                    }}</span
                                >
                            </div>
                            <div
                                class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50"
                            >
                                <span
                                    class="mb-1 block text-[10px] font-black uppercase leading-none tracking-widest text-slate-400"
                                    >Event Type</span
                                >
                                <span
                                    class="block truncate text-sm font-bold"
                                    :class="
                                        selectedCompensation.type === 'payment'
                                            ? 'text-indigo-600 dark:text-indigo-400'
                                            : 'text-emerald-600 dark:text-emerald-400'
                                    "
                                >
                                    {{
                                        selectedCompensation.type === 'payment'
                                            ? 'Paid via Wallet'
                                            : 'System Grant'
                                    }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/50"
                        >
                            <div class="flex flex-col">
                                <span
                                    class="mb-1 block text-[10px] font-black uppercase leading-none tracking-widest text-slate-400"
                                    >Old Expiration</span
                                >
                                <span
                                    class="text-sm font-medium text-slate-600 line-through dark:text-slate-400"
                                    >{{
                                        formatDate(
                                            selectedCompensation.old_expires_at,
                                        )
                                    }}</span
                                >
                            </div>
                            <ArrowUpRight
                                class="mx-2 h-5 w-5 text-emerald-500"
                            />
                            <div class="flex flex-col items-end">
                                <span
                                    class="mb-1 block text-[10px] font-black uppercase leading-none tracking-widest text-emerald-500/70"
                                    >+{{ selectedCompensation.duration_value }}
                                    {{
                                        selectedCompensation.duration_unit
                                    }}</span
                                >
                                <span
                                    class="text-sm font-bold text-slate-900 dark:text-emerald-400"
                                    >{{
                                        formatDate(
                                            selectedCompensation.new_expires_at,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="rounded-2xl bg-slate-50 p-5 dark:bg-slate-800/50"
                        >
                            <span
                                class="mb-3 block text-[10px] font-black uppercase leading-none tracking-widest text-slate-400"
                                >Admin Signature & Reason</span
                            >
                            <div class="flex items-start gap-3">
                                <div
                                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-black uppercase text-slate-500 dark:bg-slate-700"
                                >
                                    {{
                                        selectedCompensation.creator?.name
                                            ? selectedCompensation.creator.name.charAt(
                                                  0,
                                              )
                                            : 'S'
                                    }}
                                </div>
                                <div class="flex flex-col">
                                    <span
                                        class="text-sm font-bold text-slate-700 dark:text-slate-300"
                                        >{{
                                            selectedCompensation.creator
                                                ?.name || 'System'
                                        }}</span
                                    >
                                    <span
                                        class="mt-1 text-sm italic leading-relaxed text-slate-500"
                                        >{{
                                            selectedCompensation.reason ||
                                            'No additional reason provided.'
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <button
                            @click="showDetailsModal = false"
                            class="w-full rounded-2xl bg-slate-900 py-4 text-sm font-black uppercase tracking-widest text-white transition-colors hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                        >
                            Close Record
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom checkbox styling for that extra premium feel */
input[type='checkbox'] {
    @apply appearance-none;
}
input[type='checkbox']:checked {
    @apply border-primary bg-primary;
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z'/%3e%3c/svg%3e");
    background-size: 100% 100%;
    background-position: center;
    background-repeat: no-repeat;
}
</style>

<style scoped>
/* Any custom styles localized to this page */
</style>
