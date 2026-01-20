<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { 
    ArrowLeft, 
    Send, 
    User, 
    ShieldCheck, 
    Clock,
    AlertCircle,
    CheckCircle2
} from 'lucide-vue-next';

const props = defineProps({
    ticket: Object
});

const form = useForm({
    message: ''
});

const submit = () => {
    form.post(route('customer.tickets.reply', props.ticket.id), {
        onSuccess: () => form.reset()
    });
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head :title="`Ticket ${ticket.ticket_number}`" />

    <CustomerLayout>
        <template #header>Ticket #{{ ticket.ticket_number }}</template>

        <div class="space-y-8">
            <Link :href="route('customer.tickets.index')" class="inline-flex items-center gap-2 text-slate-500 hover:text-indigo-600 font-bold transition-colors">
                <ArrowLeft class="w-4 h-4" />
                Back to Tickets
            </Link>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Messages Thread -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Original Issue -->
                    <div class="bg-indigo-50 border border-indigo-100 rounded-[2rem] p-8 shadow-sm">
                        <div class="flex items-start gap-4 mb-6">
                            <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white">
                                <User class="w-5 h-5" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-black text-slate-900">You (Original Issue)</h4>
                                    <span class="text-xs font-bold text-slate-400">{{ formatDate(ticket.created_at) }}</span>
                                </div>
                                <p class="text-slate-700 leading-relaxed">{{ ticket.description }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Replies -->
                    <div v-for="reply in ticket.replies" :key="reply.id" 
                        :class="[
                            'rounded-[2rem] p-8 shadow-sm border transition-all',
                            reply.repliable_type === 'user' 
                                ? 'bg-white border-slate-200' 
                                : 'bg-slate-900 text-white border-slate-800 ml-4 md:ml-12'
                        ]"
                    >
                        <div class="flex items-start gap-4">
                            <div :class="[
                                'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                                reply.repliable_type === 'user' ? 'bg-indigo-600 text-white' : 'bg-emerald-500 text-white'
                            ]">
                                <User v-if="reply.repliable_type === 'user'" class="w-5 h-5" />
                                <ShieldCheck v-else class="w-5 h-5" />
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-black" :class="reply.repliable_type === 'user' ? 'text-slate-900' : 'text-white'">
                                        {{ reply.repliable_type === 'user' ? 'You' : 'ISP Support' }}
                                    </h4>
                                    <span class="text-xs font-bold" :class="reply.repliable_type === 'user' ? 'text-slate-400' : 'text-slate-500'">
                                        {{ formatDate(reply.created_at) }}
                                    </span>
                                </div>
                                <p :class="reply.repliable_type === 'user' ? 'text-slate-700' : 'text-slate-300'" class="leading-relaxed">{{ reply.message }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Reply Form -->
                    <div v-if="ticket.status === 'open'" class="bg-white rounded-[2rem] p-2 shadow-xl border border-slate-200 sticky bottom-4">
                        <form @submit.prevent="submit" class="flex items-center gap-4">
                            <input 
                                v-model="form.message"
                                type="text"
                                class="flex-1 border-none bg-transparent focus:ring-0 text-slate-700 font-medium px-6 py-4 placeholder-slate-300"
                                placeholder="Type your reply here..."
                            >
                            <button 
                                type="submit"
                                :disabled="form.processing || !form.message"
                                class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center hover:bg-indigo-700 disabled:opacity-50 transition-all shadow-lg shadow-indigo-100"
                            >
                                <Send class="w-5 h-5" />
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Ticket Info Sidebar -->
                <div class="space-y-6">
                    <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-slate-200">
                        <h3 class="text-lg font-black text-slate-900 mb-6">Status Details</h3>
                        <div class="space-y-6">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Current State</p>
                                <div class="flex items-center gap-2">
                                    <AlertCircle v-if="ticket.status === 'open'" class="w-4 h-4 text-amber-500" />
                                    <CheckCircle2 v-else class="w-4 h-4 text-emerald-500" />
                                    <span class="font-black text-slate-900 uppercase tracking-wider">{{ ticket.status }}</span>
                                </div>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Priority</p>
                                <p class="font-black text-slate-900 capitalize">{{ ticket.priority }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Opened on</p>
                                <p class="font-black text-slate-900">{{ formatDate(ticket.created_at) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="ticket.status === 'closed' && ticket.resolution_note" class="bg-emerald-50 border border-emerald-100 rounded-[2rem] p-8">
                        <h3 class="text-lg font-black text-emerald-900 mb-4 flex items-center gap-2">
                            <CheckCircle2 class="w-5 h-5" />
                            Resolution
                        </h3>
                        <p class="text-sm font-medium text-emerald-800 leading-relaxed">{{ ticket.resolution_note }}</p>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
