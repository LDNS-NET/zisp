<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import VueApexCharts from 'vue3-apexcharts';
import { 
    DollarSign, TrendingUp, TrendingDown, 
    Users, Activity, Globe, Wallet,
    ArrowUpRight, ArrowDownRight, Info,
    Search, Loader2, ChevronRight, PieChart,
    BarChart, ExternalLink, RefreshCw
} from 'lucide-vue-next';
import { debounce } from 'lodash';

const props = defineProps(['metrics', 'currency', 'filters']);

const isDark = computed(() => document.documentElement.classList.contains('dark'));
const search = ref(props.filters.search || '');
const isRefreshing = ref(false);

// Search Debounce
watch(search, debounce((value) => {
    router.get(route('finance'), { search: value }, { 
        preserveState: true, 
        replace: true,
        only: ['metrics']
    });
}, 500));

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

// Zone Revenue Comparison (Top 5 for chart)
const zoneRevenueOptions = computed(() => ({
    chart: { 
        type: 'donut',
        animations: { enabled: true },
    },
    labels: props.metrics.top_zones.map(z => z.location),
    colors: ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'],
    plotOptions: {
        pie: {
            donut: {
                size: '75%',
                labels: {
                    show: true,
                    name: { show: true, fontSize: '12px', fontWeight: 900 },
                    value: { show: true, fontSize: '18px', fontWeight: 900 },
                    total: {
                        show: true,
                        label: 'Top 5',
                        formatter: () => `${props.currency} ${props.metrics.top_zones.reduce((a, b) => a + Number(b.revenue), 0).toLocaleString()}`
                    }
                }
            }
        }
    },
    legend: { position: 'bottom' },
    stroke: { show: false },
    theme: { mode: isDark.value ? 'dark' : 'light' }
}));

const zoneRevenueSeries = computed(() => props.metrics.top_zones.map(z => Number(z.revenue)));

