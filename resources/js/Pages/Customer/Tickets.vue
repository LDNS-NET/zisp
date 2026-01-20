<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { 
    MessagesSquare, 
    Plus, 
    ChevronRight, 
    AlertCircle,
    CheckCircle2,
    Clock,
    Search,
    X
} from 'lucide-vue-next';

const props = defineProps({
    tickets: Object
});

const showNewTicketModal = ref(false);

const form = useForm({
    priority: 'medium',
    description: ''
});

const submit = () => {
    form.post(route('customer.tickets.store'), {
        onSuccess: () => {
            showNewTicketModal.value = false;
            form.reset();
        }
    });
};

const getStatusClass = (status) => {
    return status === 'open' 
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' 
        : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400';
};

const getPriorityClass = (priority) => {
    if (priority === 'high') return 'text-red-500';
    if (priority === 'medium') return 'text-amber-500';
    return 'text-blue-500';
};
</script>

<template>
    <Head title="Support Tickets" />

    <CustomerLayout>
        <template #header>Support Tickets</template>

        <div class="space-y-8">
            <!-- Stats & Action -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center">
                        <MessagesSquare class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">Get Help</h2>
                        <p class="text-sm font-medium text-slate-500">Track and manage your support requests</p>
                    </div>
                </div>
                <button 
                    @click="showNewTicketModal = true"
                    class="flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-2xl font-black hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100"
                >
                    <Plus class="w-5 h-5" />
                    Open New Ticket
                </button>
            </div>

            <!-- Tickets List -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
                <div v-if="tickets.data.length > 0" class="divide-y divide-slate-100">
                    <Link 
                        v-for="ticket in tickets.data" 
                        :key="ticket.id"
                        :href="route('customer.tickets.show', ticket.id)"
                        class="p-6 md:p-8 flex items-center justify-between hover:bg-slate-50 transition-colors group"
                    >
                        <div class="flex items-center gap-6">
                            <div class="hidden md:flex w-12 h-12 rounded-2xl bg-slate-50 items-center justify-center border border-slate-100 group-hover:bg-white transition-colors">
                                <AlertCircle v-if="ticket.status === 'open'" class="w-6 h-6 text-amber-500" />
                                <CheckCircle2 v-else class="w-6 h-6 text-emerald-500" />
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-black font-mono text-slate-400 group-hover:text-indigo-600">{{ ticket.ticket_number }}</span>
                                    <span :class="getStatusClass(ticket.status)" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest">
                                        {{ ticket.status }}
                                    </span>
                                </div>
                                <h4 class="text-lg font-black text-slate-900 line-clamp-1 group-hover:text-indigo-600 transition-colors">
                                    {{ ticket.description }}
                                </h4>
                                <div class="flex items-center gap-4 text-xs font-bold text-slate-400">
                                    <span :class="getPriorityClass(ticket.priority)" class="capitalize">{{ ticket.priority }} Priority</span>
                                    <span class="flex items-center gap-1.5">
                                        <Clock class="w-3 h-3" />
                                        {{ new Date(ticket.created_at).toLocaleDateString() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <ChevronRight class="w-5 h-5 text-slate-300 group-hover:text-indigo-600 group-hover:translate-x-1 transition-all" />
                    </Link>
                </div>
                <div v-else class="py-20 text-center">
                    <div class="inline-flex w-20 h-20 rounded-[2rem] bg-slate-50 items-center justify-center mb-6">
                        <Search class="w-10 h-10 text-slate-200" />
                    </div>
                    <h3 class="text-xl font-black text-slate-900 mb-2">No tickets found</h3>
                    <p class="text-sm font-medium text-slate-500 max-w-xs mx-auto mb-8">If you're having issues with your connection, open a ticket and our team will assist you.</p>
                </div>
            </div>
        </div>

        <!-- New Ticket Modal -->
        <div v-if="showNewTicketModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-[2.5rem] w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
                <div class="p-8 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-black text-slate-900">New Support Request</h3>
                        <p class="text-sm font-medium text-slate-500">Tell us what's wrong</p>
                    </div>
                    <button @click="showNewTicketModal = false" class="p-2 text-slate-400 hover:text-slate-900 transition-colors">
                        <X class="w-6 h-6" />
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-8 space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Priority Level</label>
                        <div class="grid grid-cols-3 gap-4">
                            <button 
                                v-for="p in ['low', 'medium', 'high']" 
                                :key="p"
                                type="button"
                                @click="form.priority = p"
                                :class="[
                                    'py-3 rounded-2xl border-2 font-black text-xs uppercase tracking-widest transition-all',
                                    form.priority === p ? 'border-indigo-600 bg-indigo-50 text-indigo-600 shadow-sm' : 'border-slate-100 text-slate-400 hover:border-slate-200'
                                ]"
                            >
                                {{ p }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Issue Description</label>
                        <textarea 
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-2xl border-slate-200 focus:border-indigo-600 focus:ring-0 text-slate-700 font-medium placeholder-slate-300"
                            placeholder="Please describe your issue in detail..."
                        ></textarea>
                    </div>
                    <div v-if="form.errors.error" class="p-4 bg-red-50 text-red-600 rounded-2xl text-xs font-bold border border-red-100">
                        {{ form.errors.error }}
                    </div>
                    <button 
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black shadow-lg shadow-indigo-100 hover:bg-indigo-700 disabled:opacity-50 transition-all"
                    >
                        {{ form.processing ? 'Opening Ticket...' : 'Submit Support Ticket' }}
                    </button>
                </form>
            </div>
        </div>
    </CustomerLayout>
</template>
