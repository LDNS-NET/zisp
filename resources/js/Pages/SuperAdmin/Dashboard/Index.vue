<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { 
    User, Banknote, MessageSquare, Building2, Activity, ArrowRight, 
    TrendingUp, TrendingDown, CreditCard, UserPlus, AlertCircle, CheckCircle 
} from 'lucide-vue-next';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Line, Doughnut } from 'vue-chartjs';
import { 
    Chart as ChartJS, 
    Title, 
    Tooltip, 
    Legend, 
    LineElement, 
    PointElement, 
    CategoryScale, 
    LinearScale, 
    ArcElement 
} from 'chart.js';

// Register ChartJS components
ChartJS.register(
    Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, ArcElement
);

const page = usePage();
const props = computed(() => page.props ?? {});
const metrics = computed(() => props.value.metrics ?? {});
const charts = computed(() => props.value.charts ?? {});
const recentActivity = computed(() => props.value.recentActivity ?? []);

// Chart Data: Revenue Trend
const revenueTrendData = computed(() => {
    const data = charts.value.revenue_trend ?? [];
    return {
        labels: data.map(d => d.date),
        datasets: [{
            label: 'Revenue (KES)',
            data: data.map(d => d.total),
            borderColor: '#4f46e5', // Indigo 600
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.4
        }]
    };
});

const revenueTrendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            mode: 'index',
            intersect: false,
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: { color: '#f3f4f6' }
        },
        x: {
            grid: { display: false }
        }
    }
};

// Chart Data: Revenue by Gateway
const gatewayData = computed(() => {
    const data = charts.value.revenue_by_gateway ?? [];
    return {
        labels: data.map(d => d.payment_method.toUpperCase()),
        datasets: [{
            data: data.map(d => d.total),
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'], // Green, Blue, Yellow, Red
            borderWidth: 0
        }]
    };
});

const gatewayOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'right' }
    }
};

// Helper for formatting currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(value);
};

const systemHealth = ref(null);
const loadingHealth = ref(true);

const fetchHealth = async () => {
    loadingHealth.value = true;
    try {
        const response = await axios.get(route('superadmin.system.health'));
        systemHealth.value = response.data;
    } catch (error) {
        console.error('Failed to fetch system health', error);
    } finally {
        loadingHealth.value = false;
    }
};

onMounted(() => {
    fetchHealth();
});
</script>