const refreshData = () => {
    isRefreshing.value = true;
    router.reload({ 
        onFinish: () => isRefreshing.value = false 
    });
};

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
                            <TrendingUp class="h-20 w-20 text-slate-900 dark:text-white" />
                        </div>
                        <p class="text-[0.6rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Net Profit Margin</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white">
                            {{ metrics.fiscal_intelligence.profit_margin.margin }}%
                        </h3>
                        <div class="mt-4 flex items-center justify-between">
                            <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden mr-3">
                                <div class="h-full bg-emerald-500 rounded-full" :style="{ width: metrics.fiscal_intelligence.profit_margin.margin + '%' }"></div>
                            </div>
                            <span class="text-[0.65rem] font-bold text-emerald-500 whitespace-nowrap">OPTIMAL</span>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-3xl bg-white p-6 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800 group hover:-translate-y-1 transition-transform">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <Users class="h-20 w-20 text-slate-900 dark:text-white" />
                        </div>
                        <p class="text-[0.6rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">30-Day Churn Rate</p>
                        <h3 class="text-3xl font-black text-rose-600 dark:text-rose-400">
                            {{ metrics.fiscal_intelligence.churn_rate.rate }}%
                        </h3>
                        <div class="mt-4 flex items-center gap-2 text-xs font-bold text-slate-400">
                            <Activity class="h-3 w-3" />
                            <span>{{ metrics.fiscal_intelligence.churn_rate.count }} SUBS LOST</span>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-3xl bg-white p-6 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800 group hover:-translate-y-1 transition-transform">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <Activity class="h-20 w-20 text-slate-900 dark:text-white" />
                        </div>
                        <p class="text-[0.6rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Avg. Lifetime Value</p>
                        <h3 class="text-3xl font-black text-indigo-600 dark:text-indigo-400">
                            {{ currency }} {{ metrics.fiscal_intelligence.clv.toLocaleString() }}
                        </h3>
                        <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-500">
                            <ArrowUpRight class="h-3 w-3" />
                            <span>PROJECTED LTV</span>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-3xl bg-white p-6 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800 group hover:-translate-y-1 transition-transform">
                        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                            <DollarSign class="h-20 w-20 text-slate-900 dark:text-white" />
                        </div>
                        <p class="text-[0.6rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-2">Operational MRR</p>
                        <h3 class="text-3xl font-black text-slate-900 dark:text-white">
                            {{ currency }} {{ metrics.financial_health.mrr.toLocaleString() }}
                        </h3>
                        <div class="mt-4 flex items-center gap-2 text-xs font-bold text-emerald-500">
                            <ArrowUpRight class="h-3 w-3" />
                            <span>EXPECTED</span>
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
                        <!-- Zone Revenue Heatmap (Compact Visual) -->
                        <div class="rounded-[2.5rem] bg-white p-8 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <h3 class="mb-8 text-lg font-bold text-slate-900 dark:text-white flex items-center gap-3">
                                <PieChart class="h-5 w-5 text-indigo-500" />
                                Top 5 Zones
                            </h3>
                            <div class="mb-4 h-[250px]">
                                <VueApexCharts type="donut" height="100%" :options="zoneRevenueOptions" :series="zoneRevenueSeries" />
                            </div>
                        </div>

                        <!-- Profit Summary Gauge-style -->
                        <div class="rounded-[2.5rem] bg-slate-900 p-8 shadow-xl dark:bg-slate-800 border border-slate-800 overflow-hidden relative">
                            <div class="absolute -right-4 -bottom-4 h-32 w-32 bg-emerald-500/10 rounded-full blur-3xl"></div>
                            <h3 class="mb-8 text-lg font-bold text-white flex items-center gap-3">
                                <BarChart class="h-5 w-5 text-emerald-400" />
                                Profit Summary
                            </h3>
                            <div class="space-y-6">
                                <div>
                                    <div class="flex justify-between text-[0.6rem] font-black tracking-widest text-slate-500 uppercase mb-2">
                                        <span>Total Revenue</span>
                                        <span>{{ currency }} {{ metrics.fiscal_intelligence.profit_margin.revenue.toLocaleString() }}</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-800 rounded-full">
                                        <div class="h-full bg-blue-500 rounded-full" style="width: 100%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[0.6rem] font-black tracking-widest text-slate-500 uppercase mb-2">
                                        <span>Operational Costs</span>
                                        <span>{{ currency }} {{ metrics.fiscal_intelligence.profit_margin.costs.toLocaleString() }}</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-800 rounded-full">
                                        <div class="h-full bg-rose-500 rounded-full" 
                                            :style="{ width: (metrics.fiscal_intelligence.profit_margin.costs / metrics.fiscal_intelligence.profit_margin.revenue * 100) + '%' }">
                                        </div>
                                    </div>
                                </div>
                                <div class="pt-4 border-t border-slate-800">
                                    <p class="text-xs font-bold text-slate-400 mb-1">Estimated Net Profit</p>
                                    <p class="text-2xl font-black text-emerald-400">{{ currency }} {{ metrics.fiscal_intelligence.profit_margin.net_profit.toLocaleString() }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="rounded-[2.5rem] bg-white p-8 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <h3 class="mb-8 text-lg font-bold text-slate-900 dark:text-white flex items-center gap-3">
                                <Activity class="h-5 w-5 text-emerald-500" />
                                Payment Channels
                            </h3>
                            <div class="space-y-6">
                                <div v-for="method in metrics.payment_methods" :key="method.payment_method" class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">{{ method.payment_method }}</span>
                                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ currency }} {{ method.total.toLocaleString() }}</span>
                                    </div>
                                    <div class="h-2 w-full bg-slate-50 dark:bg-slate-800 rounded-full overflow-hidden">
                                        <div :class="['h-full rounded-full transition-all duration-1000', method.payment_method === 'mpesa' ? 'bg-emerald-500' : 'bg-blue-500']" 
                                            :style="{ width: (method.total / metrics.fiscal_intelligence.profit_margin.revenue * 100) + '%' }">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Zone Intelligence Table (The Upgrade) -->
                <div class="mt-10 rounded-[2.5rem] bg-white dark:bg-slate-900 p-0 shadow-2xl border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <div class="p-8 border-b border-slate-50 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
                                <Globe class="h-6 w-6 text-indigo-500" />
                                Zone Intelligence Ledger
                            </h3>
                            <p class="text-sm text-slate-400 font-medium">Detailed revenue breakdown by geographic location.</p>
                        </div>
                        <div class="relative w-full md:w-80">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-300" />
                            <input 
                                v-model="search"
                                type="text" 
                                placeholder="Search zones..."
                                class="w-full pl-12 pr-4 py-3 rounded-2xl bg-slate-50 dark:bg-slate-800 border-none focus:ring-2 focus:ring-indigo-500 text-sm font-bold text-slate-900 dark:text-white"
                            >
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/50 dark:bg-slate-800/50 text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-8 py-5">Zone/Location</th>
                                    <th class="px-8 py-5">Performance Indicator</th>
                                    <th class="px-8 py-5 text-right">Revenue Contribution</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                                <tr v-for="zone in metrics.zone_revenue.data" :key="zone.location" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors group">
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 text-indigo-500 flex items-center justify-center">
                                                <Globe class="h-4 w-4" />
                                            </div>
                                            <span class="font-black text-slate-900 dark:text-white uppercase tracking-tighter">{{ zone.location }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-24 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                <div class="h-full bg-indigo-500 rounded-full" :style="{ width: (zone.revenue / metrics.fiscal_intelligence.profit_margin.revenue * 100 * 5) + '%' }"></div>
                                            </div>
                                            <span class="text-[0.6rem] font-bold text-slate-400">{{ Math.round(zone.revenue / metrics.fiscal_intelligence.profit_margin.revenue * 100) }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <span class="text-lg font-black text-slate-900 dark:text-white">
                                            {{ currency }} {{ Number(zone.revenue).toLocaleString() }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-8 border-t border-slate-50 dark:border-slate-800 flex items-center justify-between">
                        <p class="text-xs font-bold text-slate-400">Showing page {{ metrics.zone_revenue.current_page }} of {{ metrics.zone_revenue.last_page }}</p>
                        <Pagination :links="metrics.zone_revenue.links" />
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
