<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    FileText, Plus, Trash2, Download, 
    Play, Clock, Settings, Layout, 
    Filter, Database, ChevronRight,
    CheckCircle2, AlertCircle, Loader2,
    MessageSquare, List, History, User,
    Edit3, X, BarChart3, LineChart, PieChart,
    Search, Calendar, MoreHorizontal, ArrowUpRight
} from 'lucide-vue-next';

// Charting
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement
} from 'chart.js';
import { Bar, Line } from 'vue-chartjs';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement);

const props = defineProps(['reports', 'metrics', 'recentDataPoints']);

const activeTab = ref('library'); // 'library', 'entry', or 'insights'
const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingReport = ref(null);
const selectedReport = ref(null);

// Data Point CRUD
const editingDataPoint = ref(null);
const showEditDataModal = ref(false);

const dataEntryForm = useForm({
    category: '',
    value: '',
    description: ''
});

const submitDataPoint = () => {
    if (editingDataPoint.value) {
        dataEntryForm.put(route('analytics.reports.data-point.update', editingDataPoint.value.id), {
            onSuccess: () => {
                showEditDataModal.value = false;
                editingDataPoint.value = null;
                dataEntryForm.reset();
            }
        });
    } else {
        dataEntryForm.post(route('analytics.reports.data-point.store'), {
            onSuccess: () => {
                dataEntryForm.reset();
            }
        });
    }
};

const editDataPoint = (point) => {
    editingDataPoint.value = point;
    dataEntryForm.category = point.category;
    dataEntryForm.value = point.value;
    dataEntryForm.description = point.description;
    showEditDataModal.value = true;
};

const deleteDataPoint = (pointId) => {
    if (confirm('Are you sure you want to remove this data point?')) {
        router.delete(route('analytics.reports.data-point.destroy', pointId));
    }
};

// Report Config CRUD
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

const openCreateModal = () => {
    editingReport.value = null;
    form.reset();
    showCreateModal.value = true;
};

const editReport = (report) => {
    editingReport.value = report;
    form.name = report.name;
    form.config = { ...report.config };
    form.schedule = { ...report.schedule };
    showCreateModal.value = true;
};

const submit = () => {
    if (editingReport.value) {
        form.put(route('analytics.reports.update', editingReport.value.id), {
            onSuccess: () => {
                showCreateModal.value = false;
                editingReport.value = null;
                form.reset();
            }
        });
    } else {
        form.post(route('analytics.reports.store'), {
            onSuccess: () => {
                showCreateModal.value = false;
                form.reset();
            }
        });
    }
};

const deleteReport = (reportId) => {
    if (confirm('Delete this report configuration?')) {
        router.delete(route('analytics.reports.destroy', reportId));
    }
};

const runReport = (reportId) => {
    router.post(route('analytics.reports.generate', reportId));
};

