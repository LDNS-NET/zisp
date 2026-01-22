<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { 
    DollarSign, TrendingUp, TrendingDown, 
    Users, Activity, Globe, Wallet,
    ArrowUpRight, ArrowDownRight, Info
} from 'lucide-vue-next';

const props = defineProps(['metrics', 'currency']);

const isDark = computed(() => document.documentElement.classList.contains('dark'));

const user = usePage().props.auth.user;
const isTenantAdmin = computed(() => user.roles.includes('tenant_admin'));

// MRR Trend Chart
const mrrChartOptions = computed(() => ({
    chart: {
        type: 'area',
        toolbar: { show: false },
        background: 'transparent',
        animations: { enabled: false }, // Optimize performance
    },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    stroke: { curve: 'smooth', width: 3 },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.5,
            opacityTo: 0.1,
        }
    },
    colors: ['#3b82f6'],
    xaxis: {
        categories: props.metrics.mrr_trend.map(d => d.month),
        labels: { style: { colors: '#64748b' } }
    },
    yaxis: {
        labels: { 
            formatter: (val) => `${props.currency} ${val.toLocaleString()}`,
            style: { colors: '#64748b' }
        }
    },
    grid: { borderColor: isDark.value ? '#1e293b' : '#f1f5f9' },
    tooltip: { theme: isDark.value ? 'dark' : 'light' }
}));

const mrrChartSeries = computed(() => [{
    name: 'MRR',
    data: props.metrics.mrr_trend.map(d => Number(d.total))
}]);

// Cash Flow Forecast Chart
const forecastOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: { show: false },
        animations: { enabled: false }, // Optimize performance
    },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    plotOptions: {
        bar: {
            borderRadius: 8,
            columnWidth: '50%',
            distributed: true
        }
    },
    colors: ['#10b981', '#059669', '#047857'],
    xaxis: {
        categories: props.metrics.cash_flow_forecast.map(d => d.month),
    },
    yaxis: {
        labels: { formatter: (val) => `${props.currency} ${val.toLocaleString()}` }
    },
    legend: { show: false }
}));

const forecastSeries = computed(() => [{
    name: 'Projected Revenue',
    data: props.metrics.cash_flow_forecast.map(d => Number(d.projected))
}]);

// Zone Revenue Comparison
const zoneRevenueOptions = computed(() => ({
    chart: { 
        type: 'donut',
        animations: { enabled: false }, // Optimize performance
    },
    labels: props.metrics.zone_revenue.map(z => z.location),
    colors: ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'],
    legend: { position: 'bottom' },
    responsive: [{
        breakpoint: 480,
        options: { chart: { width: 300 }, legend: { position: 'bottom' } }
    }]
}));

const zoneRevenueSeries = computed(() => props.metrics.zone_revenue.map(z => Number(z.revenue)));

</script>

