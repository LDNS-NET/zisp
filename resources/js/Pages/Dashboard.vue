<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import {
    Users,
    User,
    Ticket,
    Inbox,
    RadioTower,
    DollarSign,
    Package,
    Coins,
    MessagesSquare,
    Server,
    FileText,
    Smile,
    BarChart2,
    TrendingUp,
    Activity,
    Check,
    X,
    Zap,
    Clock,
    AlertCircle,
    PieChart,
    LineChart,
    UserCheck
} from 'lucide-vue-next';

const props = defineProps(['stats', 'currency']);
const page = usePage();
const user = usePage().props.auth.user;
const expiresAt = ref(page.props.subscription_expires_at || null);
const countdown = ref('');
const daysRemaining = ref(0);

// Dynamic greeting based on time of day
const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 4) return 'Good night';
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    if (hour < 22) return 'Good evening';
    return 'Good night';
};

const greeting = ref(getGreeting());

function updateCountdown() {
    if (!expiresAt.value) return;

    const now = new Date();
    const expiry = new Date(expiresAt.value);
    const diff = expiry - now;

    if (diff <= 0) {
        countdown.value = 'Expired';
        daysRemaining.value = 0;
        return;
    }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
    const minutes = Math.floor((diff / (1000 * 60)) % 60);

    countdown.value = `${days}d ${hours}h ${minutes}m`;
    daysRemaining.value = days;
}

onMounted(() => {
    updateCountdown();
    setInterval(updateCountdown, 1000);
});

// Compute subscription status color
const subscriptionStatus = computed(() => {
    if (daysRemaining.value === 0) return 'expired';
    if (daysRemaining.value <= 3) return 'critical';
    if (daysRemaining.value <= 7) return 'warning';
    return 'active';
});

// Chart configurations
const isDark = computed(() => {
    return document.documentElement.classList.contains('dark');
});

// Monthly Income Chart (Area Chart)
const incomeChartOptions = computed(() => ({
    chart: {
        type: 'area',
        height: 350,
        toolbar: { show: false },
        background: 'transparent',
    },
    theme: {
        mode: isDark.value ? 'dark' : 'light',
    },
    dataLabels: { enabled: false },
    stroke: {
        curve: 'smooth',
        width: 3,
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.2,
        },
    },
    colors: ['#10b981'],
    xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        labels: {
            style: {
                colors: isDark.value ? '#9ca3af' : '#6b7280',
            },
        },
    },
    yaxis: {
        labels: {
            formatter: (value) => `${props.currency || 'KES'} ${value.toLocaleString()}`,
            style: {
                colors: isDark.value ? '#9ca3af' : '#6b7280',
            },
        },
    },
    grid: {
        borderColor: isDark.value ? '#374151' : '#e5e7eb',
        strokeDashArray: 4,
    },
    tooltip: {
        theme: isDark.value ? 'dark' : 'light',
        y: {
            formatter: (value) => `${props.currency || 'KES'} ${value.toLocaleString()}`,
        },
    },
}));

const incomeChartSeries = computed(() => {
    const paymentsData = props.stats?.payments_chart || Array(12).fill(0);
    
    console.log('Payments Chart Data:', paymentsData);
    
    return [{
        name: 'Revenue',
        data: paymentsData,
    }];
});

// User Growth Chart (Bar Chart - Simplified)
const userGrowthOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: 350,
        toolbar: { show: false },
        background: 'transparent',
    },
    theme: {
        mode: isDark.value ? 'dark' : 'light',
    },
    plotOptions: {
        bar: {
            horizontal: false,
            columnWidth: '55%',
            borderRadius: 4,
        },
    },
    dataLabels: {
        enabled: false,
    },
    colors: ['#3b82f6', '#06b6d4', '#f59e0b'],
    xaxis: {
        categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        labels: {
            style: {
                colors: isDark.value ? '#9ca3af' : '#6b7280',
            },
        },
    },
    yaxis: {
        labels: {
            style: {
                colors: isDark.value ? '#9ca3af' : '#6b7280',
            },
        },
    },
    grid: {
        borderColor: isDark.value ? '#374151' : '#e5e7eb',
        strokeDashArray: 4,
    },
    tooltip: {
        theme: isDark.value ? 'dark' : 'light',
        y: {
            formatter: (value) => `${value} users`,
        },
    },
    legend: {
        position: 'top',
        labels: {
            colors: isDark.value ? '#9ca3af' : '#6b7280',
        },
    },
}));

