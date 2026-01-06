<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, usePage, Link, router } from '@inertiajs/vue3';
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

    // Poll for real-time stats updates every 5 seconds
    setInterval(() => {
        router.reload({
            only: ['stats'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 5000);
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
            <!-- Hero Section with Premium Gradient -->
            <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 dark:from-blue-900 dark:via-indigo-900 dark:to-purple-900 shadow-2xl">
                <!-- Animated Background Pattern -->
                <div class="absolute inset-0 opacity-20">
                    <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.5) 1px, transparent 1px); background-size: 50px 50px;"></div>
                </div>
                
                <!-- Decorative Blobs -->
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full blur-3xl -mr-48 -mt-48"></div>
                <div class="absolute bottom-0 left-0 w-80 h-80 bg-white/5 rounded-full blur-3xl -ml-40 -mb-40"></div>
                
                <div class="relative px-4 py-8 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-7xl">
                        <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                            <!-- Welcome Message -->
                            <div class="space-y-2 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-widest text-blue-100">Welcome Back</p>
                                <h1 class="text-2xl font-bold text-white sm:text-3xl lg:text-4xl leading-tight">
                                    {{ greeting }}, <span class="bg-gradient-to-r from-blue-200 to-indigo-200 bg-clip-text text-transparent">{{ user.name }}</span>!
                                </h1>
                                <p class="text-sm text-blue-100">
                                    Your network intelligence dashboard is ready
                                </p>
                            </div>

                            <!-- Subscription Badge -->
                            <div v-if="expiresAt && daysRemaining <= 5" class="flex-shrink-0">
                                <div :class="[
                                    'group relative overflow-hidden rounded-2xl p-4 backdrop-blur-xl transition-all duration-300 hover:scale-105 hover:-translate-y-1 cursor-pointer',
                                    'shadow-lg border',
                                    subscriptionStatus === 'expired' ? 'bg-red-500/20 ring-2 ring-red-400 border-red-300/50' :
                                    subscriptionStatus === 'critical' ? 'bg-orange-500/20 ring-2 ring-orange-400 border-orange-300/50' :
                                    subscriptionStatus === 'warning' ? 'bg-yellow-500/20 ring-2 ring-yellow-400 border-yellow-300/50' :
                                    'bg-white/20 ring-2 ring-white/30 border-white/40'
                                ]">
                                    <div class="flex items-center gap-3">
                                        <div :class="[
                                            'flex h-11 w-11 items-center justify-center rounded-lg flex-shrink-0 shadow-lg',
                                            subscriptionStatus === 'expired' ? 'bg-red-500' :
                                            subscriptionStatus === 'critical' ? 'bg-orange-500' :
                                            subscriptionStatus === 'warning' ? 'bg-yellow-500' :
                                            'bg-green-500'
                                        ]">
                                            <Clock class="h-6 w-6 text-white" />
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium text-white/90">Subscription Expires</p>
                                            <p class="text-xl font-bold text-white font-mono">{{ countdown }}</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Button -->
                                    <Link v-if="daysRemaining <= 5"
                                        :href="route('subscription.renew')"
                                        class="mt-3 flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-xs font-semibold text-indigo-600 transition-all hover:bg-blue-50 hover:shadow-xl hover:scale-105"
                                    >
                                        <Zap class="h-3.5 w-3.5" />
                                        Renew Now
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="space-y-10">
                    <!-- Quick Stats Grid - Premium Design -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Online Users Card -->
                        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-green-500 to-teal-600 p-4 shadow-xl transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:-translate-y-1 dark:from-green-600 dark:to-teal-700 border border-green-400/30">
                            <div class="absolute top-0 right-0 w-32 h-32 opacity-20">
                                <svg viewBox="0 0 100 100" class="w-full h-full text-white" fill="currentColor">
                                    <circle cx="80" cy="20" r="15" opacity="0.8"/>
                                    <circle cx="90" cy="50" r="20" opacity="0.5"/>
                                    <circle cx="70" cy="80" r="12" opacity="0.6"/>
                                </svg>
                            </div>
                            <div class="absolute right-0 top-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-white/10 blur-2xl"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="rounded-lg bg-white/20 p-2 backdrop-blur-sm border border-white/30">
                                        <Zap class="h-4 w-4 text-white" />
                                    </div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-green-100 bg-white/20 px-2 py-0.5 rounded-full">Live</span>
                                </div>
                                <p class="text-xs font-medium text-teal-100 uppercase tracking-wide">Online Users</p>
                                <p class="mt-2 text-2xl sm:text-3xl font-bold text-white">{{ stats.users.activeUsers }}</p>
                                <div class="mt-2 flex items-center gap-2 text-xs text-teal-100">
                                    <UserCheck class="h-3 w-3" />
                                    <span>of {{ stats.users.total }} total</span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Users Card -->
                        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-4 shadow-xl transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:-translate-y-1 dark:from-blue-600 dark:to-blue-700 border border-blue-400/30">
                            <div class="absolute top-0 right-0 w-32 h-32 opacity-20">
                                <svg viewBox="0 0 100 100" class="w-full h-full text-white" fill="currentColor">
                                    <rect x="70" y="10" width="20" height="20" opacity="0.8"/>
                                    <rect x="60" y="40" width="30" height="15" opacity="0.5"/>
                                    <circle cx="85" cy="75" r="8" opacity="0.6"/>
                                </svg>
                            </div>
                            <div class="absolute right-0 top-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-white/10 blur-2xl"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="rounded-lg bg-white/20 p-2 backdrop-blur-sm border border-white/30">
                                        <Users class="h-4 w-4 text-white" />
                                    </div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-blue-100 bg-white/20 px-2 py-0.5 rounded-full">Total</span>
                                </div>
                                <p class="text-xs font-medium text-blue-100 uppercase tracking-wide">Total Users</p>
                                <p class="mt-2 text-2xl sm:text-3xl font-bold text-white">{{ stats.users.total }}</p>
                                <div class="mt-2 flex items-center gap-2 text-xs text-blue-100">
                                    <Activity class="h-3 w-3" />
                                    <span>{{ stats.users.activeUsers }} active now</span>
                                </div>
                            </div>
                        </div>

                        <!-- MikroTik Devices Card -->
                        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-yellow-500 to-yellow-600 p-4 shadow-xl transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:-translate-y-1 dark:from-yellow-700 dark:to-yellow-800 border border-yellow-400/30">
                            <div class="absolute top-0 right-0 w-32 h-32 opacity-20">
                                <svg viewBox="0 0 100 100" class="w-full h-full text-white" fill="currentColor">
                                    <path d="M 50 10 L 90 50 L 50 90 L 10 50 Z" opacity="0.8"/>
                                    <circle cx="80" cy="30" r="10" opacity="0.5"/>
                                </svg>
                            </div>
                            <div class="absolute right-0 top-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-white/10 blur-2xl"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="rounded-lg bg-white/20 p-2 backdrop-blur-sm border border-white/30">
                                        <RadioTower class="h-4 w-4 text-white" />
                                    </div>
                                    <span :class="['text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-full', stats.mikrotiks.connected === 0 ? 'text-red-900 bg-red-200' : 'text-amber-100 bg-white/20']">
                                        {{ stats.mikrotiks.connected === 0 ? 'Critical' : 'Online' }}
                                    </span>
                                </div>
                                <p class="text-xs font-medium text-amber-100 uppercase tracking-wide">Devices</p>
                                <p class="mt-2 text-2xl sm:text-3xl font-bold text-white">{{ stats.mikrotiks.total }}</p>
                                <div class="mt-2 flex items-center gap-2 text-xs text-amber-100">
                                    <Check class="h-3 w-3" />
                                    <span v-if="stats.mikrotiks.connected === 0" class="text-red-300">All Offline</span>
                                    <span v-else>{{ stats.mikrotiks.connected }} online</span>
                                </div>
                            </div>
                        </div>

                        <!-- Support Tickets Card -->
                        <div class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 p-4 shadow-xl transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:-translate-y-1 dark:from-indigo-600 dark:to-indigo-700 border border-indigo-400/30">
                            <div class="absolute top-0 right-0 w-32 h-32 opacity-20">
                                <svg viewBox="0 0 100 100" class="w-full h-full text-white" fill="currentColor">
                                    <polygon points="50,10 90,30 85,75 50,90 15,75 10,30" opacity="0.8"/>
                                    <circle cx="50" cy="50" r="20" opacity="0.5"/>
                                </svg>
                            </div>
                            <div class="absolute right-0 top-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-white/10 blur-2xl"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="rounded-lg bg-white/20 p-2 backdrop-blur-sm border border-white/30">
                                        <Ticket class="h-4 w-4 text-white" />
                                    </div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-100 bg-white/20 px-2 py-0.5 rounded-full">Support</span>
                                </div>
                                <p class="text-xs font-medium text-indigo-100 uppercase tracking-wide">Support Tickets</p>
                                <p class="mt-2 text-2xl sm:text-3xl font-bold text-white">{{ stats.tickets.open }}</p>
                                <div class="mt-2 flex items-center gap-2 text-xs text-indigo-100">
                                    <AlertCircle class="h-3 w-3" />
                                    <span>{{ stats.tickets.assigned_to_me }} assigned</span>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue Card (if admin/cashier) -->
                        <div v-if="user.role === 'admin' || user.role === 'cashier'" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 p-4 shadow-xl transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:-translate-y-1 dark:from-purple-600 dark:to-purple-700 border border-purple-400/30 sm:col-span-2 lg:col-span-1">
                            <div class="absolute top-0 right-0 w-32 h-32 opacity-20">
                                <svg viewBox="0 0 100 100" class="w-full h-full text-white" fill="currentColor">
                                    <circle cx="70" cy="20" r="12" opacity="0.8"/>
                                    <circle cx="85" cy="45" r="15" opacity="0.5"/>
                                    <circle cx="75" cy="75" r="10" opacity="0.6"/>
                                </svg>
                            </div>
                            <div class="absolute right-0 top-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-white/10 blur-2xl"></div>
                            <div class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="rounded-lg bg-white/20 p-2 backdrop-blur-sm border border-white/30">
                                        <DollarSign class="h-4 w-4 text-white" />
                                    </div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-purple-100 bg-white/20 px-2 py-0.5 rounded-full">Revenue</span>
                                </div>
                                <p class="text-xs font-medium text-purple-100 uppercase tracking-wide">Total Revenue</p>
                                <p class="mt-2 text-xl sm:text-2xl font-bold text-white">{{ currency || 'KES' }} {{ stats.payments.total_amount }}</p>
                                <div class="mt-2 flex items-center gap-2 text-xs text-purple-100">
                                    <TrendingUp class="h-3 w-3" />
                                    <span>{{ stats.payments.count }} payments</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Stats Sections -->
                    <div class="grid gap-8 lg:grid-cols-2">
                        <!-- Network Users -->
                        <div class="rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                            <h3 class="mb-8 flex items-center gap-3 text-xl font-bold text-gray-900 dark:text-white">
                                <div class="rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-2">
                                    <Users class="h-5 w-5 text-white" />
                                </div>
                                Network Users Breakdown
                            </h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 p-5 dark:from-blue-900/20 dark:to-blue-800/20 border border-blue-200 dark:border-blue-700/50 hover:shadow-lg transition-all">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Hotspot Users</p>
                                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.users.hotspot }}</p>
                                        </div>
                                        <RadioTower class="h-8 w-8 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                                    </div>
                                    <div class="h-1 bg-blue-200 dark:bg-blue-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-blue-500 to-blue-600" :style="{width: stats.users.total > 0 ? (stats.users.hotspot / stats.users.total * 100) + '%' : '0%'}"></div>
                                    </div>
                                </div>
                                <div class="rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100 p-5 dark:from-indigo-900/20 dark:to-indigo-800/20 border border-indigo-200 dark:border-indigo-700/50 hover:shadow-lg transition-all">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">PPPoE Users</p>
                                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.users.pppoe }}</p>
                                        </div>
                                        <User class="h-8 w-8 text-indigo-600 dark:text-indigo-400 flex-shrink-0" />
                                    </div>
                                    <div class="h-1 bg-indigo-200 dark:bg-indigo-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-600" :style="{width: stats.users.total > 0 ? (stats.users.pppoe / stats.users.total * 100) + '%' : '0%'}"></div>
                                    </div>
                                </div>
                                <div class="rounded-2xl bg-gradient-to-br from-purple-50 to-purple-100 p-5 dark:from-purple-900/20 dark:to-purple-800/20 border border-purple-200 dark:border-purple-700/50 hover:shadow-lg transition-all">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Static Users</p>
                                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.users.static }}</p>
                                        </div>
                                        <Server class="h-8 w-8 text-purple-600 dark:text-purple-400 flex-shrink-0" />
                                    </div>
                                    <div class="h-1 bg-purple-200 dark:bg-purple-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-purple-500 to-purple-600" :style="{width: stats.users.total > 0 ? (stats.users.static / stats.users.total * 100) + '%' : '0%'}"></div>
                                    </div>
                                </div>
                                <div class="rounded-2xl bg-gradient-to-br from-red-50 to-red-100 p-5 dark:from-red-900/20 dark:to-red-800/20 border border-red-200 dark:border-red-700/50 hover:shadow-lg transition-all">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Expired Users</p>
                                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.users.expired }}</p>
                                        </div>
                                        <X class="h-8 w-8 text-red-600 dark:text-red-400 flex-shrink-0" />
                                    </div>
                                    <div class="h-1 bg-red-200 dark:bg-red-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-red-500 to-red-600" :style="{width: stats.users.total > 0 ? (stats.users.expired / stats.users.total * 100) + '%' : '0%'}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leads & Tickets -->
                        <div class="rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                            <h3 class="mb-8 flex items-center gap-3 text-xl font-bold text-gray-900 dark:text-white">
                                <div class="rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 p-2">
                                    <Inbox class="h-5 w-5 text-white" />
                                </div>
                                Leads & Support Center
                            </h3>
                            <div class="space-y-5">
                                <!-- Leads -->
                                <div class="rounded-2xl border-2 border-gray-200 dark:border-gray-700 p-6 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-lg transition-all bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-700 dark:to-slate-800">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Leads</p>
                                            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.leads.total }}</p>
                                        </div>
                                        <div class="rounded-xl bg-gradient-to-br from-yellow-500 to-yellow-600 p-3">
                                            <Inbox class="h-5 w-5 text-white" />
                                        </div>
                                    </div>
                                    <div class="flex gap-6 pt-4 border-t border-gray-200 dark:border-gray-600">
                                        <div class="flex-1">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">Pending</p>
                                            <p class="mt-2 text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.leads.pending }}</p>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">Converted</p>
                                            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.leads.converted }}</p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Tickets -->
                                <div class="rounded-2xl border-2 border-gray-200 dark:border-gray-700 p-6 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-lg transition-all bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-700 dark:to-slate-800">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Support Tickets</p>
                                            <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.tickets.open + stats.tickets.closed }}</p>
                                        </div>
                                        <div class="rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 p-3">
                                            <Ticket class="h-5 w-5 text-white" />
                                        </div>
                                    </div>
                                    <div class="flex gap-6 pt-4 border-t border-gray-200 dark:border-gray-600">
                                        <div class="flex-1">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">Open</p>
                                            <p class="mt-2 text-2xl font-bold text-orange-600 dark:text-orange-400">{{ stats.tickets.open }}</p>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">Resolved</p>
                                            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.tickets.closed }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics & Charts Section -->
                    <div class="space-y-8">
                        <div class="flex items-center justify-between">
                            <h3 class="flex items-center gap-3 text-2xl font-bold text-gray-900 dark:text-white">
                                <div class="rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 p-2.5">
                                    <BarChart2 class="h-6 w-6 text-white" />
                                </div>
                                Analytics & Insights
                            </h3>
                        </div>

                        <!-- Charts Grid -->
                        <div class="grid gap-8 lg:grid-cols-2">
                            <!-- Monthly Income Chart -->
                            <div class="rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                                <div class="mb-8 flex items-center justify-between">
                                    <h4 class="flex items-center gap-3 text-lg font-bold text-gray-900 dark:text-white">
                                        <div class="rounded-lg bg-gradient-to-br from-green-500 to-green-600 p-2">
                                            <TrendingUp class="h-5 w-5 text-white" />
                                        </div>
                                        Monthly Revenue
                                    </h4>
                                    <span class="rounded-full bg-gradient-to-r from-green-100 to-green-200 dark:from-green-900/30 dark:to-green-800/30 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-green-700 dark:text-green-400 border border-green-200 dark:border-green-700">
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
                            <div class="rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                                <div class="mb-8 flex items-center justify-between">
                                    <h4 class="flex items-center gap-3 text-lg font-bold text-gray-900 dark:text-white">
                                        <div class="rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 p-2">
                                            <LineChart class="h-5 w-5 text-white" />
                                        </div>
                                        Network User Types
                                    </h4>
                                    <span class="rounded-full bg-gradient-to-r from-blue-100 to-blue-200 dark:from-blue-900/30 dark:to-blue-800/30 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-700">
                                        Current Year
                                    </span>
                                </div>
                                
                                <!-- Data Preview -->
                                <div v-if="stats?.users" class="mb-6 flex flex-wrap gap-4 p-4 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600">
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600"></div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Hotspot: <strong class="text-gray-900 dark:text-white font-bold">{{ stats.users.hotspot }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-gradient-to-r from-pink-500 to-pink-600"></div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">PPPoE: <strong class="text-gray-900 dark:text-white font-bold">{{ stats.users.pppoe }}</strong></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="h-3 w-3 rounded-full bg-gradient-to-r from-amber-500 to-amber-600"></div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Static: <strong class="text-gray-900 dark:text-white font-bold">{{ stats.users.static }}</strong></span>
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

                        <div class="grid gap-8 lg:grid-cols-2">
                            <!-- Package Utilization Chart -->
                            <div class="rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                                <div class="mb-8 flex items-center justify-between">
                                    <h4 class="flex items-center gap-3 text-lg font-bold text-gray-900 dark:text-white">
                                        <div class="rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 p-2">
                                            <PieChart class="h-5 w-5 text-white" />
                                        </div>
                                        Package Distribution
                                    </h4>
                                    <span class="rounded-full bg-gradient-to-r from-purple-100 to-purple-200 dark:from-purple-900/30 dark:to-purple-800/30 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-700">
                                        Current Status
                                    </span>
                                </div>
                                <div class="mx-auto">
                                    <VueApexCharts
                                        type="donut"
                                        height="350"
                                        :options="packageChartOptions"
                                        :series="packageChartSeries"
                                    />
                                </div>
                            </div>

                            <!-- Most Active Users Section -->
                            <div class="rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                                <h3 class="mb-8 flex items-center gap-3 text-lg font-bold text-gray-900 dark:text-white">
                                    <div class="rounded-lg bg-gradient-to-br from-green-500 to-green-600 p-2">
                                        <UserCheck class="h-5 w-5 text-white" />
                                    </div>
                                    Most Active Users
                                </h3>
                                <div class="rounded-2xl border-2 border-gray-200 dark:border-gray-700 p-8 text-center bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-700 dark:to-slate-800 hover:shadow-lg transition-all">
                                    <div class="text-6xl mb-3">🚀</div>
                                    <p class="text-xl font-bold text-gray-900 dark:text-white mb-2">Coming Soon!</p>
                                    <p class="text-gray-600 dark:text-gray-400">Advanced user analytics and activity tracking</p>
                                </div>
                            </div>
                            </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                        <h3 class="mb-8 flex items-center gap-3 text-2xl font-bold text-gray-900 dark:text-white">
                            <div class="rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-600 p-2.5">
                                <Activity class="h-6 w-6 text-white" />
                            </div>
                            Recent Activity
                        </h3>
                        <div class="grid gap-8 md:grid-cols-3">
                            <!-- New Users -->
                            <div class="space-y-4">
                                <h4 class="flex items-center gap-3 text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                    <div class="h-3 w-3 rounded-full bg-gradient-to-r from-blue-500 to-blue-600"></div>
                                    New Users
                                </h4>
                                <div v-if="stats.recent_activity?.latest_users?.length > 0" class="space-y-3">
                                    <div v-for="u in stats.recent_activity?.latest_users" :key="u.username" 
                                        class="rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-700 dark:to-slate-800 p-4 hover:shadow-md hover:border-blue-300 dark:hover:border-blue-600 transition-all cursor-pointer">
                                        <p class="font-bold text-gray-900 dark:text-white">{{ u.username }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 uppercase font-semibold tracking-wider">{{ u.type }}</p>
                                    </div>
                                </div>
                                <div v-else class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 text-center text-gray-500 dark:text-gray-400">
                                    <p class="text-sm font-medium">No new users yet</p>
                                </div>
                            </div>

                            <!-- Recent Payments -->
                            <div class="space-y-4">
                                <h4 class="flex items-center gap-3 text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                    <div class="h-3 w-3 rounded-full bg-gradient-to-r from-green-500 to-green-600"></div>
                                    Recent Payments
                                </h4>
                                <div v-if="stats.recent_activity?.latest_payments?.length > 0" class="space-y-3">
                                    <div v-for="p in stats.recent_activity?.latest_payments" :key="p.receipt_number"
                                        class="rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-700 dark:to-slate-800 p-4 hover:shadow-md hover:border-green-300 dark:hover:border-green-600 transition-all cursor-pointer">
                                        <p class="font-bold text-green-700 dark:text-green-400">{{ currency || 'KES' }} {{ p.amount }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ p.paid_at }}</p>
                                    </div>
                                </div>
                                <div v-else class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 text-center text-gray-500 dark:text-gray-400">
                                    <p class="text-sm font-medium">No recent payments</p>
                                </div>
                            </div>

                            <!-- Latest Leads -->
                            <div class="space-y-4">
                                <h4 class="flex items-center gap-3 text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                    <div class="h-3 w-3 rounded-full bg-gradient-to-r from-purple-500 to-purple-600"></div>
                                    Latest Leads
                                </h4>
                                <div v-if="stats.recent_activity?.latest_leads?.length > 0" class="space-y-3">
                                    <div v-for="l in stats.recent_activity?.latest_leads" :key="l.name"
                                        class="rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-700 dark:to-slate-800 p-4 hover:shadow-md hover:border-purple-300 dark:hover:border-purple-600 transition-all cursor-pointer">
                                        <p class="font-bold text-gray-900 dark:text-white">{{ l.name }}</p>
                                        <p :class="[
                                            'text-xs mt-1 px-2 py-1 rounded-full font-bold uppercase tracking-wider inline-block',
                                            l.status === 'Converted' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                                            l.status === 'Pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' :
                                            'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                        ]">
                                            {{ l.status }}
                                        </p>
                                    </div>
                                </div>
                                <div v-else class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 text-center text-gray-500 dark:text-gray-400">
                                    <p class="text-sm font-medium">No recent leads</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Options (Admin Only) -->
                    <div v-if="user.role === 'admin'" class="flex flex-wrap justify-center gap-4">
                        <button @click="window.print()" 
                            class="flex items-center gap-2 rounded-xl bg-white dark:bg-slate-800 px-6 py-3 font-semibold text-gray-700 dark:text-gray-200 shadow-lg hover:shadow-xl transition-all hover:scale-105 border-2 border-gray-200 dark:border-gray-700">
                            <FileText class="h-5 w-5" />
                            Print Dashboard
                        </button>
                        <a :href="route('dashboard.export', { format: 'excel' })"
                            class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-green-500 to-green-600 px-6 py-3 font-semibold text-white shadow-lg hover:shadow-xl transition-all hover:scale-105">
                            <Coins class="h-5 w-5" />
                            Export to Excel
                        </a>
                        <a :href="route('dashboard.export', { format: 'pdf' })"
                            class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-red-500 to-red-600 px-6 py-3 font-semibold text-white shadow-lg hover:shadow-xl transition-all hover:scale-105">
                            <FileText class="h-5 w-5" />
                            Export to PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Smooth animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.group:hover {
    animation: fadeInUp 0.3s ease-out;
}
</style>