<template>
    <Head title="Super Admin Dashboard" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard Overview
                </h2>
                <div class="text-sm text-gray-500">
                    Last updated: {{ new Date().toLocaleTimeString() }}
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- 1. Key Metrics Cards -->
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Revenue -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="rounded-lg bg-indigo-50 p-3 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300">
                            <Banknote class="h-6 w-6" />
                        </div>
                        <span :class="[
                            'flex items-center text-sm font-medium',
                            metrics.finance?.growth >= 0 ? 'text-green-600' : 'text-red-600'
                        ]">
                            <component :is="metrics.finance?.growth >= 0 ? TrendingUp : TrendingDown" class="mr-1 h-4 w-4" />
                            {{ Math.abs(metrics.finance?.growth ?? 0) }}%
                        </span>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</h3>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatCurrency(metrics.finance?.total_revenue ?? 0) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ formatCurrency(metrics.finance?.this_month ?? 0) }} this month
                        </p>
                    </div>
                </div>

                <!-- Active Tenants -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="rounded-lg bg-blue-50 p-3 text-blue-600 dark:bg-blue-900/50 dark:text-blue-300">
                            <Building2 class="h-6 w-6" />
                        </div>
                        <span :class="[
                            'flex items-center text-sm font-medium',
                            metrics.tenants?.growth >= 0 ? 'text-green-600' : 'text-red-600'
                        ]">
                            <component :is="metrics.tenants?.growth >= 0 ? TrendingUp : TrendingDown" class="mr-1 h-4 w-4" />
                            {{ Math.abs(metrics.tenants?.growth ?? 0) }}%
                        </span>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tenants</h3>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ metrics.tenants?.total ?? 0 }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ metrics.tenants?.active ?? 0 }} Active · {{ metrics.tenants?.suspended ?? 0 }} Suspended
                        </p>
                    </div>
                </div>

                <!-- End Users -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="rounded-lg bg-green-50 p-3 text-green-600 dark:bg-green-900/50 dark:text-green-300">
                            <User class="h-6 w-6" />
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                            </span>
                            <span class="text-xs font-medium text-gray-500">{{ metrics.users?.online ?? 0 }} Online</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total End Users</h3>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ metrics.users?.total ?? 0 }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            Across all tenants
                        </p>
                    </div>
                </div>

                <!-- SMS Sent -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="rounded-lg bg-purple-50 p-3 text-purple-600 dark:bg-purple-900/50 dark:text-purple-300">
                            <MessageSquare class="h-6 w-6" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total SMS Sent</h3>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ metrics.sms?.total ?? 0 }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ metrics.sms?.this_month ?? 0 }} sent this month
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. Charts Section -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Revenue Trend -->
                <div class="col-span-2 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Trend (30 Days)</h3>
                    <div class="h-72">
                        <Line :data="revenueTrendData" :options="revenueTrendOptions" />
                    </div>
                </div>

                <!-- Revenue by Gateway -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue by Gateway</h3>
                    <div class="h-72 flex items-center justify-center">
                        <Doughnut :data="gatewayData" :options="gatewayOptions" />
                    </div>
                </div>
            </div>

            <!-- 3. Recent Activity & System Health -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Recent Activity -->
                <div class="col-span-2 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
                        <Link :href="route('superadmin.payments.index')" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            View All
                        </Link>
                    </div>
                    
                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            <li v-for="(item, index) in recentActivity" :key="index">
                                <div class="relative pb-8">
                                    <span v-if="index !== recentActivity.length - 1" class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span :class="[
                                                'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-800',
                                                item.type === 'payment' ? 'bg-green-500' : 'bg-blue-500'
                                            ]">
                                                <component 
                                                    :is="item.type === 'payment' ? CreditCard : UserPlus" 
                                                    class="h-5 w-5 text-white" 
                                                    aria-hidden="true" 
                                                />
                                            </span>
                                        </div>
                                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                            <div>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    <span class="font-medium text-gray-900 dark:text-white">{{ item.title }}</span>: {{ item.description }}
                                                </p>
                                            </div>
                                            <div class="whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                                <time :datetime="item.timestamp">{{ item.time }}</time>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- System Status -->
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">System Status</h3>
                        <button @click="fetchHealth" class="text-xs text-indigo-600 hover:text-indigo-500 font-medium" :disabled="loadingHealth">
                            {{ loadingHealth ? 'Checking...' : 'Refresh' }}
                        </button>
                    </div>
                    
                    <div v-if="loadingHealth && !systemHealth" class="space-y-4 animate-pulse">
                        <div v-for="i in 4" :key="i" class="h-8 bg-gray-100 dark:bg-gray-700 rounded"></div>
                    </div>
                    
                    <div v-else-if="systemHealth" class="space-y-4">
                        <!-- Database -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <component :is="systemHealth.database.status === 'healthy' ? CheckCircle : AlertCircle" 
                                    :class="['h-5 w-5 mr-2', systemHealth.database.status === 'healthy' ? 'text-green-500' : 'text-red-500']" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Database</span>
                            </div>
                            <span :class="[
                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                systemHealth.database.status === 'healthy' ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-red-50 text-red-700 ring-red-600/20'
                            ]">
                                {{ systemHealth.database.message }}
                            </span>
                        </div>
                        
                        <!-- Cache -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <component :is="systemHealth.cache.status === 'healthy' ? CheckCircle : AlertCircle" 
                                    :class="['h-5 w-5 mr-2', systemHealth.cache.status === 'healthy' ? 'text-green-500' : 'text-red-500']" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Cache</span>
                            </div>
                            <span :class="[
                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                systemHealth.cache.status === 'healthy' ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-red-50 text-red-700 ring-red-600/20'
                            ]">
                                {{ systemHealth.cache.message }}
                            </span>
                        </div>
                        
                        <!-- Disk Space -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <component :is="systemHealth.disk.status === 'healthy' ? CheckCircle : AlertCircle" 
                                    :class="['h-5 w-5 mr-2', systemHealth.disk.status === 'healthy' ? 'text-green-500' : 'text-yellow-500']" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Disk Space</span>
                            </div>
                            <span :class="[
                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                systemHealth.disk.status === 'healthy' ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-yellow-50 text-yellow-700 ring-yellow-600/20'
                            ]">
                                {{ systemHealth.disk.message }}
                            </span>
                        </div>
                        
                        <!-- IntaSend -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <component :is="systemHealth.services.intasend.status === 'healthy' ? CheckCircle : AlertCircle" 
                                    :class="['h-5 w-5 mr-2', systemHealth.services.intasend.status === 'healthy' ? 'text-green-500' : 'text-red-500']" />
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">IntaSend API</span>
                            </div>
                            <span :class="[
                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                systemHealth.services.intasend.status === 'healthy' ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-red-50 text-red-700 ring-red-600/20'
                            ]">
                                {{ systemHealth.services.intasend.message }}
                            </span>
                        </div>
                        
                        <div class="pt-2 text-[10px] text-gray-400 text-right italic">
                            Last checked: {{ systemHealth.last_check }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
