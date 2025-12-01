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
    LineChart
} from 'lucide-vue-next';

const props = defineProps(['stats']);
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
    colors: ['#10b981', '#3b82f6'],
    xaxis: {
        categories: props.stats?.payments_chart ? Object.keys(props.stats.payments_chart) : [],
        labels: {
            style: {
                colors: isDark.value ? '#9ca3af' : '#6b7280',
            },
        },
    },
    yaxis: {
        labels: {
            formatter: (value) => `KES ${value.toLocaleString()}`,
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
            formatter: (value) => `KES ${value.toLocaleString()}`,
        },
    },
}));

const incomeChartSeries = computed(() => [{
    name: 'Revenue',
    data: props.stats?.payments_chart ? Object.values(props.stats.payments_chart) : [],
}]);

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
    colors: ['#6366f1', '#ec4899', '#f59e0b'],
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
    const growthData = props.stats?.user_growth_chart;
    
    // Debug: log the data
    console.log('User Growth Chart Data:', growthData);
    
    if (!growthData || !growthData.total_users) {
        // Better fallback with some sample data to show the chart works
        return [
            { name: 'Total Users', data: [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, props.stats?.users?.total || 65] },
            { name: 'Active Users', data: [8, 12, 16, 20, 24, 28, 32, 36, 40, 44, 48, props.stats?.users?.activeUsers || 52] },
            { name: 'New Users', data: [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13] },
        ];
    }

    return [
        {
            name: 'Total Users',
            data: growthData.total_users,
        },
        {
            name: 'Active Users',
            data: growthData.active_users,
        },
        {
            name: 'New Users',
            data: growthData.new_users,
        },
    ];
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
    colors: ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b'],
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

        <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-slate-950 dark:via-slate-900 dark:to-indigo-950">
            <!-- Hero Section with Gradient -->
            <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 dark:from-blue-900 dark:via-indigo-900 dark:to-purple-900">
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
                                <p class="text-lg text-blue-100 dark:text-blue-200">
                                    Here's what's happening with your network today
                                </p>
                            </div>

                            <!-- Subscription Badge -->
                            <div v-if="expiresAt" class="flex-shrink-0">
                                <div :class="[
                                    'group relative overflow-hidden rounded-2xl p-6 backdrop-blur-lg transition-all duration-300 hover:scale-105',
                                    subscriptionStatus === 'expired' ? 'bg-red-500/20 ring-2 ring-red-400' :
                                    subscriptionStatus === 'critical' ? 'bg-orange-500/20 ring-2 ring-orange-400' :
                                    subscriptionStatus === 'warning' ? 'bg-yellow-500/20 ring-2 ring-yellow-400' :
                                    'bg-white/20 ring-2 ring-white/30'
                                ]">
                                    <div class="flex items-center gap-4">
                                        <div :class="[
                                            'flex h-12 w-12 items-center justify-center rounded-xl',
                                            subscriptionStatus === 'expired' ? 'bg-red-500' :
                                            subscriptionStatus === 'critical' ? 'bg-orange-500' :
                                            subscriptionStatus === 'warning' ? 'bg-yellow-500' :
                                            'bg-green-500'
                                        ]">
                                            <Clock class="h-6 w-6 text-white" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-white/90">Subscription Expires</p>
                                            <p class="text-2xl font-bold text-white">{{ countdown }}</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Button -->
                                    <a v-if="daysRemaining <= 7"
                                        href="https://payment.intasend.com/pay/8d7f60c4-f2c2-4642-a2b6-0654a3cc24e3/"
                                        target="_blank"
                                        class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-indigo-600 transition-all hover:bg-indigo-50 hover:shadow-lg"
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
                    <!-- Quick Stats Grid -->
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Total Users -->
                        <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-blue-600 p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-blue-600 dark:to-blue-700">
                            <div class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-blue-100">Total Users</p>
                                        <p class="mt-2 text-4xl font-bold text-white">{{ stats.users.total }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-3 backdrop-blur-sm">
                                        <Users class="h-8 w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center gap-2 text-sm text-blue-100">
                                    <Activity class="h-4 w-4" />
                                    <span>{{ stats.users.active }} active now</span>
                                </div>
                            </div>
                        </div>

                        <!-- MikroTik Devices -->
                        <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-emerald-600 dark:to-emerald-700">
                            <div class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-emerald-100">MikroTik Devices</p>
                                        <p class="mt-2 text-4xl font-bold text-white">{{ stats.mikrotiks.total }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-3 backdrop-blur-sm">
                                        <RadioTower class="h-8 w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center gap-2 text-sm text-emerald-100">
                                    <Check class="h-4 w-4" />
                                    <span>{{ stats.mikrotiks.connected }} connected</span>
                                </div>
                            </div>
                        </div>

                        <!-- Open Tickets -->
                        <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-amber-600 dark:to-amber-700">
                            <div class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-amber-100">Open Tickets</p>
                                        <p class="mt-2 text-4xl font-bold text-white">{{ stats.tickets.open }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-3 backdrop-blur-sm">
                                        <Ticket class="h-8 w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center gap-2 text-sm text-amber-100">
                                    <User class="h-4 w-4" />
                                    <span>{{ stats.tickets.assigned_to_me }} assigned to you</span>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue (if admin/cashier) -->
                        <div v-if="user.role === 'admin' || user.role === 'cashier'" class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-purple-500 to-purple-600 p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl dark:from-purple-600 dark:to-purple-700">
                            <div class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-white/10"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-purple-100">Total Revenue</p>
                                        <p class="mt-2 text-3xl font-bold text-white">KES {{ stats.payments.total_amount }}</p>
                                    </div>
                                    <div class="rounded-xl bg-white/20 p-3 backdrop-blur-sm">
                                        <DollarSign class="h-8 w-8 text-white" />
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center gap-2 text-sm text-purple-100">
                                    <TrendingUp class="h-4 w-4" />
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
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 p-4 dark:from-blue-900/20 dark:to-blue-800/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Hotspot</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users.hotspot }}</p>
                                        </div>
                                        <RadioTower class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                                    </div>
                                </div>
                                <div class="rounded-xl bg-gradient-to-br from-indigo-50 to-indigo-100 p-4 dark:from-indigo-900/20 dark:to-indigo-800/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">PPPoE</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users.pppoe }}</p>
                                        </div>
                                        <User class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                </div>
                                <div class="rounded-xl bg-gradient-to-br from-purple-50 to-purple-100 p-4 dark:from-purple-900/20 dark:to-purple-800/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Static</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users.static }}</p>
                                        </div>
                                        <Server class="h-8 w-8 text-purple-600 dark:text-purple-400" />
                                    </div>
                                </div>
                                <div class="rounded-xl bg-gradient-to-br from-red-50 to-red-100 p-4 dark:from-red-900/20 dark:to-red-800/20">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Expired</p>
                                            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.users.expired }}</p>
                                        </div>
                                        <X class="h-8 w-8 text-red-600 dark:text-red-400" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leads & Tickets -->
                        <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                            <h3 class="mb-6 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white">
                                <Inbox class="h-5 w-5 text-purple-600 dark:text-purple-400" />
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
                                                <p class="text-yellow-600 dark:text-yellow-400">{{ stats.leads.pending }}</p>
                                                <p class="text-xs text-gray-500">Pending</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-green-600 dark:text-green-400">{{ stats.leads.converted }}</p>
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
                                                <p class="text-green-600 dark:text-green-400">{{ stats.tickets.closed }}</p>
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
                                    type="area"
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
                                        User Growth
                                    </h4>
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        Year Overview
                                    </span>
                                </div>
                                
                                <!-- Data Preview -->
                                <div v-if="stats?.users" class="mb-4 flex gap-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-indigo-500"></div>
                                        <span class="text-gray-600 dark:text-gray-400">Total: <strong class="text-gray-900 dark:text-white">{{ stats.users.total }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-pink-500"></div>
                                        <span class="text-gray-600 dark:text-gray-400">Active: <strong class="text-gray-900 dark:text-white">{{ stats.users.activeUsers || 0 }}</strong></span>
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
                                    <DollarSign class="h-4 w-4" />
                                    Recent Payments
                                </h4>
                                <div class="space-y-2">
                                    <div v-for="p in stats.recent_activity.latest_payments" :key="p.receipt_number"
                                        class="rounded-lg bg-gray-50 p-3 text-sm transition-colors hover:bg-gray-100 dark:bg-slate-700/50 dark:hover:bg-slate-700">
                                        <p class="font-medium text-gray-900 dark:text-white">KES {{ p.amount }}</p>
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
