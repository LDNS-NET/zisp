<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { BarChart3, TrendingUp, Activity, AlertTriangle, Download, Users } from 'lucide-vue-next';
import { Line, Doughnut, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    topConsumers: Array,
    bandwidthTrends: Array,
    protocolBreakdown: Array,
    anomalies: Array,
    period: String,
});

const selectedPeriod = ref(props.period || '7days');

// Period options
const periodOptions = [
    { value: '24hours', label: '24 Hours' },
    { value: '7days', label: '7 Days' },
    { value: '30days', label: '30 Days' },
    { value: '90days', label: '90 Days' },
];

// Change period
function changePeriod(period) {
    selectedPeriod.value = period;
    router.visit(route('analytics.traffic', { period }), {
        preserveState: true,
        preserveScroll: true,
    });
}

// Format bytes
function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const k = 1024;
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + units[i];
}

// Bandwidth Trends Chart Data
const bandwidthChartData = computed(() => {
    const labels = props.bandwidthTrends.map(item => {
        if (item.hour !== undefined) {
            return `${item.hour}:00`;
        }
        return new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    return {
        labels,
        datasets: [
            {
                label: 'Download',
                data: props.bandwidthTrends.map(item => item.total_in / (1024 * 1024)), // Convert to MB
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
            },
            {
                label: 'Upload',
                data: props.bandwidthTrends.map(item => item.total_out / (1024 * 1024)),
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4,
            },
        ],
    };
});

const bandwidthChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
        title: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' MB';
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function(value) {
                    return value + ' MB';
                }
            }
        }
    }
};

// Protocol Breakdown Chart Data
const protocolChartData = computed(() => {
    return {
        labels: props.protocolBreakdown.map(item => item.protocol || 'Unknown'),
        datasets: [{
            data: props.protocolBreakdown.map(item => item.percentage),
            backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(139, 92, 246, 0.8)',
                'rgba(236, 72, 153, 0.8)',
            ],
        }],
    };
});

const protocolChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right',
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    return context.label + ': ' + context.parsed + '%';
                }
            }
        }
    }
};

// Get severity badge color
function getSeverityColor(severity) {
    return severity === 'critical' 
        ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
        : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
}

// Export data
function exportData() {
    // TODO: Implement CSV export
    console.log('Export functionality coming soon');
}
</script>

<template>
    <Head title="Traffic Analytics" />
    
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <BarChart3 class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                            Traffic Analytics
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Advanced bandwidth usage insights and anomaly detection
                        </p>
                    </div>
                </div>

                <button
                    @click="exportData"
                    class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                >
                    <Download class="w-4 h-4" />
                    <span>Export</span>
                </button>
            </div>
        </template>

        <!-- Period Selector -->
        <div class="mb-6 flex gap-2">
            <button
                v-for="option in periodOptions"
                :key="option.value"
                @click="changePeriod(option.value)"
                :class="[
                    'px-4 py-2 rounded-lg font-medium transition-colors',
                    selectedPeriod === option.value
                        ? 'bg-blue-600 text-white'
                        : 'bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700'
                ]"
            >
                {{ option.label }}
            </button>
        </div>

        <!-- Bandwidth Trends Chart -->
        <div class="mb-6 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <TrendingUp class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Bandwidth Trends
                </h3>
            </div>
            <div class="h-80">
                <Line :data="bandwidthChartData" :options="bandwidthChartOptions" />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Top Consumers -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6">
                <div class="flex items-center gap-2 mb-4">
                    <Users class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Top Consumers
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase border-b border-gray-200 dark:border-slate-700">
                            <tr>
                                <th class="pb-3 text-left">User</th>
                                <th class="pb-3 text-right">Usage</th>
                                <th class="pb-3 text-right">Download</th>
                                <th class="pb-3 text-right">Upload</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <tr
                                v-for="(user, index) in topConsumers"
                                :key="user.user_id"
                                class="border-b border-gray-100 dark:border-slate-700/50 last:border-0"
                            >
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-xs font-bold text-blue-600 dark:text-blue-400">
                                            {{ index + 1 }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ user.username }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.full_name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-right font-semibold text-gray-900 dark:text-white">
                                    {{ user.usage_formatted }}
                                </td>
                                <td class="py-3 text-right text-gray-600 dark:text-gray-400">
                                    {{ formatBytes(user.total_download) }}
                                </td>
                                <td class="py-3 text-right text-gray-600 dark:text-gray-400">
                                    {{ formatBytes(user.total_upload) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Protocol Breakdown -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6">
                <div class="flex items-center gap-2 mb-4">
                    <Activity class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Protocol Breakdown
                    </h3>
                </div>

                <div v-if="protocolBreakdown.length > 0" class="h-64">
                    <Doughnut :data="protocolChartData" :options="protocolChartOptions" />
                </div>
                <div v-else class="h-64 flex items-center justify-center text-gray-500 dark:text-gray-400">
                    No protocol data available
                </div>
            </div>
        </div>

        <!-- Anomalies -->
        <div v-if="anomalies.length > 0" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <AlertTriangle class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Usage Anomalies Detected
                </h3>
            </div>

            <div class="space-y-3">
                <div
                    v-for="anomaly in anomalies"
                    :key="anomaly.user_id"
                    class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-900/50 rounded-lg border border-gray-200 dark:border-slate-700"
                >
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-medium text-gray-900 dark:text-white">{{ anomaly.username }}</span>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', getSeverityColor(anomaly.severity)]">
                                {{ anomaly.severity }}
                            </span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ anomaly.type === 'spike' ? '↑' : '↓' }}
                            {{ Math.abs(anomaly.deviation_percent) }}% {{ anomaly.type === 'spike' ? 'increase' : 'decrease' }} from normal usage
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                            Recent avg: {{ formatBytes(anomaly.recent_avg) }} | Historical avg: {{ formatBytes(anomaly.historical_avg) }}
                        </div>
                    </div>
                    <button class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Investigate
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