const userGrowthSeries = computed(() => {
    // Use real data from backend based on actual created_at dates
    const typesData = props.stats?.user_types_chart;
    
    console.log('User Types Chart Data from Backend:', typesData);
    console.log('Props Stats:', props.stats);
    
    if (!typesData) {
        console.warn('No user_types_chart data available');
        // Fallback if no data
        return [
            { name: 'Hotspot Users', data: Array(12).fill(0) },
            { name: 'PPPoE Users', data: Array(12).fill(0) },
            { name: 'Static Users', data: Array(12).fill(0) },
        ];
    }

    const chartSeries = [
        {
            name: 'Hotspot Users',
            data: typesData.hotspot || Array(12).fill(0),
        },
        {
            name: 'PPPoE Users',
            data: typesData.pppoe || Array(12).fill(0),
        },
        {
            name: 'Static Users',
            data: typesData.static || Array(12).fill(0),
        },
    ];
    
    console.log('Chart Series Being Passed:', chartSeries);
    
    return chartSeries;
});

// Package Utilization Chart (Donut Chart)
const packageChartOptions = computed(() => ({
    chart: {
        type: 'donut',
        height: 350,
        background: 'transparent',
    },
    theme: {
        mode: isDark.value ? 'dark' : 'light',
    },
    labels: props.stats?.user_distribution ? Object.keys(props.stats.user_distribution) : ['Hotspot', 'PPPoE', 'Static'],
    colors: ['#3b82f6', '#06b6d4', '#f59e0b', '#ef4444'],
    dataLabels: {
        enabled: true,
        style: {
            fontSize: '14px',
            fontWeight: 'bold',
        },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '70%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total Users',
                        fontSize: '16px',
                        fontWeight: 600,
                        color: isDark.value ? '#9ca3af' : '#6b7280',
                        formatter: () => props.stats?.users?.total || '0',
                    },
                },
            },
        },
    },
    legend: {
        position: 'bottom',
        labels: {
            colors: isDark.value ? '#9ca3af' : '#6b7280',
        },
    },
    tooltip: {
        theme: isDark.value ? 'dark' : 'light',
    },
}));

