<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: Object,
    package: Object,
    daysRemaining: Number,
    paymentMethods: Array,
    country: String,
    currency: String,
});

const logout = () => {
    useForm({}).post(route('customer.logout'));
};

const statusColorClass = computed(() => {
    if (props.daysRemaining === null) return 'bg-green-100 text-green-800';
    if (props.daysRemaining <= 0) return 'bg-red-100 text-red-800';
    if (props.daysRemaining <= 3) return 'bg-yellow-100 text-yellow-800';
    return 'bg-green-100 text-green-800';
});

const statusLabel = computed(() => {
    if (props.daysRemaining === null) return 'Active';
    if (props.daysRemaining <= 0) return 'Expired';
    return 'Active';
});

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Customer Dashboard" />

    <div class="min-h-screen bg-slate-50">
        <!-- Navigation -->
        <nav class="bg-white border-b border-slate-200 sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <span class="font-black text-2xl bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-violet-600">ZISP PORTAL</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="hidden sm:flex flex-col items-end">
                            <span class="text-sm font-bold text-slate-900">{{ user.username }}</span>
                            <span class="text-xs text-slate-500">{{ user.account_number }}</span>
                        </div>
                        <button @click="logout" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-full text-white bg-slate-900 hover:bg-slate-800 transition-all shadow-sm">
                            Log Out
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Welcome Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-slate-900">Welcome back, {{ user.full_name || user.username }}!</h1>
                    <p class="text-slate-500">Manage your internet subscription and payments here.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Subscription Card -->
                    <div class="lg:col-span-2 space-y-8">
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="p-8">
                                <div class="flex justify-between items-start mb-6">
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-900">Current Subscription</h3>
                                        <p class="text-sm text-slate-500">Your active internet plan details</p>
                                    </div>
                                    <span :class="statusColorClass" class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
                                        {{ statusLabel }}
                                    </span>
                                </div>

                                <div class="flex flex-col md:flex-row gap-8 items-center md:items-start">
                                    <div class="w-32 h-32 rounded-2xl bg-indigo-50 flex items-center justify-center shrink-0">
                                        <svg class="w-16 h-16 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.111 16.404a5.5 5.5 0 117.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.345 6.347c5.858-5.857 15.352-5.857 21.213 0" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 text-center md:text-left">
                                        <h2 class="text-3xl font-black text-slate-900 mb-2">{{ package?.name || 'No Active Plan' }}</h2>
                                        <div class="flex flex-wrap justify-center md:justify-start gap-4 text-slate-600 mb-6">
                                            <div class="flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" stroke-width="2"/></svg>
                                                <span class="font-bold">{{ package?.download_speed }} Mbps</span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg>
                                                <span>{{ package?.duration_value }} {{ package?.duration_unit }}</span>
                                            </div>
                                        </div>
                                        
                                        <div v-if="daysRemaining !== null" class="w-full bg-slate-100 rounded-full h-2 mb-2">
                                            <div class="bg-indigo-600 h-2 rounded-full" :style="{ width: Math.max(0, Math.min(100, (daysRemaining / 30) * 100)) + '%' }"></div>
                                        </div>
                                        <p v-if="daysRemaining !== null" class="text-sm font-medium" :class="daysRemaining <= 3 ? 'text-red-600' : 'text-slate-600'">
                                            {{ daysRemaining > 0 ? `${daysRemaining} days remaining` : (daysRemaining === 0 ? 'Expires today' : 'Expired') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-slate-50 px-8 py-4 border-t border-slate-200 flex flex-wrap justify-between items-center gap-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :class="user.online ? 'bg-green-500 animate-pulse' : 'bg-slate-400'"></div>
                                    <span class="text-sm font-bold text-slate-700">{{ user.online ? 'Connected' : 'Disconnected' }}</span>
                                </div>
                                <div class="text-sm text-slate-500">
                                    Expires on: <span class="font-bold text-slate-900">{{ formatDate(user.expires_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <Link :href="route('customer.renew')" class="group bg-white p-6 rounded-3xl shadow-sm border border-slate-200 hover:border-indigo-600 transition-all">
                                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                </div>
                                <h4 class="text-lg font-bold text-slate-900">Renew Plan</h4>
                                <p class="text-sm text-slate-500">Extend your current subscription instantly.</p>
                            </Link>
                            <Link :href="route('customer.upgrade')" class="group bg-white p-6 rounded-3xl shadow-sm border border-slate-200 hover:border-violet-600 transition-all">
                                <div class="w-12 h-12 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4 group-hover:bg-violet-600 group-hover:text-white transition-all">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                </div>
                                <h4 class="text-lg font-bold text-slate-900">Upgrade Speed</h4>
                                <p class="text-sm text-slate-500">Switch to a faster internet package.</p>
                            </Link>
                        </div>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="space-y-8">
                        <!-- Account Details -->
                        <div class="bg-slate-900 rounded-3xl p-8 text-white shadow-xl">
                            <h3 class="text-lg font-bold mb-6">Account Details</h3>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-slate-400 text-xs uppercase font-bold tracking-widest mb-1">Account Number</p>
                                    <p class="text-lg font-mono">{{ user.account_number }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-xs uppercase font-bold tracking-widest mb-1">Username</p>
                                    <p class="text-lg">{{ user.username }}</p>
                                </div>
                                <div>
                                    <p class="text-slate-400 text-xs uppercase font-bold tracking-widest mb-1">Phone</p>
                                    <p class="text-lg">{{ user.phone || 'Not set' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-200">
                            <h3 class="text-lg font-bold text-slate-900 mb-6">Payment Methods</h3>
                            <div class="space-y-4">
                                <div v-for="method in paymentMethods" :key="method" class="flex items-center gap-4 p-3 rounded-2xl bg-slate-50 border border-slate-100">
                                    <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shadow-sm">
                                        <img v-if="method === 'mpesa'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/15/M-PESA_LOGO-01.svg/1200px-M-PESA_LOGO-01.svg.png" class="h-4" alt="M-Pesa">
                                        <img v-else-if="method === 'momo'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/MTN_Logo.svg/1200px-MTN_Logo.svg.png" class="h-6" alt="MoMo">
                                        <span v-else class="text-xs font-bold uppercase">{{ method }}</span>
                                    </div>
                                    <span class="font-bold text-slate-700 capitalize">{{ method === 'mpesa' ? 'M-Pesa' : (method === 'momo' ? 'MTN MoMo' : method) }}</span>
                                </div>
                                <p class="text-xs text-slate-500 text-center mt-4">Available in {{ country }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.bg-slate-900 {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}
</style>