<template>
    <AuthenticatedLayout>
        <Head title="Financial Intelligence" />

        <div v-if="isTenantAdmin" class="min-h-screen bg-slate-50 dark:bg-slate-950 px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <!-- Header Component -->
                <div class="mb-10 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                Real-time Analytics
                            </span>
                        </div>
                        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">Financial Intelligence</h1>
                        <p class="mt-2 text-lg text-slate-500">Advanced fiscal insights and revenue multi-dimensional analysis.</p>
                    </div>

                    <div class="flex items-center gap-4 bg-white dark:bg-slate-900 p-2 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2 px-4 py-2 bg-slate-50 dark:bg-slate-800 rounded-xl">
                            <Wallet class="h-5 w-5 text-blue-500" />
                            <span class="font-mono font-bold">{{ currency }}</span>
                        </div>
                    </div>
                </div>

                <!-- Core Metrics Grid -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 mb-10">
                    <div class="relative overflow-hidden rounded-3xl bg-white p-6 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800 group hover:-translate-y-1 transition-transform">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <DollarSign class="h-20 w-20 text-slate-900 dark:text-white" />
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">ARPU</p>
                            <div class="group/info relative">
                                <Info class="h-3.5 w-3.5 text-slate-300 cursor-help" />
                                <div class="absolute left-0 bottom-full mb-2 hidden group-hover/info:block w-48 rounded-lg bg-slate-900 p-2 text-[0.65rem] text-white shadow-xl z-50">
                                    Average Revenue Per User: Total potential revenue divided by total user count.
                                </div>
                            </div>
                        </div>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white">
                            {{ currency }} {{ metrics.financial_health.arpu.toLocaleString() }}
                        </h3>
                        <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-500">
                            <ArrowUpRight class="h-3 w-3" />
                            <span>PER USER</span>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-3xl bg-white p-6 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800 group hover:-translate-y-1 transition-transform">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <TrendingUp class="h-20 w-20 text-slate-900 dark:text-white" />
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">MRR</p>
                            <div class="group/info relative">
                                <Info class="h-3.5 w-3.5 text-slate-300 cursor-help" />
                                <div class="absolute left-0 bottom-full mb-2 hidden group-hover/info:block w-48 rounded-lg bg-slate-900 p-2 text-[0.65rem] text-white shadow-xl z-50">
                                    Monthly Recurring Revenue: Expected income from all active subscriptions per month.
                                </div>
                            </div>
                        </div>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white">
                            {{ currency }} {{ metrics.financial_health.mrr.toLocaleString() }}
                        </h3>
                        <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-500">
                            <ArrowUpRight class="h-3 w-3" />
                            <span>MONTHLY</span>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-3xl bg-white p-6 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800 group hover:-translate-y-1 transition-transform">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <Activity class="h-20 w-20 text-slate-900 dark:text-white" />
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Active Yield</p>
                            <div class="group/info relative">
                                <Info class="h-3.5 w-3.5 text-slate-300 cursor-help" />
                                <div class="absolute left-0 bottom-full mb-2 hidden group-hover/info:block w-48 rounded-lg bg-slate-900 p-2 text-[0.65rem] text-white shadow-xl z-50">
                                    The average revenue specifically from your active, paying customers.
                                </div>
                            </div>
                        </div>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white">
                            {{ currency }} {{ metrics.financial_health.active_yield.toLocaleString() }}
                        </h3>
                        <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-500">
                            <ArrowUpRight class="h-3 w-3" />
                            <span>PER ACTIVE</span>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-3xl bg-white p-6 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800 group hover:-translate-y-1 transition-transform">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <Users class="h-20 w-20 text-slate-900 dark:text-white" />
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Status</p>
                        </div>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white">Healthy</h3>
                        <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-500">
                            <ArrowUpRight class="h-3 w-3" />
                            <span>STABLE GROWTH</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- MRR Trend Chart -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="rounded-[2.5rem] bg-white p-8 shadow-2xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <div class="mb-8 flex items-center justify-between">
                                <h3 class="flex items-center gap-3 text-xl font-bold text-slate-900 dark:text-white">
                                    <TrendingUp class="h-6 w-6 text-blue-500" />
                                    Revenue Growth Trend
                                </h3>
                                <Info class="h-5 w-5 text-slate-300 cursor-help" />
                            </div>
                            <div class="h-[350px]">
                                <VueApexCharts type="area" height="100%" :options="mrrChartOptions" :series="mrrChartSeries" />
                            </div>
                        </div>

                        <!-- Cash Flow Forecast -->
                        <div class="rounded-[2.5rem] bg-gradient-to-br from-emerald-500 to-teal-700 p-8 shadow-2xl text-white overflow-hidden relative">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 blur-[100px] rounded-full -mr-32 -mt-32"></div>
                            <div class="relative z-10">
                                <h3 class="text-xl font-bold mb-8">90-Day Cash Flow Projection</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div v-for="item in metrics.cash_flow_forecast" :key="item.month" class="bg-white/10 backdrop-blur-md rounded-3xl p-6 border border-white/20">
                                        <p class="text-xs font-bold uppercase tracking-widest opacity-60 mb-2 text-emerald-100">{{ item.month }}</p>
                                        <p class="text-2xl font-black">{{ currency }} {{ item.projected.toLocaleString() }}</p>
                                        <div class="mt-4 flex items-center gap-1.5 text-[0.65rem] font-bold bg-white/20 px-2 py-1 rounded-full w-fit">
                                            <Activity class="h-3 w-3" />
                                            AI-MODEL PROJECTION
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column Sidebar -->
                    <div class="space-y-8">
                        <!-- Zone Revenue Heatmap -->
                        <div class="rounded-[2.5rem] bg-white p-8 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <h3 class="mb-8 text-lg font-bold text-slate-900 dark:text-white flex items-center gap-3">
                                <Globe class="h-5 w-5 text-indigo-500" />
                                Revenue by Zone
                            </h3>
                            <div class="mb-8">
                                <VueApexCharts type="donut" height="300" :options="zoneRevenueOptions" :series="zoneRevenueSeries" />
                            </div>
                            <div class="space-y-4">
                                <div v-for="zone in metrics.zone_revenue" :key="zone.location" class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 w-2 rounded-full bg-slate-200"></div>
                                        <span class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ zone.location }}</span>
                                    </div>
                                    <span class="text-sm font-bold text-slate-900 dark:text-white">{{ currency }} {{ zone.revenue.toLocaleString() }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="rounded-[2.5rem] bg-slate-900 p-8 shadow-xl dark:bg-slate-800 border border-slate-800">
                            <h3 class="mb-8 text-lg font-bold text-white flex items-center gap-3">
                                <Activity class="h-5 w-5 text-emerald-400" />
                                Payment Channels
                            </h3>
                            <div class="space-y-6">
                                <div v-for="method in metrics.payment_methods" :key="method.payment_method" class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-bold text-slate-400 uppercase tracking-widest">{{ method.payment_method }}</span>
                                        <span class="text-sm font-bold text-white">{{ currency }} {{ method.total.toLocaleString() }}</span>
                                    </div>
                                    <div class="h-2 w-full bg-white/10 rounded-full overflow-hidden">
                                        <div :class="['h-full rounded-full transition-all duration-1000', method.payment_method === 'mpesa' ? 'bg-emerald-500' : 'bg-blue-500']" 
                                            :style="{ width: (method.total / metrics.financial_health.mrr * 100) + '%' }">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-950">
            <div class="text-center p-8 bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-slate-200 dark:border-slate-800">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 mb-4">
                    <Activity class="w-8 h-8" />
                </div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">Unauthorized Access</h2>
                <p class="text-slate-500 dark:text-slate-400">You do not have permission to view this financial data.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}
.animate-float {
    animation: float 6s ease-in-out infinite;
}
</style>
