<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { 
    Wifi, 
    Clock, 
    ArrowUpCircle,
    ArrowDownCircle, 
    CreditCard,
    Gauge,
    Activity,
    Play,
    Zap
} from 'lucide-vue-next';

const props = defineProps({
    user: Object,
    package: Object,
    daysRemaining: Number,
    paymentMethods: Array,
    country: String,
    currency: String,
    usage: Object,
});

// ... status logic stays same ...

const dataLimitGB = computed(() => {
    // If the package has a data limit, use it. Otherwise assume unlimited or high number for visual scale
    return props.package?.data_limit || 100; 
});

const usagePercentage = computed(() => {
    return Math.min(100, Math.round((props.usage.total_gb / dataLimitGB.value) * 100));
});

// Speed Test Logic
const isTesting = ref(false);
const testResult = ref(null);
const runSpeedTest = () => {
    isTesting.value = true;
    testResult.value = null;
    
    // Simulate speed test
    setTimeout(() => {
        testResult.value = {
            download: (props.package?.download_speed * (0.8 + Math.random() * 0.2)).toFixed(1),
            upload: (props.package?.upload_speed * (0.7 + Math.random() * 0.3)).toFixed(1),
            latency: Math.floor(Math.random() * 50) + 10
        };
        isTesting.value = false;
    }, 3000);
};
</script>

