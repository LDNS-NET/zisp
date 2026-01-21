<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { Head, usePage, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import {
    Users,
    User,
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
    Activity,
    Check,
    X,
    Zap,
    Clock,
    AlertCircle,
    PieChart,
    LineChart,
    UserCheck,
    EyeOff,
    Cpu,
    Gauge,
    ArrowUpDown,
    Wifi,
    Phone,
    ServerCrash,
    WifiOff,
    AlertTriangle,
    CloudOff,
    ZapOff,
    MapPin,
    Ticket,
    TrendingUp,
    TrendingDown,
    Briefcase,
    Radio
} from 'lucide-vue-next';

const props = defineProps(['stats', 'currency']);
const page = usePage();
const user = usePage().props.auth.user;
const expiresAt = ref(page.props.subscription_expires_at || null);
const countdown = ref('');
const daysRemaining = ref(0);

// Timer refs for cleanup
const countdownInterval = ref(null);
const pollingInterval = ref(null);

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
    countdownInterval.value = setInterval(updateCountdown, 1000);

    // Poll for real-time stats updates every 30 seconds
    const POLL_INTERVAL = 30000;
    
    pollingInterval.value = setInterval(() => {
        // Skip polling if tab is not visible to save resources
        if (document.hidden) {
            return;
        }

        router.reload({
            only: ['stats'],
            preserveScroll: true,
            preserveState: true,
        });
    }, POLL_INTERVAL);
});