const packageChartSeries = computed(() => 
    props.stats?.user_distribution ? Object.values(props.stats.user_distribution) : [
        props.stats?.users?.hotspot || 0,
        props.stats?.users?.pppoe || 0,
        props.stats?.users?.static || 0,
    ]
);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <div class="w-full">
            <!-- Hero Section with Professional Gradient -->
            <div class="relative overflow-hidden bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950">
                <!-- Animated Background Pattern -->
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute inset-0" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 40px 40px;"></div>
                </div>
                
                <div class="relative px-4 py-12 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-7xl">
                        <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                            <!-- Welcome Message -->
                            <div class="space-y-2">
                                <h1 class="text-3xl font-bold text-white sm:text-4xl lg:text-5xl">
                                    {{ greeting }}, {{ user.name }}! 👋
                                </h1>
                                <p class="text-lg text-slate-300">
                                    Network overview and key metrics
                                </p>
                            </div>

                            <!-- Subscription Badge -->
                            <div v-if="expiresAt" class="flex-shrink-0">
                                <div :class="[
                                    'group relative overflow-hidden rounded-2xl p-6 backdrop-blur-lg transition-all duration-300 hover:scale-105',
                                    subscriptionStatus === 'expired' ? 'bg-red-500/20 ring-2 ring-red-400' :
                                    subscriptionStatus === 'critical' ? 'bg-orange-500/20 ring-2 ring-orange-400' :
                                    subscriptionStatus === 'warning' ? 'bg-amber-500/20 ring-2 ring-amber-400' :
                                    'bg-emerald-500/20 ring-2 ring-emerald-400'
                                ]">
                                    <div class="flex items-center gap-4">
                                        <div :class="[
                                            'flex h-12 w-12 items-center justify-center rounded-xl font-bold',
                                            subscriptionStatus === 'expired' ? 'bg-red-600' :
                                            subscriptionStatus === 'critical' ? 'bg-orange-600' :
                                            subscriptionStatus === 'warning' ? 'bg-amber-600' :
                                            'bg-emerald-600'
                                        ]">
                                            <Clock class="h-6 w-6 text-white" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-white/90">Subscription Expires</p>
                                            <p class="text-2xl font-bold text-white">{{ countdown }}</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Button -->
                                    <a v-if="daysRemaining <= 5"
                                        href="https://payment.intasend.com/pay/8d7f60c4-f2c2-4642-a2b6-0654a3cc24e3/"
                                        target="_blank"
                                        class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition-all hover:bg-slate-100 hover:shadow-lg"
                                    >
                                        <Zap class="h-4 w-4" />
                                        Renew Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="space-y-8">
                    <!-- Quick Stats Grid - Compact on mobile -->
                    <div class="grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
                        <!-- All Online Users -->
                         <div class="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-700 p-4 sm:p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-emerald-700 dark:to-emerald-800">
                            <div class="absolute right-0 top-0 h-20 w-20 sm:h-32 sm:w-32 translate-x-6 sm:translate-x-8 -translate-y-6 sm:-translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs sm:text-sm font-medium text-emerald-100">Online Users</p>    
                                        <p class="mt-1 sm:mt-2 text-2xl sm:text-4xl font-bold text-white">{{ stats.users.activeUsers }}</p>
                                    </div>
                                    <div class="rounded-lg sm:rounded-xl bg-white/20 p-2 sm:p-3 backdrop-blur-sm">
                                        <Zap class="h-5 w-5 sm:h-8 sm:w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-2 sm:mt-4 flex items-center gap-1 sm:gap-2 text-xs sm:text-sm text-emerald-100">
                                    <UserCheck class="h-3 w-3 sm:h-4 sm:w-4" />
                                    <span>{{ stats.users.total }} users</span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Users -->
                        <div class="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 p-4 sm:p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-blue-700 dark:to-blue-800">
                            <div class="absolute right-0 top-0 h-20 w-20 sm:h-32 sm:w-32 translate-x-6 sm:translate-x-8 -translate-y-6 sm:-translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs sm:text-sm font-medium text-blue-100">Total Users</p>
                                        <p class="mt-1 sm:mt-2 text-2xl sm:text-4xl font-bold text-white">{{ stats.users.total }}</p>
                                    </div>
                                    <div class="rounded-lg sm:rounded-xl bg-white/20 p-2 sm:p-3 backdrop-blur-sm">
                                        <Users class="h-5 w-5 sm:h-8 sm:w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-2 sm:mt-4 flex items-center gap-1 sm:gap-2 text-xs sm:text-sm text-blue-100">
                                    <Activity class="h-3 w-3 sm:h-4 sm:w-4" />
                                    <span>{{ stats.users.activeUsers }} active</span>
                                </div>
                            </div>
                        </div>

                        <!-- MikroTik Devices -->
                        <div class="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-amber-600 to-amber-700 p-4 sm:p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-amber-700 dark:to-amber-800">
                            <div class="absolute right-0 top-0 h-20 w-20 sm:h-32 sm:w-32 translate-x-6 sm:translate-x-8 -translate-y-6 sm:-translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs sm:text-sm font-medium text-amber-100">Devices</p>
                                        <p class="mt-1 sm:mt-2 text-2xl sm:text-4xl font-bold text-white">{{ stats.mikrotiks.total }}</p>
                                    </div>
                                    <div class="rounded-lg sm:rounded-xl bg-white/20 p-2 sm:p-3 backdrop-blur-sm">
                                        <RadioTower class="h-5 w-5 sm:h-8 sm:w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-2 sm:mt-4 flex items-center gap-1 sm:gap-2 text-xs sm:text-sm text-amber-100">
                                    <Check class="h-3 w-3 sm:h-4 sm:w-4" />
                                    <span v-if="stats.mikrotiks.connected === 0"
                                    class="text-red-200">
                                        All Routers Offline
                                    </span>
                                    <span v-else>
                                        {{ stats.mikrotiks.connected }} online
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Open Tickets -->
                        <div class="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-slate-600 to-slate-700 p-4 sm:p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-slate-700 dark:to-slate-800">
                            <div class="absolute right-0 top-0 h-20 w-20 sm:h-32 sm:w-32 translate-x-6 sm:translate-x-8 -translate-y-6 sm:-translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs sm:text-sm font-medium text-slate-100">Tickets</p>
                                        <p class="mt-1 sm:mt-2 text-2xl sm:text-4xl font-bold text-white">{{ stats.tickets.open }}</p>
                                    </div>
                                    <div class="rounded-lg sm:rounded-xl bg-white/20 p-2 sm:p-3 backdrop-blur-sm">
                                        <Ticket class="h-5 w-5 sm:h-8 sm:w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-2 sm:mt-4 flex items-center gap-1 sm:gap-2 text-xs sm:text-sm text-slate-100">
                                    <User class="h-3 w-3 sm:h-4 sm:w-4" />
                                    <span>{{ stats.tickets.assigned_to_me }} yours</span>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue (if admin/cashier) -->
                        <div v-if="user.role === 'admin' || user.role === 'cashier'" class="group relative overflow-hidden rounded-xl sm:rounded-2xl bg-gradient-to-br from-indigo-600 to-indigo-700 p-4 sm:p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-indigo-700 dark:to-indigo-800">
                            <div class="absolute right-0 top-0 h-20 w-20 sm:h-32 sm:w-32 translate-x-6 sm:translate-x-8 -translate-y-6 sm:-translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs sm:text-sm font-medium text-indigo-100">Revenue</p>
                                        <p class="mt-1 sm:mt-2 text-xl sm:text-3xl font-bold text-white">{{ currency || 'KES' }} {{ stats.payments.total_amount }}</p>
                                    </div>
                                    <div class="rounded-lg sm:rounded-xl bg-white/20 p-2 sm:p-3 backdrop-blur-sm">
                                        <DollarSign class="h-5 w-5 sm:h-8 sm:w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-2 sm:mt-4 flex items-center gap-1 sm:gap-2 text-xs sm:text-sm text-indigo-100">
                                    <TrendingUp class="h-3 w-3 sm:h-4 sm:w-4" />
                                    <span>{{ stats.payments.count }} payments</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Stats Sections -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Network Users -->
                        <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                            <h3 class="mb-6 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                <Users class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                Network Users
                            </h3>
                            <div class="grid grid-cols-2 gap-2 sm:gap-4">
                                <div class="rounded-lg sm:rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 p-3 sm:p-4 dark:from-blue-900/20 dark:to-blue-800/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Hotspot</p>
                                            <p class="mt-0.5 sm:mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users.hotspot }}</p>
                                        </div>
                                        <RadioTower class="h-5 w-5 sm:h-8 sm:w-8 text-blue-600 dark:text-blue-400" />
                                    </div>
                                </div>
                                <div class="rounded-lg sm:rounded-xl bg-gradient-to-br from-cyan-50 to-cyan-100 p-3 sm:p-4 dark:from-cyan-900/20 dark:to-cyan-800/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">PPPoE</p>
                                            <p class="mt-0.5 sm:mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users.pppoe }}</p>
                                        </div>
                                        <User class="h-5 w-5 sm:h-8 sm:w-8 text-cyan-600 dark:text-cyan-400" />
                                    </div>
                                </div>
                                <div class="rounded-lg sm:rounded-xl bg-gradient-to-br from-amber-50 to-amber-100 p-3 sm:p-4 dark:from-amber-900/20 dark:to-amber-800/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Static</p>
                                            <p class="mt-0.5 sm:mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users.static }}</p>
                                        </div>
                                        <Server class="h-5 w-5 sm:h-8 sm:w-8 text-amber-600 dark:text-amber-400" />
                                    </div>
                                </div>
                                <div class="rounded-lg sm:rounded-xl bg-gradient-to-br from-red-50 to-red-100 p-3 sm:p-4 dark:from-red-900/20 dark:to-red-800/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Expired</p>
                                            <p class="mt-0.5 sm:mt-1 text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users.expired }}</p>
                                        </div>
                                        <X class="h-5 w-5 sm:h-8 sm:w-8 text-red-600 dark:text-red-400" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leads & Tickets -->
                        <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                            <h3 class="mb-6 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                <Inbox class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                Leads & Support
                            </h3>
                            <div class="space-y-4">
                                <!-- Leads -->
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Leads</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.leads.total }}</p>
                                        </div>
                                        <div class="flex gap-4 text-sm">
                                            <div class="text-center">
                                                <p class="text-amber-600 dark:text-amber-400">{{ stats.leads.pending }}</p>
                                                <p class="text-xs text-gray-500">Pending</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-emerald-600 dark:text-emerald-400">{{ stats.leads.converted }}</p>
                                                <p class="text-xs text-gray-500">Converted</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Tickets -->
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Support Tickets</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.tickets.open + stats.tickets.closed }}</p>
                                        </div>
                                        <div class="flex gap-4 text-sm">
                                            <div class="text-center">
                                                <p class="text-orange-600 dark:text-orange-400">{{ stats.tickets.open }}</p>
                                                <p class="text-xs text-gray-500">Open</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-emerald-600 dark:text-emerald-400">{{ stats.tickets.closed }}</p>
                                                <p class="text-xs text-gray-500">Closed</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics & Charts Section -->
                    <div class="space-y-6">
                        <h3 class="flex items-center gap-2 text-xl font-semibold text-gray-900 dark:text-white">
                            <BarChart2 class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                            Analytics & Insights
                        </h3>

                        <!-- Charts Grid -->
                        <div class="grid gap-6 lg:grid-cols-2">
                            <!-- Monthly Income Chart -->
                            <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                                <div class="mb-4 flex items-center justify-between">
                                    <h4 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                        <TrendingUp class="h-5 w-5 text-green-600 dark:text-green-400" />
                                        Monthly Revenue
                                    </h4>
                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        Last 12 Months
                                    </span>
                                </div>
                                <VueApexCharts
                                    type="line"
                                    height="300"
                                    :options="incomeChartOptions"
                                    :series="incomeChartSeries"
                                />
                            </div>

                            <!-- User Growth Chart -->
                            <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                                <div class="mb-4 flex items-center justify-between">
                                    <h4 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                        <LineChart class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                        Network User Types
                                    </h4>
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        Current Year
                                    </span>
                                </div>
                                
                                <!-- Data Preview -->
                                <div v-if="stats?.users" class="mb-4 flex gap-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-indigo-500"></div>
                                        <span class="text-gray-600 dark:text-gray-400">Hotspot: <strong class="text-gray-900 dark:text-white">{{ stats.users.hotspot }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-pink-500"></div>
                                        <span class="text-gray-600 dark:text-gray-400">PPPoE: <strong class="text-gray-900 dark:text-white">{{ stats.users.pppoe }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-amber-500"></div>
                                        <span class="text-gray-600 dark:text-gray-400">Static: <strong class="text-gray-900 dark:text-white">{{ stats.users.static }}</strong></span>
                                    </div>
                                </div>
                                
                                <VueApexCharts
                                    type="bar"
                                    height="300"
                                    :options="userGrowthOptions"
                                    :series="userGrowthSeries"
                                />
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-2">
                            <!-- Package Utilization Chart (Full Width) -->
                            <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                                <div class="mb-4 flex items-center justify-between">
                                    <h4 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                        <PieChart class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                        Package Distribution
                                    </h4>
                                    <span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-semibold text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">
                                        Current Status
                                    </span>
                                </div>
                                <div class="mx-auto max-w-2xl">
                                    <VueApexCharts
                                        type="donut"
                                        height="350"
                                        :options="packageChartOptions"
                                        :series="packageChartSeries"
                                    />
                                </div>
                            </div>

                            <!-- Most Active Users Section -->
                            <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                                <h3 class="mb-6 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                    <UserCheck class="h-5 w-5 text-green-600 dark:text-green-400" />
                                    Most Active Users
                                </h3>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="rounded-lg bg-gray-50 p-4 text-center transition-colors hover:bg-gray-100 dark:bg-slate-700/50 dark:hover:bg-slate-700">
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">!!COMING SOON!!</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Data Used: 0MB</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Online Time: 0 hrs</p>
                                    </div>      
                                </div>
                            </div>
                            </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                        <h3 class="mb-6 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                            <Activity class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            Recent Activity
                        </h3>
                        <div class="grid gap-6 md:grid-cols-3">
                            <!-- New Users -->
                            <div class="space-y-3">
                                <h4 class="flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400">
                                    <Users class="h-4 w-4" />
                                    New Users
                                </h4>
                                <div class="space-y-2">
                                    <div v-for="u in stats.recent_activity.latest_users" :key="u.username" 
                                        class="rounded-lg bg-gray-50 p-3 text-sm transition-colors hover:bg-gray-100 dark:bg-slate-700/50 dark:hover:bg-slate-700">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ u.username }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ u.type }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Payments -->
                            <div class="space-y-3">
                                <h4 class="flex items-center gap-2 text-sm font-medium text-green-600 dark:text-green-400">
                                    Recent Payments in ({{ currency || 'KES' }})
                                </h4>
                                <div class="space-y-2">
                                    <div v-for="p in stats.recent_activity.latest_payments" :key="p.receipt_number"
                                        class="rounded-lg bg-gray-50 p-3 text-sm transition-colors hover:bg-gray-100 dark:bg-slate-700/50 dark:hover:bg-slate-700">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ currency || 'KES' }} {{ p.amount }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ p.paid_at }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Latest Leads -->
                            <div class="space-y-3">
                                <h4 class="flex items-center gap-2 text-sm font-medium text-purple-600 dark:text-purple-400">
                                    <Inbox class="h-4 w-4" />
                                    Latest Leads
                                </h4>
                                <div class="space-y-2">
                                    <div v-for="l in stats.recent_activity.latest_leads" :key="l.name"
                                        class="rounded-lg bg-gray-50 p-3 text-sm transition-colors hover:bg-gray-100 dark:bg-slate-700/50 dark:hover:bg-slate-700">
                                        <p class="font-medium text-gray-900 dark:text-white">{{ l.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ l.status }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Options (Admin Only) -->
                    <div v-if="user.role === 'admin'" class="flex justify-end gap-3">
                        <button @click="window.print()" 
                            class="rounded-xl bg-white px-6 py-3 font-medium text-gray-700 shadow-md transition-all hover:shadow-lg dark:bg-slate-800 dark:text-gray-200">
                            Print Dashboard
                        </button>
                        <a :href="route('dashboard.export', { format: 'excel' })"
                            class="rounded-xl bg-green-600 px-6 py-3 font-medium text-white shadow-md transition-all hover:bg-green-700 hover:shadow-lg">
                            Export Excel
                        </a>
                        <a :href="route('dashboard.export', { format: 'pdf' })"
                            class="rounded-xl bg-red-600 px-6 py-3 font-medium text-white shadow-md transition-all hover:bg-red-700 hover:shadow-lg">
                            Export PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Smooth animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.group:hover {
    animation: fadeIn 0.3s ease-out;
}
</style>
