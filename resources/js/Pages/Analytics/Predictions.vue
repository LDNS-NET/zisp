<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    TrendingUp, 
    AlertTriangle, 
    Users, 
    DollarSign, 
    RefreshCw, 
    ExternalLink,
    TrendingDown,
    BrainCircuit,
    ArrowRight
} from 'lucide-vue-next';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';
import { Line, Bar } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    churnRisk: Array,
    revenueForecast: Object,
});

const form = useForm({});
const isRefreshing = ref(false);

const refreshPredictions = () => {
    isRefreshing.value = true;
    form.post(route('analytics.predictions.refresh'), {
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

// Revenue Forecast Chart Data
const forecastChartData = computed(() => {
    if (!props.revenueForecast || !props.revenueForecast.factors) return null;

    const historical = props.revenueForecast.factors.historical_data || [];
    const forecast = props.revenueForecast.factors.forecast_data || [];

    const labels = [...historical.map(d => d.month), ...forecast.map(d => d.month)];
    const combinedData = [
        ...historical.map(d => d.total),
        ...forecast.map(d => d.predicted_value)
    ];

    // Create a series for colors (Historical vs Forecasted)
    const backgroundColors = [
        ...historical.map(() => 'rgba(59, 130, 246, 0.5)'),
        ...forecast.map(() => 'rgba(16, 185, 129, 0.5)')
    ];

    return {
        labels,
        datasets: [
            {
                label: 'Revenue (Actual & Forecasted)',
                data: combinedData,
                backgroundColor: backgroundColors,
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'white',
                fill: true,
                tension: 0.4,
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            mode: 'index',
            intersect: false,
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: {
                display: false,
            },
        },
        x: {
            grid: {
                display: false,
            },
        },
    },
};

const getRiskColor = (score) => {
    if (score >= 70) return 'text-red-600 bg-red-100 dark:bg-red-900/30 dark:text-red-400';
    if (score >= 40) return 'text-yellow-600 bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-400';
    return 'text-blue-600 bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400';
};

const getRiskLabel = (score) => {
    if (score >= 70) return 'Critical';
    if (score >= 40) return 'Elevated';
    return 'Monitor';
};
</script>

<template>
    <Head title="Predictive Analytics" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <BrainCircuit class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                            Predictive Analytics
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            AI-driven churn prediction and revenue forecasting
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="refreshPredictions"
                        :disabled="isRefreshing"
                        class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50"
                    >
                        <RefreshCw :class="['w-4 h-4', isRefreshing ? 'animate-spin' : '']" />
                        <span>{{ isRefreshing ? 'Recalculating...' : 'Refresh Insights' }}</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Churn Prediction Panel -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="w-5 h-5 text-red-500" />
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                At-Risk Customers (Churn Prediction)
                            </h3>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Next update: 7 days</span>
                    </div>

                    <div class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 dark:bg-slate-900/50 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3">User</th>
                                        <th class="px-6 py-3">Risk Level</th>
                                        <th class="px-6 py-3">Risk Calculation</th>
                                        <th class="px-6 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                                    <tr v-for="prediction in churnRisk" :key="prediction.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center">
                                                    <Users class="w-5 h-5 text-gray-500" />
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ prediction.user ? prediction.user.username : 'Unknown User' }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ prediction.user ? (prediction.user.full_name || prediction.user.phone) : 'N/A' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-2">
                                                    <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium', getRiskColor(prediction.prediction_value)]">
                                                        {{ getRiskLabel(prediction.prediction_value) }} ({{ Math.round(prediction.prediction_value) }}%)
                                                    </span>
                                                </div>
                                                <div class="w-24 bg-gray-200 dark:bg-slate-700 rounded-full h-1.5 overflow-hidden">
                                                    <div 
                                                        class="h-full bg-blue-600 transition-all"
                                                        :style="{ 
                                                            width: prediction.prediction_value + '%',
                                                            backgroundColor: prediction.prediction_value >= 70 ? '#ef4444' : (prediction.prediction_value >= 40 ? '#f59e0b' : '#3b82f6')
                                                        }"
                                                    ></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                                <li v-for="(factor, idx) in prediction.factors" :key="idx" class="flex items-start gap-1">
                                                    <span class="text-gray-400 mt-0.5">•</span>
                                                    {{ factor }}
                                                </li>
                                            </ul>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <Link 
                                                v-if="prediction.user"
                                                :href="route('users.index', { search: prediction.user.username })"
                                                class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                            >
                                                Manage <ArrowRight class="w-4 h-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="churnRisk.length === 0">
                                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                            <Users class="w-12 h-12 mx-auto mb-3 opacity-20" />
                                            <p>No high-risk churn predictions at this time.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Forecast Panel -->
            <div class="space-y-6">
                <!-- Forecast Chart -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-2">
                            <TrendingUp class="w-5 h-5 text-blue-500" />
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Revenue Forecast
                            </h3>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Actual</span>
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Forecast</span>
                        </div>
                    </div>

                    <div v-if="forecastChartData" class="h-64 mb-6">
                        <Line :data="forecastChartData" :options="chartOptions" />
                    </div>
                    <div v-else class="h-64 mb-6 flex items-center justify-center text-gray-500 text-sm">
                        <p class="text-center px-6">Not enough historical data to generate a reliable forecast. Requires at least 2 months of payment data.</p>
                    </div>

                    <div v-if="revenueForecast" class="p-4 bg-gray-50 dark:bg-slate-900/50 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Predicted Monthly Growth</span>
                            <span :class="['text-sm font-bold', revenueForecast.factors.historical_growth >= 0 ? 'text-green-600' : 'text-red-600']">
                                {{ revenueForecast.factors.historical_growth >= 0 ? '+' : '' }}{{ revenueForecast.factors.historical_growth }} /mo
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Confidence Score</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">
                                {{ Math.round(revenueForecast.confidence) }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Business Recommendation -->
                <div class="bg-purple-600 rounded-xl shadow-lg p-6 text-white relative overflow-hidden">
                    <div class="relative z-10">
                        <h4 class="text-lg font-bold mb-2">Smart Recommendation</h4>
                        <p class="text-sm text-purple-100 mb-4" v-if="churnRisk.length > 0">
                            You have {{ churnRisk.length }} customers at elevated risk of leaving. We recommend sending a personalized promotion to these users to increase retention.
                        </p>
                        <p class="text-sm text-purple-100 mb-4" v-else>
                            Great job! Customer retention looks strong. Your network capacity is currently sufficient for the projected growth over the next 3 months.
                        </p>
                        <button class="w-full bg-white text-purple-600 font-bold py-2 rounded-lg hover:bg-purple-50 transition-colors flex items-center justify-center gap-2">
                            View Retention Tools <ExternalLink class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-purple-500 rounded-full opacity-50"></div>
                    <div class="absolute bottom-0 left-0 -ml-4 -mb-4 w-16 h-16 bg-purple-700 rounded-full opacity-50"></div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