onUnmounted(() => {
    if (countdownInterval.value) clearInterval(countdownInterval.value);
    if (pollingInterval.value) clearInterval(pollingInterval.value);
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
    
    //console.log('Payments Chart Data:', paymentsData);
    
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
    
    //console.log('User Types Chart Data from Backend:', typesData);
    //console.log('Props Stats:', props.stats);
    
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
    
    //console.log('Chart Series Being Passed:', chartSeries);
    
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

const isTenantAdmin = computed(() => {
    return user.roles.includes('tenant_admin');
});
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

            <!-- Network Pulse Section -->
            <div class="relative mt-6 px-4 sm:px-6 lg:px-8 mb-8">
                <div class="mx-auto max-w-7xl">
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <!-- Network Health Score -->
                        <div class="rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800 border border-white/50 dark:border-slate-700/50 relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                                <Activity class="h-24 w-24 text-blue-500" />
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="relative h-20 w-20 flex-shrink-0">
                                    <svg class="h-full w-full transform -rotate-90" viewBox="0 0 36 36">
                                        <!-- Background Circle -->
                                        <path class="text-gray-100 dark:text-slate-700" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                        <!-- Value Circle -->
                                        <path :class="[
                                            'transition-all duration-1000 ease-out',
                                            (stats.network_health || 100) > 80 ? 'text-green-500' :
                                            (stats.network_health || 100) > 60 ? 'text-yellow-500' : 'text-red-500'
                                        ]"
                                        :stroke-dasharray="(stats.network_health || 100) + ', 100'"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" />
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center flex-col">
                                        <span class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.network_health || 100 }}</span>
                                        <div class="flex items-center gap-0.5 group/sqi relative">
                                            <span class="text-[0.6rem] font-bold uppercase text-gray-500">SQI</span>
                                            <Info class="h-2 w-2 text-gray-400 cursor-help" />
                                            <div class="absolute left-1/2 bottom-full mb-1 -translate-x-1/2 hidden group-hover/sqi:block w-32 rounded bg-slate-900 p-1 text-[0.5rem] text-white shadow-xl z-50 text-center">
                                                Service Quality Index: A score of network stability and user experience.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Network Health</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Service Quality Index</p>
                                    <div class="mt-2 inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30">
                                        {{ (stats.network_health || 100) > 80 ? 'Excellent' : (stats.network_health || 100) > 60 ? 'Fair' : 'Critical' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Live Events Ticker -->
                        <div class="md:col-span-1 lg:col-span-2 rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800 border border-white/50 dark:border-slate-700/50 relative overflow-hidden">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white mb-4 uppercase tracking-wider">
                                <Radio class="h-4 w-4 text-red-500 animate-pulse" />
                                Live Network Activity
                            </h3>
                            <div class="space-y-3 relative">
                                <!-- Gradient fade for list -->
                                <div class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-white dark:from-slate-800 to-transparent pointer-events-none z-10"></div>
                                
                                <div v-if="stats.live_events && stats.live_events.length > 0">
                                    <div v-for="(event, index) in stats.live_events" :key="index" class="flex items-center gap-3 animate-fade-in-up" :style="{ animationDelay: index * 100 + 'ms' }">
                                        <div :class="[
                                            'h-2 w-2 rounded-full flex-shrink-0',
                                            event.severity === 'critical' ? 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.6)]' :
                                            event.severity === 'warning' ? 'bg-orange-500' : 'bg-blue-500'
                                        ]"></div>
                                        <p class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300 truncate">
                                            {{ event.message }}
                                        </p>
                                        <span class="text-xs text-gray-400 font-mono whitespace-nowrap">{{ event.time }}</span>
                                    </div>
                                </div>
                                <div v-else class="flex items-center justify-center py-4 text-gray-500 text-sm">
                                    <Activity class="h-4 w-4 mr-2 opacity-50" /> No recent events
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="space-y-4">
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

                        <!-- Revenue Card (only tenant_admin) -->
                        <div v-if="isTenantAdmin" class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 p-4 shadow-xl transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:-translate-y-1 dark:from-purple-600 dark:to-purple-700 border border-purple-400/30 sm:col-span-2 lg:col-span-1">
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
                    </div>
                    
                    <!-- System Health & Performance -->
                    <div class="py-10 mb-10 mt-10 rounded-3xl bg-white/80 backdrop-blur-xl p-6 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="flex items-center gap-3 text-lg font-bold text-gray-900 dark:text-white">
                                <div class="rounded-lg bg-gradient-to-br from-teal-500 to-emerald-600 p-2">
                                    <Activity class="h-5 w-5 text-white" />
                                </div>
                                System Health & Performance
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="flex h-3 w-3 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                </span>
                                <span class="text-xs font-semibold text-green-600 dark:text-green-400">Live Monitoring</span>
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- CPU Load -->
                            <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 p-5 dark:from-slate-700/50 dark:to-slate-800/50 border border-slate-200 dark:border-slate-600 hover:shadow-lg transition-all">
                                <div class="absolute right-0 top-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                                    <Cpu class="h-16 w-16 text-blue-600 dark:text-blue-400 transform rotate-12" />
                                </div>
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="rounded-lg bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                        <Cpu class="h-5 w-5" />
                                    </div>
                                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Avg CPU Load</span>
                                </div>
                                <div class="flex items-end gap-2 mb-2">
                                    <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.system_health?.cpu_avg || 0 }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-500"
                                         :style="{ width: (stats.system_health?.cpu_avg || 0) + '%' }"></div>
                                </div>
                            </div>

                            <!-- Memory Usage -->
                            <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 p-5 dark:from-slate-700/50 dark:to-slate-800/50 border border-slate-200 dark:border-slate-600 hover:shadow-lg transition-all">
                                <div class="absolute right-0 top-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                                    <Gauge class="h-16 w-16 text-purple-600 dark:text-purple-400 transform -rotate-12" />
                                </div>
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="rounded-lg bg-purple-500/10 p-2 text-purple-600 dark:text-purple-400">
                                        <Gauge class="h-5 w-5" />
                                    </div>
                                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">Avg Memory</span>
                                </div>
                                <div class="flex items-end gap-2 mb-2">
                                    <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.system_health?.memory_avg || 0 }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 h-2 rounded-full transition-all duration-500"
                                         :style="{ width: (stats.system_health?.memory_avg || 0) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Smart Network & User Insights -->
                    <div class="py-10 mb-10 mt-10 rounded-3xl bg-white/80 backdrop-blur-xl p-6 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                         <div class="flex items-center justify-between mb-6">
                            <h3 class="flex items-center gap-3 text-lg font-bold text-gray-900 dark:text-white">
                                <div class="rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 p-2">
                                    <CloudOff class="h-5 w-5 text-white" />
                                </div>
                                Smart Network & User Insights
                            </h3>
                            <span class="rounded-full bg-amber-100 dark:bg-amber-900/30 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-700">
                                AI Risk Analysis
                            </span>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                            <!-- Infrastructure Risks -->
                            <div class="py-10 mb-10 mt-10 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 bg-white/50 dark:bg-slate-700/30">
                                <h4 class="mb-4 flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                                    <ServerCrash class="h-4 w-4 text-red-500" />
                                    Infrastructure Risks
                                </h4>
                                
                                <div v-if="stats.smart_insights?.router_risks?.length > 0" class="space-y-3">
                                    <div v-for="risk in stats.smart_insights.router_risks" :key="risk.name" 
                                        class="flex items-start gap-3 rounded-xl bg-red-50 p-3 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30">
                                        <AlertTriangle class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400 mt-0.5" />
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ risk.name }}</p>
                                            <p class="text-xs text-gray-600 dark:text-gray-300">{{ risk.ip }}</p>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <span v-for="issue in risk.issues" :key="issue" class="inline-flex items-center rounded-md bg-red-100 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                                    {{ issue }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                                    <div class="mb-2 rounded-full bg-green-100 p-3 dark:bg-green-900/20">
                                        <Wifi class="h-6 w-6 text-green-600 dark:text-green-400" />
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">All Systems Normal</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">No critical infrastructure risks detected.</p>
                                </div>
                            </div>

                            <!-- Revenue Intelligence (only tenant_admin) -->
                            <div v-if="isTenantAdmin" class="py-10 mb-10 mt-10 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 bg-white/50 dark:bg-slate-700/30">
                                <h4 class="mb-4 flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                                    <Briefcase class="h-4 w-4 text-emerald-500" />
                                    Revenue Intelligence
                                </h4>
                                
                                <div class="space-y-4">
                                    <!-- Forecast -->
                                    <div class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-900/30">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 uppercase">Forecast (7 Days)</span>
                                                <div class="group/hint relative">
                                                    <Info class="h-3 w-3 text-emerald-400 cursor-help" />
                                                    <div class="absolute left-0 bottom-full mb-1 hidden group-hover/hint:block w-40 rounded bg-slate-900 p-1.5 text-[0.6rem] text-white z-50 shadow-2xl">
                                                        Total expected revenue from users whose subscriptions expire in the next 7 days.
                                                    </div>
                                                </div>
                                            </div>
                                            <TrendingUp class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                        </div>
                                        <div class="flex items-end gap-1">
                                            <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ currency }} {{ stats.smart_insights?.business_insights?.forecast_revenue }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            From {{ stats.smart_insights?.business_insights?.critical_expiries }} renewals due in 3 days.
                                        </p>
                                    </div>

                                    <!-- Missed Opportunity -->
                                    <div class="rounded-xl bg-purple-50 p-4 dark:bg-purple-900/20 border border-purple-100 dark:border-purple-900/30">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-xs font-semibold text-purple-700 dark:text-purple-300 uppercase">Missed (Last 7 Days)</span>
                                                <div class="group/hint relative">
                                                    <Info class="h-3 w-3 text-purple-400 cursor-help" />
                                                    <div class="absolute left-0 bottom-full mb-1 hidden group-hover/hint:block w-40 rounded bg-slate-900 p-1.5 text-[0.6rem] text-white z-50 shadow-2xl">
                                                        Revenue lost from users who expired in the last 7 days and haven't renewed.
                                                    </div>
                                                </div>
                                            </div>
                                            <TrendingDown class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                        </div>
                                        <div class="flex items-end gap-1">
                                            <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ currency }} {{ stats.smart_insights?.business_insights?.missed_revenue }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Uncollected from {{ stats.smart_insights?.business_insights?.churn_candidates }} expired users.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- User Experience Risks -->
                            <div class="py-10 mb-10 mt-10 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 bg-white/50 dark:bg-slate-700/30">
                                <h4 class="mb-4 flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                                    <ZapOff class="h-4 w-4 text-orange-500" />
                                    User Experience Alerts
                                </h4>
                                
                                <div v-if="stats.smart_insights?.user_risks?.length > 0" class="space-y-3">
                                    <div v-for="userRisk in stats.smart_insights.user_risks" :key="userRisk.username"
                                        class="flex items-start gap-3 rounded-xl bg-orange-50 p-3 dark:bg-orange-900/20 border border-orange-100 dark:border-orange-900/30">
                                        <WifiOff class="h-5 w-5 flex-shrink-0 text-orange-600 dark:text-orange-400 mt-0.5" />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between mb-1">
                                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ userRisk.username }}</p>
                                                <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                    <MapPin class="h-3 w-3" />
                                                    <span>{{ userRisk.location }}</span>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-2 text-xs font-mono text-gray-500 dark:text-gray-400 mb-2">
                                                <Phone class="h-3 w-3" />
                                                {{ userRisk.phone }}
                                            </div>

                                            <p class="text-xs font-medium text-orange-700 dark:text-orange-300 mb-1">{{ userRisk.issue }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ userRisk.detail }}</p>

                                            <!-- Ticket Status Badge -->
                                            <div class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium border"
                                                :class="userRisk.ticket_status === 'Ticket Auto-Opened' 
                                                    ? 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800' 
                                                    : 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800'">
                                                <Ticket class="h-3 w-3" />
                                                {{ userRisk.ticket_status }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                                    <div class="mb-2 rounded-full bg-green-100 p-3 dark:bg-green-900/20">
                                        <UserCheck class="h-6 w-6 text-green-600 dark:text-green-400" />
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">Excellent Experience</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">No users flagged with connectivity issues.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                        <!-- Network Users -->
                        <div class="rounded-3xl py-10 mb-10 mt-10 bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
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

                    <!-- Phase 2: Geographic & Traffic Intelligence -->
                    <div class="mt-10 gap-8 mb-10 rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="flex items-center gap-3 text-xl font-bold text-gray-900 dark:text-white">
                                <div class="rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 p-2">
                                    <MapPin class="h-5 w-5 text-white" />
                                </div>
                                Geographic & Traffic Intelligence
                            </h3>
                            <span class="rounded-full bg-pink-100 dark:bg-pink-900/30 px-3 py-1 text-xs font-bold uppercase tracking-wider text-pink-600 dark:text-pink-400 border border-pink-200 dark:border-pink-700">
                                Location AI
                            </span>
                        </div>

                        <div class="grid gap-8 lg:grid-cols-2">
                            <!-- Zone Performance -->
                            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-6 bg-white/50 dark:bg-slate-700/30">
                                <h4 class="mb-4 flex items-center gap-2 font-bold text-gray-900 dark:text-white">
                                    <RadioTower class="h-4 w-4 text-gray-500" />
                                    Top Network Zones
                                </h4>
                                <div v-if="stats.zone_analytics && stats.zone_analytics.length > 0" class="space-y-4">
                                    <div v-for="(zone, index) in stats.zone_analytics" :key="zone.name" class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                {{ index + 1 }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ zone.name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ zone.users }} active users</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ currency }} {{ zone.revenue.toLocaleString() }}</p>
                                            <p class="text-[10px] uppercase tracking-wider text-gray-400">Yield: {{ currency }} {{ zone.efficiency }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="py-8 text-center text-sm text-gray-500 text-gray-400">
                                    No location data available yet.
                                </div>
                            </div>

                            <div class="space-y-6">
                                <!-- Traffic AI -->
                                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-6 bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 text-center relative overflow-hidden">
                                     <div class="absolute top-0 right-0 p-4 opacity-10">
                                        <Clock class="h-24 w-24 text-indigo-500" />
                                    </div>
                                    <p class="text-xs font-bold uppercase tracking-widest text-indigo-500 mb-2">Predicted Peak Traffic</p>
                                    <h3 class="text-3xl font-black text-indigo-900 dark:text-indigo-100 mb-1">
                                        {{ stats.traffic_ai?.peak_period || 'Analyzing...' }}
                                    </h3>
                                    <p class="text-sm text-indigo-600 dark:text-indigo-300 flex items-center justify-center gap-2">
                                        <Activity class="h-4 w-4" />
                                        Network Load: <span class="font-bold">{{ stats.traffic_ai?.load_level || 'Normal' }}</span>
                                    </p>
                                </div>

                                <!-- Financial Health (ARPU) -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4 bg-white/50 dark:bg-slate-700/30">
                                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">ARPU</p>
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ currency }} {{ stats.financial_health?.arpu || 0 }}</p>
                                        <p class="text-[10px] text-green-500 font-medium">Avg Revenue / User</p>
                                    </div>
                                    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 p-4 bg-white/50 dark:bg-slate-700/30">
                                        <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Active Yield</p>
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ currency }} {{ stats.financial_health?.active_yield || 0 }}</p>
                                        <p class="text-[10px] text-blue-500 font-medium">Revenue / Online</p>
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
                                <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-slate-700/50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usage</th>
                                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white/50 dark:bg-slate-800/50">
                                            <tr v-for="consumer in stats.top_consumers" :key="consumer.username" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="h-8 w-8 rounded-full bg-gradient-to-r from-green-400 to-green-600 flex items-center justify-center text-white font-bold text-xs ring-2 ring-white dark:ring-slate-800">
                                                            {{ consumer.username.substring(0, 2).toUpperCase() }}
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ consumer.username }}</div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ consumer.ip }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center gap-2">
                                                        <ArrowUpDown class="h-4 w-4 text-blue-500" />
                                                        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ consumer.usage_formatted }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                                    <div class="flex items-center justify-end gap-2 text-sm text-gray-500 dark:text-gray-400">
                                                        <Phone class="h-3 w-3" />
                                                        <span>{{ consumer.phone }}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="!stats.top_consumers || stats.top_consumers.length === 0">
                                                <td colspan="3" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                                    <Wifi class="h-8 w-8 mx-auto mb-2 opacity-50" />
                                                    <p>No active sessions found</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="py-10 mb-10 mt-10 rounded-3xl bg-white/80 backdrop-blur-xl p-8 shadow-2xl dark:bg-slate-800/80 border border-white/50 dark:border-slate-700/50">
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