// Chart Data Calculation
const chartData = computed(() => {
    if (activeTab.value !== 'library' || !props.recentDataPoints.length) return null;
    
    const categories = [...new Set(props.recentDataPoints.map(p => p.category))];
    const data = categories.map(cat => {
        return props.recentDataPoints
            .filter(p => p.category === cat)
            .reduce((sum, p) => sum + (parseFloat(p.value) || 0), 0);
    });

    return {
        labels: categories,
        datasets: [
            {
                label: 'Metric Value',
                backgroundColor: '#3b82f6',
                data: data,
                borderRadius: 8,
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        y: { beginAtZero: true, grid: { display: false } },
        x: { grid: { display: false } }
    }
};

const selectedMetricData = computed(() => {
    return props.metrics[form.config.metric] || null;
});

const user = usePage().props.auth.user;
const isTenantAdmin = computed(() => user.roles.includes('tenant_admin'));
const isManagement = computed(() => {
    const roles = ['tenant_admin', 'admin', 'Finance', 'technical', 'network_engineer'];
    return user.roles.some(role => roles.includes(role));
});

if (!isManagement.value) {
    activeTab.value = 'entry';
}
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Custom Report Builder" />

        <div class="min-h-screen bg-slate-50 dark:bg-slate-950 px-4 py-12 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <!-- Premium Header -->
                <div class="mb-12 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-2">
                        <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-[0.65rem] font-bold uppercase tracking-widest text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-100 dark:border-blue-800">
                            <BarChart3 class="h-3 w-3" />
                            Analytics Engine
                        </div>
                        <h1 class="text-4xl font-black tracking-tight text-slate-900 dark:text-white">
                            Business <span class="text-blue-600">Intelligence</span>
                        </h1>
                        <p class="text-lg text-slate-500 dark:text-slate-400 max-w-2xl font-medium">
                            Synthesize operational data into actionable reports and monitor key performance indicators in real-time.
                        </p>
                    </div>

                    <div class="flex items-center gap-3 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl p-2 rounded-3xl border border-slate-200/50 dark:border-slate-800/50 shadow-2xl shadow-slate-200/20">
                        <button 
                            v-if="isManagement"
                            @click="activeTab = 'library'"
                            :class="[
                                'flex items-center gap-2 px-6 py-3 text-sm font-bold rounded-2xl transition-all duration-300',
                                activeTab === 'library' ? 'bg-blue-600 text-white shadow-xl shadow-blue-500/40' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'
                            ]"
                        >
                            <Layout class="w-4 h-4" />
                            Report Library
                        </button>
                        <button 
                            @click="activeTab = 'entry'"
                            :class="[
                                'flex items-center gap-2 px-6 py-3 text-sm font-bold rounded-2xl transition-all duration-300',
                                activeTab === 'entry' ? 'bg-blue-600 text-white shadow-xl shadow-blue-500/40' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'
                            ]"
                        >
                            <Plus class="w-4 h-4" />
                            Data Collection
                        </button>
                        
                        <div v-if="isTenantAdmin" class="h-8 w-px bg-slate-200 dark:bg-slate-800 mx-2"></div>
                        
                        <button 
                            v-if="isTenantAdmin"
                            @click="openCreateModal"
                            class="flex items-center gap-2 px-6 py-3 text-sm font-bold rounded-2xl bg-slate-950 text-white hover:bg-blue-600 transition-all duration-300 shadow-xl dark:bg-white dark:text-slate-950 dark:hover:bg-blue-500 dark:hover:text-white"
                        >
                            <Settings class="w-4 h-4 animate-spin-slow" />
                            New Architect
                        </button>
                    </div>
                </div>

                <div v-if="activeTab === 'library'" class="grid gap-10 lg:grid-cols-3">
                    <!-- Sidebar: Saved Reports -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="flex items-center justify-between">
                            <h2 class="flex items-center gap-2 text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter">
                                <Layout class="h-5 w-5 text-blue-500" />
                                Inventory
                            </h2>
                            <span class="text-xs font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-lg">
                                {{ reports.length }} Active
                            </span>
                        </div>
                        
                        <div v-if="reports.length === 0" class="rounded-[2rem] border-2 border-dashed border-slate-200 dark:border-slate-800 p-10 text-center bg-white/30 dark:bg-slate-900/30 backdrop-blur-sm">
                            <FileText class="mx-auto h-16 w-16 text-slate-200 dark:text-slate-800 mb-4" />
                            <p class="text-sm font-bold text-slate-500 dark:text-slate-400">No telemetry blueprints found.</p>
                            <button @click="openCreateModal" class="mt-4 text-blue-600 text-xs font-black uppercase tracking-widest hover:underline">Draft Now</button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="report in reports" :key="report.id" 
                                @click="selectedReport = report"
                                :class="[
                                    'group relative cursor-pointer rounded-[1.5rem] border p-1 transition-all duration-500',
                                    selectedReport?.id === report.id ? 'bg-white dark:bg-slate-900 border-blue-500 shadow-2xl scale-[1.02]' : 'bg-white/40 dark:bg-slate-900/40 border-transparent hover:bg-white dark:hover:bg-slate-900'
                                ]"
                            >
                                <div class="p-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="space-y-1">
                                            <h3 class="font-bold text-slate-900 dark:text-white leading-none">{{ report.name }}</h3>
                                            <p class="text-[0.6rem] text-blue-500 font-black uppercase tracking-widest">
                                                {{ metrics[report.config.metric]?.name || 'Legacy Metric' }}
                                            </p>
                                        </div>
                                        <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all duration-300">
                                            <button @click.stop="editReport(report)" class="p-2 text-slate-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-xl">
                                                <Edit3 class="h-3.5 w-3.5" />
                                            </button>
                                            <button @click.stop="deleteReport(report.id)" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-xl">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div v-if="report.latest_run" class="flex items-center justify-between text-[0.6rem] font-black uppercase tracking-tighter text-slate-400 border-t border-slate-100 dark:border-slate-800 pt-3">
                                        <div class="flex items-center gap-1.5">
                                            <span :class="['h-1.5 w-1.5 rounded-full', report.latest_run.status === 'completed' ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]' : 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)]']"></span>
                                            {{ report.latest_run.status }}
                                        </div>
                                        <span>{{ new Date(report.latest_run.generated_at).toLocaleDateString() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Viewport -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Dynamic Visualization -->
                        <div class="rounded-[2.5rem] bg-white/50 dark:bg-slate-900/50 p-8 backdrop-blur-xl border border-white dark:border-slate-800 shadow-2xl relative overflow-hidden h-[400px]">
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 to-purple-500/5 pointer-events-none"></div>
                            
                            <div class="relative z-10 h-full flex flex-col">
                                <div class="flex items-center justify-between mb-8">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-600/30">
                                            <LineChart class="h-5 w-5" />
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-bold text-slate-900 dark:text-white leading-none">Intelligence Stream</h3>
                                            <p class="text-xs text-slate-500 mt-1">Real-time telemetry aggregation</p>
                                        </div>
                                    </div>
                                    <div v-if="chartData" class="flex gap-2">
                                        <div class="px-3 py-1 bg-white/80 dark:bg-slate-800/80 rounded-full text-[0.65rem] font-bold border border-slate-100 dark:border-slate-700">
                                            Live Node
                                        </div>
                                    </div>
                                </div>

                                <div class="flex-1 min-h-0">
                                    <Bar v-if="chartData" :data="chartData" :options="chartOptions" />
                                    <div v-else class="h-full flex flex-col items-center justify-center text-center opacity-40">
                                        <Loader2 class="h-12 w-12 animate-spin text-blue-500 mb-4" />
                                        <p class="text-slate-500 font-bold tracking-widest uppercase text-xs">Awaiting Data Signature</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Report Control Panel -->
                        <div v-if="selectedReport" class="rounded-[2.5rem] bg-slate-900 p-8 text-white shadow-2xl shadow-slate-950/20 relative overflow-hidden">
                            <div class="absolute top-0 right-0 p-8 opacity-10">
                                <FileText class="h-32 w-32" />
                            </div>
                            
                            <div class="relative z-10">
                                <div class="flex flex-wrap items-center justify-between gap-6 mb-10">
                                    <div class="flex items-center gap-6">
                                        <div class="space-y-1">
                                            <p class="text-[0.65rem] font-black uppercase tracking-[0.2em] text-blue-400">Target Manifest</p>
                                            <h2 class="text-3xl font-black">{{ selectedReport.name }}</h2>
                                        </div>
                                        <div class="hidden sm:block h-12 w-px bg-white/10"></div>
                                        <div class="hidden sm:block space-y-1">
                                            <p class="text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400">Dimension Scan</p>
                                            <p class="text-sm font-bold">{{ selectedReport.config.dimensions.join(' + ') }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex gap-3">
                                        <button 
                                            @click="runReport(selectedReport.id)"
                                            class="inline-flex items-center gap-3 rounded-2xl bg-blue-600 px-8 py-4 text-sm font-black text-white hover:bg-blue-500 hover:scale-105 active:scale-95 transition-all shadow-xl shadow-blue-600/40"
                                        >
                                            <Play class="h-5 w-5 fill-current" />
                                            EXECUTE RUN
                                        </button>
                                        <button 
                                            @click="editReport(selectedReport)"
                                            class="p-4 rounded-2xl bg-white/10 hover:bg-white/20 transition-all text-white border border-white/10"
                                        >
                                            <Edit3 class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>

                                <div class="grid gap-6 md:grid-cols-2">
                                    <div class="space-y-4">
                                        <h4 class="text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400">Execution Log</h4>
                                        <div v-if="!selectedReport.runs?.length" class="p-6 rounded-3xl border border-white/5 bg-white/5 text-center">
                                            <p class="text-sm text-slate-500 font-bold">No historical runs recorded.</p>
                                        </div>
                                        <div v-for="run in selectedReport.runs?.slice(0, 3)" :key="run.id" class="group flex items-center justify-between p-4 rounded-2xl bg-white/5 border border-white/5 hover:bg-white/10 transition-all">
                                            <div class="flex items-center gap-4">
                                                <div :class="['h-2.5 w-2.5 rounded-full shadow-lg', run.status === 'completed' ? 'bg-green-500 shadow-green-500/40' : 'bg-red-500 shadow-red-500/40']"></div>
                                                <div>
                                                    <p class="text-xs font-bold">{{ new Date(run.generated_at).toLocaleString() }}</p>
                                                    <p class="text-[0.5rem] font-black uppercase tracking-widest opacity-50">{{ run.status }}</p>
                                                </div>
                                            </div>
                                            <a v-if="run.status === 'completed'" :href="run.file_path" class="p-3 bg-white/10 rounded-xl hover:bg-white text-slate-900 transition-all scale-0 group-hover:scale-100">
                                                <Download class="h-4 w-4" />
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        <h4 class="text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400">Configuration Matrix</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="p-4 rounded-3xl bg-white/5 border border-white/5">
                                                <p class="text-[0.55rem] font-black uppercase tracking-tighter text-slate-500 mb-1 leading-none">Date Window</p>
                                                <p class="text-xs font-bold">{{ selectedReport.config.filters.date_range.replace('_', ' ') }}</p>
                                            </div>
                                            <div class="p-4 rounded-3xl bg-white/5 border border-white/5">
                                                <p class="text-[0.55rem] font-black uppercase tracking-tighter text-slate-500 mb-1 leading-none">Output Codec</p>
                                                <p class="text-xs font-bold uppercase">{{ selectedReport.config.format }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center h-[500px] rounded-[3.5rem] bg-slate-100/30 dark:bg-slate-900/10 border-4 border-dashed border-slate-200 dark:border-slate-800 opacity-60">
                            <div class="relative">
                                <div class="absolute inset-0 bg-blue-500 animate-ping rounded-full opacity-20"></div>
                                <Settings class="h-20 w-20 text-slate-300 dark:text-slate-700 animate-spin-slow relative" />
                            </div>
                            <p class="mt-8 text-xl font-black text-slate-400 dark:text-slate-600 uppercase tracking-widest">Select Target Blueprint</p>
                        </div>
                    </div>
                </div>

                <!-- Data Collection Section -->
                <div v-else class="grid gap-10 lg:grid-cols-3">
                    <div class="lg:col-span-1 space-y-6">
                        <div class="rounded-[2.5rem] bg-white p-8 shadow-2xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-8 flex items-center gap-3">
                                <Plus class="h-6 w-6 text-blue-500" />
                                Collection
                            </h2>
                            
                            <form @submit.prevent="submitDataPoint" class="space-y-6">
                                <div>
                                    <label class="block text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-2 ml-1">Category / Signal</label>
                                    <select v-model="dataEntryForm.category" class="w-full rounded-2xl border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 focus:ring-4 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white" required>
                                        <option value="" disabled>Select domain...</option>
                                        <option value="Marketing">Marketing / Outreach</option>
                                        <option value="Operations">Operations / Infrastructure</option>
                                        <option value="Finance">Finance / Liquidity</option>
                                        <option value="Field Work">Tactical / Field Work</option>
                                        <option value="Research">Research / Development</option>
                                        <option value="Other">Standard / Other</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-2 ml-1">Magnitude (Optional)</label>
                                    <input v-model="dataEntryForm.value" type="number" step="0.01" class="w-full rounded-2xl border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 focus:ring-4 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white" placeholder="Float or Integer value">
                                </div>

                                <div>
                                    <label class="block text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-2 ml-1">Contextual Description</label>
                                    <textarea v-model="dataEntryForm.description" rows="4" class="w-full rounded-2xl border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 focus:ring-4 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white" placeholder="Detailed metadata regarding this entry" required></textarea>
                                </div>

                                <button 
                                    type="submit" 
                                    :disabled="dataEntryForm.processing"
                                    class="w-full rounded-2xl bg-blue-600 px-6 py-4 text-sm font-black text-white shadow-xl shadow-blue-600/30 hover:bg-blue-500 hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50"
                                >
                                    <span v-if="dataEntryForm.processing" class="flex items-center justify-center gap-2">
                                        <Loader2 class="h-4 w-4 animate-spin" /> Transmitting...
                                    </span>
                                    <span v-else>{{ editingDataPoint ? 'UPDATE SEQUENCE' : 'COMMIT SIGNAL' }}</span>
                                </button>
                                <button v-if="editingDataPoint" type="button" @click="editingDataPoint = null; dataEntryForm.reset()" class="w-full mt-2 text-xs font-bold text-slate-400 uppercase tracking-widest hover:text-slate-600 transition-colors">Abort Update</button>
                            </form>
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-6">
                        <div class="rounded-[2.5rem] bg-white p-10 shadow-2xl dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <h2 class="text-2xl font-black text-slate-900 dark:text-white mb-10 flex items-center gap-4">
                                <History class="h-6 w-6 text-blue-500" />
                                Sequence Audit
                            </h2>

                            <div v-if="recentDataPoints.length === 0" class="py-24 text-center border-4 border-dashed border-slate-100 dark:border-slate-800 rounded-[3rem] bg-slate-50/50 dark:bg-slate-800/10">
                                <MessageSquare class="mx-auto h-20 w-20 text-slate-100 dark:text-slate-800 mb-4" />
                                <p class="text-lg font-bold text-slate-400 uppercase tracking-widest">Archive Empty</p>
                            </div>

                            <div v-else class="space-y-4">
                                <div v-for="point in recentDataPoints" :key="point.id" class="group flex items-start gap-6 p-6 rounded-[2rem] bg-slate-50 dark:bg-slate-800/30 border border-slate-100 dark:border-slate-800 hover:bg-white dark:hover:bg-slate-800 hover:shadow-2xl hover:scale-[1.01] transition-all duration-300">
                                    <div class="rounded-2xl bg-blue-500/10 p-4 text-blue-600 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                                        <Database class="h-6 w-6" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-[0.6rem] font-black uppercase tracking-widest bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                                {{ point.category }}
                                            </span>
                                            <div class="flex items-center gap-4">
                                                <span class="text-[0.6rem] font-black text-slate-400 uppercase tracking-widest">{{ new Date(point.created_at).toLocaleString([], {hour: '2-digit', minute:'2-digit', day: '2-digit', month: 'short'}) }}</span>
                                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
                                                    <button @click="editDataPoint(point)" class="p-2 text-slate-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-xl transition-all"><Edit3 class="h-3.5 w-3.5" /></button>
                                                    <button @click="deleteDataPoint(point.id)" class="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-xl transition-all"><Trash2 class="h-3.5 w-3.5" /></button>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-base text-slate-900 dark:text-white font-bold leading-relaxed pr-8">{{ point.description }}</p>
                                        <div class="mt-4 flex items-center justify-between">
                                            <div class="flex items-center gap-6">
                                                <div v-if="point.value" class="flex items-center gap-2">
                                                    <div class="h-1.5 w-4 bg-blue-500 rounded-full"></div>
                                                    <span class="text-xs font-black font-mono text-slate-600 dark:text-slate-400">VALUE: {{ point.value }}</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                                    <User class="h-3 w-3 text-slate-400" />
                                                    <span class="text-[0.65rem] font-black uppercase tracking-tight text-slate-500 dark:text-slate-400">
                                                        {{ point.creator?.name || 'Ghost Admin' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <ArrowUpRight class="h-4 w-4 text-slate-200 dark:text-slate-800 opacity-0 group-hover:opacity-100 transition-all -translate-x-2 group-hover:translate-x-0" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Architect Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-950/80 backdrop-blur-md">
            <div class="w-full max-w-2xl rounded-[3rem] bg-white p-10 shadow-[0_32px_128px_rgba(0,0,0,0.5)] dark:bg-slate-900 border border-white/10 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-600 via-purple-600 to-pink-600"></div>
                
                <div class="flex items-center justify-between mb-12">
                    <div>
                        <h2 class="text-3xl font-black text-slate-900 dark:text-white leading-tight">
                            {{ editingReport ? 'Refine Blueprint' : 'Architect New Run' }}
                        </h2>
                        <p class="text-sm font-bold text-slate-400 uppercase tracking-widest mt-1">Telemetry configuration</p>
                    </div>
                    <button @click="showCreateModal = false" class="p-4 rounded-3xl bg-slate-50 dark:bg-slate-800 text-slate-400 hover:text-red-500 transition-all">
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-8">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-3 ml-1">Identifier</label>
                            <input v-model="form.name" type="text" class="w-full rounded-2xl border-slate-200 bg-slate-50 p-4 text-base font-bold text-slate-900 focus:ring-4 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white" placeholder="Blueprint name">
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="block text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-3 ml-1">Base Telemetry Metric</label>
                                <select v-model="form.config.metric" class="w-full rounded-2xl border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-900 focus:ring-4 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                    <option value="" disabled>Select Metric...</option>
                                    <option v-for="(data, key) in metrics" :key="key" :value="key">{{ data.name }}</option>
                                </select>
                            </div>

                            <div v-if="selectedMetricData">
                                <label class="block text-[0.65rem] font-black uppercase tracking-[0.2em] text-slate-400 mb-3 ml-1">Data Segments</label>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="dim in selectedMetricData.dimensions" 
                                        :key="dim" 
                                        type="button"
                                        @click="form.config.dimensions.includes(dim) ? form.config.dimensions = form.config.dimensions.filter(d => d !== dim) : form.config.dimensions.push(dim)"
                                        :class="[
                                            'px-4 py-2 rounded-xl text-[0.6rem] font-black uppercase tracking-widest transition-all duration-300',
                                            form.config.dimensions.includes(dim) ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30' : 'bg-slate-100 text-slate-500 dark:bg-slate-800'
                                        ]"
                                    >
                                        {{ dim }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-10 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-4">
                        <button type="button" @click="showCreateModal = false" class="px-8 py-4 text-sm font-black text-slate-400 uppercase tracking-widest">Abort</button>
                        <button type="submit" :disabled="form.processing" class="rounded-2xl bg-blue-600 px-10 py-4 text-sm font-black text-white shadow-xl shadow-blue-600/30 hover:bg-blue-500 transition-all">
                            {{ editingReport ? 'PATCH CONFIG' : 'COMMIT BLUEPRINT' }}
                        </button>
                    </div>
                </form>
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
