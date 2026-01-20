<script setup>
import { Head } from '@inertiajs/vue3';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { 
    Clock, 
    ArrowDownCircle, 
    ArrowUpCircle, 
    Activity,
    Calendar,
    Zap,
    History
} from 'lucide-vue-next';

defineProps({
    sessions: Array
});

const formatTime = (seconds) => {
    if (!seconds) return '0s';
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    let res = "";
    if (hrs > 0) res += `${hrs}h `;
    if (mins > 0) res += `${mins}m `;
    if (secs > 0 || res === "") res += `${secs}s`;
    return res;
};

const formatDate = (dateString) => {
    if (!dateString) return 'Ongoing';
    return new Date(dateString).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Connection History" />

    <CustomerLayout>
        <template #header>Connection History</template>

        <div class="space-y-8">
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center">
                            <Zap class="w-6 h-6 text-indigo-600" />
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Recent Activity</p>
                            <h4 class="text-xl font-black text-slate-900">{{ sessions.length }} Sessions</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History List -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-8 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-black text-slate-900">Session Logs</h3>
                        <p class="text-sm font-medium text-slate-500">Your last 20 connection attempts</p>
                    </div>
                    <History class="w-6 h-6 text-slate-300" />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Start Time</th>
                                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Duration</th>
                                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Traffic (In/Out)</th>
                                <th class="px-8 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Device/IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="session in sessions" :key="session.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-3">
                                        <Calendar class="w-4 h-4 text-slate-300" />
                                        <div>
                                            <p class="text-sm font-black text-slate-900">{{ formatDate(session.start_time) }}</p>
                                            <p class="text-[10px] font-medium text-slate-400">End: {{ formatDate(session.stop_time) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-2">
                                        <Clock class="w-4 h-4 text-indigo-500" />
                                        <span class="text-sm font-bold text-slate-700">{{ formatTime(session.duration) }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-4">
                                        <div class="flex items-center gap-1">
                                            <ArrowDownCircle class="w-3 h-3 text-emerald-500" />
                                            <span class="text-sm font-black text-slate-900">{{ session.download }} MB</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <ArrowUpCircle class="w-3 h-3 text-blue-500" />
                                            <span class="text-sm font-black text-slate-900">{{ session.upload }} MB</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <div class="space-y-1">
                                        <p class="text-xs font-mono font-bold text-slate-600">{{ session.ip_address }}</p>
                                        <p class="text-[10px] font-mono text-slate-400">{{ session.mac_address }}</p>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="sessions.length === 0">
                                <td colspan="4" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <Activity class="w-12 h-12 text-slate-200 mb-4" />
                                        <p class="text-slate-500 font-medium">No connection history found.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
