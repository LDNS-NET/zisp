<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    FileText, Plus, Trash2, Download, 
    Play, Clock, Settings, Layout, 
    Filter, Database, ChevronRight,
    CheckCircle2, AlertCircle, Loader2
} from 'lucide-vue-next';

const props = defineProps(['reports', 'metrics']);

const showCreateModal = ref(false);
const selectedReport = ref(null);

const form = useForm({
    name: '',
    config: {
        metric: '',
        dimensions: [],
        filters: {
            date_range: 'last_30_days',
            status: 'all'
        },
        format: 'excel'
    },
    schedule: {
        frequency: 'none',
        day: 1
    }
});

const submit = () => {
    form.post(route('analytics.reports.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const runReport = (reportId) => {
    router.post(route('analytics.reports.generate', reportId));
};

const getStatusColor = (status) => {
    switch (status) {
        case 'completed': return 'text-green-500 bg-green-50 dark:bg-green-900/20';
        case 'processing': return 'text-blue-500 bg-blue-50 dark:bg-blue-900/20';
        case 'failed': return 'text-red-500 bg-red-50 dark:bg-red-900/20';
        default: return 'text-gray-500 bg-gray-50 dark:bg-gray-900/20';
    }
};

const selectedMetricData = computed(() => {
    return props.metrics[form.config.metric] || null;
});

const user = usePage().props.auth.user;
const isTenantAdmin = computed(() => user.roles.includes('tenant_admin'));
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Custom Report Builder" />

        <div v-if="isTenantAdmin" class="min-h-screen bg-slate-50 dark:bg-slate-950 px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <!-- Header -->
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">Report Builder</h1>
                        <p class="mt-1 text-slate-500 dark:text-slate-400">Design and automate custom business intelligence reports.</p>
                    </div>
                    <button 
                        @click="showCreateModal = true"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 transition-all hover:bg-blue-700 hover:scale-105 active:scale-95"
                    >
                        <Plus class="h-4 w-4" />
                        Create New Report
                    </button>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Saved Reports Library -->
                    <div class="lg:col-span-1 space-y-4">
                        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white">
                            <Layout class="h-5 w-5 text-blue-500" />
                            Saved Reports
                        </h2>
                        
                        <div v-if="reports.length === 0" class="rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 p-8 text-center">
                            <FileText class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-700" />
                            <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-400">No reports saved yet.</p>
                        </div>

                        <div v-for="report in reports" :key="report.id" 
                            @click="selectedReport = report"
                            :class="[
                                'group relative cursor-pointer rounded-2xl border p-5 transition-all hover:shadow-xl dark:border-slate-800',
                                selectedReport?.id === report.id ? 'bg-white dark:bg-slate-900 ring-2 ring-blue-500 shadow-xl' : 'bg-white/50 dark:bg-slate-900/50 hover:bg-white dark:hover:bg-slate-900'
                            ]"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="font-bold text-slate-900 dark:text-white">{{ report.name }}</h3>
                                    <p class="text-xs text-slate-500 uppercase tracking-wider mt-1">
                                        {{ metrics[report.config.metric]?.name || 'Unknown Metric' }}
                                    </p>
                                </div>
                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button @click.stop="runReport(report.id)" class="p-1.5 text-slate-400 hover:text-blue-500 transition-colors">
                                        <Play class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            
                            <div v-if="report.latest_run" class="mt-4 flex items-center justify-between border-t border-slate-100 dark:border-slate-800 pt-3">
                                <div class="flex items-center gap-2">
                                    <span :class="['inline-flex h-2 w-2 rounded-full', report.latest_run.status === 'completed' ? 'bg-green-500' : 'bg-blue-500']"></span>
                                    <span class="text-[0.65rem] font-bold uppercase text-slate-400">{{ report.latest_run.status }}</span>
                                </div>
                                <span class="text-[0.65rem] text-slate-400 font-mono">{{ new Date(report.latest_run.generated_at).toLocaleDateString() }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Builder Detail / Preview Pane -->
                    <div class="lg:col-span-2 space-y-6">
                        <div v-if="selectedReport" class="rounded-3xl bg-white p-8 shadow-xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between mb-8">
                                <div class="flex items-center gap-4">
                                    <div class="rounded-2xl bg-blue-500/10 p-3 text-blue-600 dark:text-blue-400">
                                        <FileText class="h-6 w-6" />
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ selectedReport.name }}</h2>
                                        <p class="text-sm text-slate-500">Configuration Details</p>
                                    </div>
                                </div>
                                <div class="flex gap-3">
                                    <button 
                                        @click="runReport(selectedReport.id)"
                                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-slate-900 hover:scale-105 active:scale-95 transition-transform"
                                    >
                                        <Play class="h-4 w-4" />
                                        Run Now
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-8 md:grid-cols-2">
                                <div class="space-y-6">
                                    <div class="space-y-4">
                                        <h3 class="text-sm font-bold uppercase tracking-widest text-slate-400">Dimensions</h3>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-for="dim in selectedReport.config.dimensions" :key="dim" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                <Database class="h-3.5 w-3.5" />
                                                {{ dim }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <h3 class="text-sm font-bold uppercase tracking-widest text-slate-400">Filters</h3>
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                                <span class="text-sm text-slate-500">Date Range</span>
                                                <span class="text-sm font-bold text-slate-900 dark:text-white">{{ selectedReport.config.filters.date_range.replace('_', ' ') }}</span>
                                            </div>
                                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                                                <span class="text-sm text-slate-500">Status</span>
                                                <span class="text-sm font-bold text-slate-900 dark:text-white capitalize">{{ selectedReport.config.filters.status }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <h3 class="text-sm font-bold uppercase tracking-widest text-slate-400">Execution History</h3>
                                    <div class="space-y-3">
                                        <div v-if="!selectedReport.runs?.length" class="text-sm text-slate-500 py-4 text-center border-2 border-dashed border-slate-100 dark:border-slate-800 rounded-2xl">
                                            No runs recorded.
                                        </div>
                                        <div v-for="run in selectedReport.runs" :key="run.id" class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                                            <div class="flex items-center gap-3">
                                                <div :class="['h-2 w-2 rounded-full', run.status === 'completed' ? 'bg-green-500' : 'bg-red-500']"></div>
                                                <div>
                                                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ new Date(run.generated_at).toLocaleString() }}</p>
                                                    <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest">{{ run.status }}</p>
                                                </div>
                                            </div>
                                            <a v-if="run.status === 'completed'" :href="run.file_path" class="p-2 text-slate-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-all">
                                                <Download class="h-4 w-4" />
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center h-[500px] rounded-3xl border-2 border-dashed border-slate-200 dark:border-slate-800">
                            <Settings class="h-16 w-16 text-slate-200 dark:text-slate-800 mb-4 animate-spin-slow" />
                            <p class="text-slate-500 font-medium">Select a report or create one to get started.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal (Simplified) -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-sm">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-8 shadow-2xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Design Custom Report</h2>
                    <button @click="showCreateModal = false" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Report Name</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 p-3 text-slate-900 focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white" placeholder="e.g., Monthly Revenue by Region">
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Base Metric</label>
                            <select v-model="form.config.metric" class="w-full rounded-xl border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                <option value="" disabled>Select metric...</option>
                                <option v-for="(data, key) in metrics" :key="key" :value="key">{{ data.name }}</option>
                            </select>
                        </div>

                        <div v-if="selectedMetricData">
                            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Dimensions</label>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="dim in selectedMetricData.dimensions" 
                                    :key="dim" 
                                    type="button"
                                    @click="form.config.dimensions.includes(dim) ? form.config.dimensions = form.config.dimensions.filter(d => d !== dim) : form.config.dimensions.push(dim)"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg text-xs font-bold transition-all',
                                        form.config.dimensions.includes(dim) ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/20' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'
                                    ]"
                                >
                                    {{ dim }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-4">
                        <button type="button" @click="showCreateModal = false" class="px-6 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-400">Cancel</button>
                        <button type="submit" class="rounded-xl bg-blue-600 px-8 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700 transition-all">Save Report</button>
                    </div>
                </form>
            </div>
        </div>
        <div v-else class="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-950">
            <div class="text-center p-8 bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-slate-200 dark:border-slate-800">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 mb-4">
                    <FileText class="w-8 h-8" />
                </div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">Unauthorized Access</h2>
                <p class="text-slate-500 dark:text-slate-400">You do not have permission to build or view business reports.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-spin-slow {
    animation: spin 8s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
    animation: fade-in-up 0.5s ease-out forwards;
}
</style>