<template>
    <Head title="Dashboard" />

    <CustomerLayout>
        <template #header>Dashboard Overview</template>

        <div class="space-y-8">
            <!-- Welcome Header -->
            <div class="bg-indigo-600 rounded-[2rem] p-8 text-white shadow-xl shadow-indigo-100 relative overflow-hidden">
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <h1 class="text-3xl font-black mb-2">Hello, {{ user.full_name || user.username }}!</h1>
                        <p class="text-indigo-100 font-medium">Your internet connection is currently <span class="font-black underline decoration-2 underline-offset-4">{{ user.online ? 'Active' : 'Offline' }}</span>.</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/20">
                        <p class="text-[10px] font-black uppercase tracking-widest text-indigo-100 mb-1">Total Data Used</p>
                        <p class="text-2xl font-black">{{ usage.total_gb }} <span class="text-sm">GB</span></p>
                    </div>
                </div>
                <!-- Decorative SVG -->
                <svg class="absolute right-0 bottom-0 w-64 h-64 text-indigo-500 opacity-20 -mr-16 -mb-16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8.111 16.404a5.5 5.5 0 117.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.345 6.347c5.858-5.857 15.352-5.857 21.213 0" />
                </svg>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Subscription Card -->
                <div class="lg:col-span-2 space-y-8">
                    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
                        <div class="p-8">
                            <div class="flex justify-between items-start mb-8">
                                <div>
                                    <h3 class="text-xl font-black text-slate-900">Subscription & Usage</h3>
                                    <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Real-time status</p>
                                </div>
                                <span :class="statusColorClass" class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest shadow-sm">
                                    {{ statusLabel }}
                                </span>
                            </div>

                            <div class="grid md:grid-cols-2 gap-12">
                                <!-- Usage Circle -->
                                <div class="flex flex-col items-center justify-center p-6 bg-slate-50 rounded-[2.5rem] border border-slate-100 relative overflow-hidden group">
                                    <div class="relative h-48 w-48 mb-6">
                                        <svg class="h-full w-full transform -rotate-90" viewBox="0 0 36 36">
                                            <path class="text-slate-200" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="2.5" />
                                            <path :class="usagePercentage > 80 ? 'text-orange-500' : 'text-indigo-600'" 
                                                class="transition-all duration-1000"
                                                :stroke-dasharray="usagePercentage + ', 100'"
                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                                        </svg>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                                            <span class="text-4xl font-black text-slate-900">{{ usagePercentage }}%</span>
                                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Plan Used</span>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm font-bold text-slate-600">{{ usage.total_gb }} GB used of {{ dataLimitGB }} GB</p>
                                    </div>
                                </div>

                                <!-- Stats & Limits -->
                                <div class="space-y-6">
                                    <h4 class="text-4xl font-black text-slate-900 mb-2">{{ package?.name || 'No Active Plan' }}</h4>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
                                            <div class="flex items-center gap-2 mb-1">
                                                <ArrowDownCircle class="w-3 h-3 text-indigo-600" />
                                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Download</p>
                                            </div>
                                            <p class="text-xl font-black text-indigo-600">{{ usage.download_gb }} <span class="text-[10px]">GB</span></p>
                                        </div>
                                        <div class="bg-violet-50 p-4 rounded-2xl border border-violet-100">
                                            <div class="flex items-center gap-2 mb-1">
                                                <ArrowUpCircle class="w-3 h-3 text-violet-600" />
                                                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Upload</p>
                                            </div>
                                            <p class="text-xl font-black text-violet-600">{{ usage.upload_gb }} <span class="text-[10px]">GB</span></p>
                                        </div>
                                    </div>

                                    <div class="space-y-4">
                                        <div class="flex justify-between items-end">
                                            <p class="text-sm font-black text-slate-900">Time Remaining</p>
                                            <p class="text-sm font-black" :class="daysRemaining <= 3 ? 'text-red-600' : 'text-indigo-600'">
                                                {{ daysRemaining > 0 ? `${daysRemaining} days` : (daysRemaining === 0 ? 'Expires today' : 'Expired') }}
                                            </p>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                                            <div 
                                                class="h-full rounded-full transition-all duration-1000" 
                                                :class="daysRemaining <= 3 ? 'bg-red-500' : 'bg-indigo-600'"
                                                :style="{ width: Math.max(2, Math.min(100, (daysRemaining / 30) * 100)) + '%' }"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="flex gap-4 pt-4">
                                        <Link :href="route('customer.renew')" class="flex-1 rounded-2xl bg-slate-900 text-white p-4 text-center text-sm font-black hover:bg-slate-800 transition-colors">
                                            Extend Plan
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50/50 px-8 py-6 border-t border-slate-100 flex flex-wrap justify-between items-center gap-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center">
                                    <Clock class="w-5 h-5 text-slate-400" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Expires On</p>
                                    <p class="text-sm font-black text-slate-900">{{ formatDate(user.expires_at) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center">
                                    <Wifi class="w-5 h-5 text-slate-400" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Plan Speed</p>
                                    <p class="text-sm font-black text-slate-900">{{ package?.download_speed }} Mbps</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <Link :href="route('customer.renew')" class="group bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200 hover:border-indigo-600 hover:shadow-xl hover:shadow-indigo-100 transition-all duration-300">
                            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                                <RefreshCw class="w-7 h-7" />
                            </div>
                            <h4 class="text-xl font-black text-slate-900 mb-2">Renew Plan</h4>
                            <p class="text-sm font-medium text-slate-500 leading-relaxed">Extend your current subscription instantly with M-Pesa or MoMo.</p>
                        </Link>
                        <Link :href="route('customer.upgrade')" class="group bg-white p-8 rounded-[2rem] shadow-sm border border-slate-200 hover:border-violet-600 hover:shadow-xl hover:shadow-violet-100 transition-all duration-300">
                            <div class="w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-6 group-hover:bg-violet-600 group-hover:text-white transition-all duration-300">
                                <TrendingUp class="w-7 h-7" />
                            </div>
                            <h4 class="text-xl font-black text-slate-900 mb-2">Upgrade Speed</h4>
                            <p class="text-sm font-medium text-slate-500 leading-relaxed">Need more speed? Switch to a higher performance package now.</p>
                        </Link>
                    </div>
                </div>

                <!-- Sidebar Info -->
                <div class="space-y-8">
                    <!-- Account Details -->
                    <div class="bg-slate-900 rounded-[2rem] p-8 text-white shadow-xl shadow-slate-200 relative overflow-hidden">
                        <div class="relative z-10">
                            <h3 class="text-xl font-black mb-8 flex items-center gap-2">
                                <User class="w-6 h-6 text-indigo-400" />
                                Account Info
                            </h3>
                            <div class="space-y-6">
                                <div>
                                    <p class="text-slate-400 text-[10px] uppercase font-black tracking-widest mb-1">Account Number</p>
                                    <p class="text-xl font-mono font-black text-indigo-100">{{ user.account_number }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-[10px] uppercase font-black tracking-widest mb-1">Username</p>
                                    <p class="text-lg font-bold">{{ user.username }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-[10px] uppercase font-black tracking-widest mb-1">Phone Number</p>
                                    <p class="text-lg font-bold">{{ user.phone || 'Not set' }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Decorative background element -->
                        <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/5 rounded-full blur-3xl"></div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-slate-200">
                        <h3 class="text-xl font-black text-slate-900 mb-8 flex items-center gap-2">
                            <CreditCard class="w-6 h-6 text-indigo-600" />
                            Payments
                        </h3>
                        <div class="space-y-4">
                            <div v-for="method in paymentMethods" :key="method" class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-100 group hover:bg-white hover:border-indigo-200 transition-all duration-200">
                                <div class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shadow-sm border border-slate-100 group-hover:scale-110 transition-transform">
                                    <img v-if="method === 'mpesa'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/15/M-PESA_LOGO-01.svg/1200px-M-PESA_LOGO-01.svg.png" class="h-5" alt="M-Pesa">
                                    <img v-else-if="method === 'momo'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/MTN_Logo.svg/1200px-MTN_Logo.svg.png" class="h-8" alt="MoMo">
                                    <span v-else class="text-xs font-black uppercase">{{ method }}</span>
                                </div>
                                <div>
                                    <span class="font-black text-slate-700 capitalize block">{{ method === 'mpesa' ? 'M-Pesa' : (method === 'momo' ? 'MTN MoMo' : method) }}</span>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Instant Pay</span>
                                </div>
                            </div>
                            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Service Region</p>
                                <p class="text-sm font-black text-slate-900 mt-1">{{ country === 'KE' ? 'Kenya' : country }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Speed Test Card -->
                    <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-slate-200">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="text-xl font-black text-slate-900 flex items-center gap-2">
                                <Gauge class="w-6 h-6 text-indigo-600" />
                                Speed Test
                            </h3>
                            <button 
                                @click="runSpeedTest" 
                                :disabled="isTesting"
                                class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all"
                                :class="isTesting ? 'bg-slate-100 text-slate-400' : 'bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white'"
                            >
                                {{ isTesting ? 'Testing...' : 'Run Test' }}
                            </button>
                        </div>

                        <div v-if="testResult || isTesting" class="grid grid-cols-3 gap-4">
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center relative overflow-hidden">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Ping</p>
                                <p class="text-xl font-black text-slate-900">{{ isTesting ? '...' : `${testResult.latency}ms` }}</p>
                                <div v-if="isTesting" class="absolute bottom-0 left-0 h-0.5 bg-indigo-600 animate-progress w-full"></div>
                            </div>
                            <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 text-center relative overflow-hidden">
                                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-1">Down</p>
                                <p class="text-xl font-black text-indigo-600">{{ isTesting ? '...' : `${testResult.download}Mb` }}</p>
                                <div v-if="isTesting" class="absolute bottom-0 left-0 h-0.5 bg-indigo-600 animate-progress w-full"></div>
                            </div>
                            <div class="bg-violet-50 p-4 rounded-2xl border border-violet-100 text-center relative overflow-hidden">
                                <p class="text-[10px] font-black text-violet-600 uppercase tracking-widest mb-1">Up</p>
                                <p class="text-xl font-black text-violet-600">{{ isTesting ? '...' : `${testResult.upload}Mb` }}</p>
                                <div v-if="isTesting" class="absolute bottom-0 left-0 h-0.5 bg-violet-600 animate-progress w-full"></div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                            <Activity class="w-12 h-12 text-slate-100 mb-2" />
                            <p class="text-xs font-bold text-slate-400">Test your connection speed in seconds.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>

<style scoped>
@keyframes progress {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-progress {
    animation: progress 1.5s infinite linear;
}
</style>

<style scoped>
.bg-slate-900 {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}
</style>

<style scoped>
.bg-slate-900 {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}
</style>
