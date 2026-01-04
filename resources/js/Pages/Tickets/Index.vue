<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextArea from '@/Components/TextArea.vue';
import InputError from '@/Components/InputError.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { 
    Plus, 
    Save, 
    X, 
    Edit, 
    Trash2, 
    CheckCheck, 
    Ticket, 
    MoreVertical, 
    XCircle,
    User,
    AlertCircle,
    AlertCircle,
    FileText,
    Search
} from 'lucide-vue-next';

const props = defineProps({
    tickets: Object,
    statusFilter: String,
    users: Array,
    leads: Array,
    filters: Object,
});

const showModal = ref(false);
const editing = ref(null);
const selectedTenantTickets = ref([]);
const resolving = ref(null);
const showDescriptionModal = ref(false);
const fullDescription = ref('');
const showActionsModal = ref(false);
const selectedTicket = ref(null);

const form = useForm({
    client_type: 'user',
    client_id: '',
    priority: 'medium',
    status: 'open',
    description: '',
    resolution_note: '',
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.status = 'open';
    form.priority = 'medium';
    showModal.value = true;
};

const openEdit = (ticket) => {
    editing.value = ticket.id;
    form.client_type = ticket.client_type;
    form.client_id = ticket.client_id;
    form.priority = ticket.priority;
    form.status = ticket.status;
    form.description = ticket.description;
    showModal.value = true;
};

const openActions = (ticket) => {
    selectedTicket.value = ticket;
    showActionsModal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.put(route('tickets.update', editing.value), {
            onSuccess: () => (showModal.value = false),
        });
    } else {
        form.post(route('tickets.store'), {
            onSuccess: () => (showModal.value = false),
        });
    }
};

//resolve ticket
const openResolve = (ticket) => {
    resolving.value = ticket.id;
    form.resolution_note = '';
};

const resolveTicket = () => {
    form.put(route('tickets.resolve', resolving.value), {
        onSuccess: () => {
            resolving.value = null;
            form.resolution_note = '';
        },
    });
};

watch(
    () => form.client_type,
    () => {
        form.client_id = '';
    },
);

const selectAll = ref(false);

watch(selectAll, (val) => {
    if (val) {
        selectedTenantTickets.value = props.tickets.data.map((lead) => lead.id);
    } else {
        selectedTenantTickets.value = [];
    }
});

const allIds = computed(() => props.tickets.data.map((l) => l.id));

watch(selectedTenantTickets, (val) => {
    selectAll.value = val.length === allIds.value.length && allIds.value.length > 0;
});

const remove = (ticket) => {
    if (confirm('Delete this ticket?')) {
        router.delete(route('tickets.destroy', ticket.id));
    }
};

const changeFilter = (status) => {
    router.visit(route('tickets.index', { status, search: search.value }), {
        preserveScroll: true,
    });
};

const clients = computed(() =>
    form.client_type === 'user' ? props.users : props.leads,
);

const search = ref(props.filters?.search || '');
let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('tickets.index'),
            { search: value, status: props.statusFilter },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
});

//bulk delete
const bulkDelete = () => {
    if (!selectedTenantTickets.value.length) return;
    if (!confirm('Are you sure you want to delete selected Tickets?')) return;

    router.delete(route('tickets.bulk-delete'), {
        data: { ids: selectedTenantTickets.value },
        onSuccess: () => {
            selectedTenantTickets.value = [];
            selectAll.value = false;
        },
    });
};

// truncate long descriptions
function truncateWords(text, wordCount = 2) {
    if (!text) return '';
    const words = text.split(' ');
    return words.length > wordCount
        ? words.slice(0, wordCount).join(' ') + '…'
        : text;
}

function showDescription(description) {
    fullDescription.value = description;
    showDescriptionModal.value = true;
}
</script>

<template>
    <Head title="Tickets" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Ticket class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        Tickets
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage support requests and issues
                    </p>
                </div>
                <PrimaryButton @click="openCreate" class="flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    <span>Add Ticket</span>
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters and Bulk Actions -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
                <div class="flex p-1 bg-gray-100 dark:bg-slate-900 rounded-lg">
                    <button
                        @click="changeFilter('open')"
                        :class="[
                            'px-4 py-2 text-sm font-medium rounded-md transition-all',
                            props.statusFilter === 'open'
                                ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                        ]"
                    >
                        Open
                    </button>
                    <button
                        @click="changeFilter('closed')"
                        :class="[
                            'px-4 py-2 text-sm font-medium rounded-md transition-all',
                            props.statusFilter === 'closed'
                                ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                        ]"
                    >
                        Closed
                    </button>
                </div>

                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search tickets..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out"
                    />
                </div>

                <div v-if="selectedTenantTickets.length" class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ selectedTenantTickets.length }} selected</span>
                    <DangerButton @click="bulkDelete" class="flex items-center gap-2">
                        <Trash2 class="w-4 h-4" /> Delete
                    </DangerButton>
                </div>
            </div>

            <!-- Tickets Table (Desktop) / Cards (Mobile) -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left w-10">
                                    <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ticket #</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Priority</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" :value="ticket.id" v-model="selectedTenantTickets" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-mono text-sm text-blue-600 dark:text-blue-400">#{{ ticket.ticket_number }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <User class="w-4 h-4 text-gray-400" />
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ ticket.client?.full_name || ticket.client?.name || 'Unknown' }}
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 capitalize">({{ ticket.client_type }})</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                        ticket.priority === 'high' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                                        ticket.priority === 'medium' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' :
                                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    ]">
                                        {{ ticket.priority }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                        ticket.status === 'open' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    ]">
                                        {{ ticket.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button @click="showDescription(ticket.description)" class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                                        <FileText class="w-3 h-3" />
                                        {{ truncateWords(ticket.description, 4) }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openActions(ticket)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="tickets.data.length === 0">
                                <td colspan="7" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <Ticket class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                        <p class="text-lg font-medium">No tickets found</p>
                                        <p class="text-sm">Create a new ticket to get started</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden">
                    <div v-if="selectedTenantTickets.length > 0" class="p-4 bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
                        <label class="flex items-center">
                            <input 
                                type="checkbox"
                                v-model="selectAll"
                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600"
                            />
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Select All</span>
                        </label>
                    </div>

                    <div class="divide-y divide-gray-200 dark:divide-slate-700">
                        <div v-for="ticket in tickets.data" :key="ticket.id" class="p-4 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                            <div class="flex items-start gap-3">
                                <input 
                                    type="checkbox"
                                    :value="ticket.id"
                                    v-model="selectedTenantTickets"
                                    class="mt-1 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <div class="h-8 w-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                                <Ticket class="w-4 h-4" />
                                            </div>
                                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                                                #{{ ticket.ticket_number }}
                                            </h3>
                                        </div>
                                        <button @click="openActions(ticket)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <MoreVertical class="w-5 h-5" />
                                        </button>
                                    </div>
                                    
                                    <div class="ml-10 space-y-1">
                                        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                            <User class="w-3 h-3" />
                                            {{ ticket.client?.full_name || ticket.client?.name || 'Unknown' }}
                                            <span class="text-gray-400 capitalize">({{ ticket.client_type }})</span>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs">
                                            <span :class="[
                                                'px-2 py-0.5 text-[10px] font-semibold rounded-full capitalize',
                                                ticket.priority === 'high' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                                                ticket.priority === 'medium' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' :
                                                'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                            ]">
                                                {{ ticket.priority }}
                                            </span>
                                            <span :class="[
                                                'px-2 py-0.5 text-[10px] font-semibold rounded-full capitalize',
                                                ticket.status === 'open' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                                                'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                            ]">
                                                {{ ticket.status }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-slate-900/50 p-2 rounded mt-2">
                                            {{ truncateWords(ticket.description, 10) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="tickets.data.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <Ticket class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                <p class="text-lg font-medium">No tickets found</p>
                                <p class="text-sm">Create a new ticket to get started</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-show="tickets.total > 0" class="flex justify-center mt-6">
                <Pagination 
                    :links="tickets.links" 
                    :per-page="tickets.per_page"
                    :total="tickets.total"
                    :from="tickets.from"
                    :to="tickets.to"
                />
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ editing ? 'Edit Ticket' : 'Create Ticket' }}
                </h3>
                <form @submit.prevent="submit">
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="client_type" value="Client Type" />
                            <select v-model="form.client_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="user">User</option>
                                <option value="lead">Lead</option>
                            </select>
                            <InputError :message="form.errors.client_type" />
                        </div>
                        <div>
                            <InputLabel for="client_id" value="Client" />
                            <select v-model="form.client_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option disabled value="">Select Client</option>
                                <option v-for="client in clients" :key="client.id" :value="client.id">
                                    {{ client.full_name || client.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.client_id" />
                        </div>
                        <div>
                            <InputLabe for="priority" value="Priority" />
                            <select v-model="form.priority" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                            <InputError :message="form.errors.priority" />
                        </div>
                        <div>
                            <InputLabel for="status" value="Status" />
                            <select v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                        <div>
                            <InputLabel for="description" value="Description" />
                            <TextArea v-model="form.description" class="mt-1 block w-full" rows="3" />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="showModal = false">Cancel</DangerButton>
                        <PrimaryButton :disabled="form.processing">{{ editing ? 'Update' : 'Save' }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Actions Modal (Compact) -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-4 dark:bg-slate-800 dark:text-white" v-if="selectedTicket">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4">
                        Ticket #{{ selectedTicket.ticket_number }}
                    </h3>
                    <button @click="showActionsModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-1">
                    <button v-if="selectedTicket.status === 'open'" @click="openResolve(selectedTicket); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 group-hover:bg-green-100 dark:group-hover:bg-green-900/40">
                            <CheckCheck class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Resolve Ticket</span>
                    </button>

                    <button @click="openEdit(selectedTicket); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40">
                            <Edit class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Edit Ticket</span>
                    </button>

                    <button @click="showDescription(selectedTicket.description); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-purple-50 text-purple-600 dark:bg-purple-900/20 dark:text-purple-400 group-hover:bg-purple-100 dark:group-hover:bg-purple-900/40">
                            <FileText class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">View Description</span>
                    </button>

                    <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>

                    <button @click="remove(selectedTicket); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 group-hover:bg-red-100 dark:group-hover:bg-red-900/40">
                            <Trash2 class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">Delete Ticket</span>
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Resolve Modal -->
        <Modal :show="!!resolving" @close="resolving = null">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Resolve Ticket</h3>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Resolution Note" />
                        <TextArea v-model="form.resolution_note" class="mt-1 block w-full" rows="4" />
                        <InputError :message="form.errors.resolution_note" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <DangerButton type="button" @click="resolving = null">Cancel</DangerButton>
                    <PrimaryButton :disabled="form.processing" @click="resolveTicket">
                        <CheckCheck class="mr-1 h-4 w-4" /> Mark as Resolved
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Description Modal -->
        <Modal :show="showDescriptionModal" @close="showDescriptionModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Description</h3>
                <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                    {{ fullDescription }}
                </div>
                <div class="mt-6 flex justify-end">
                    <PrimaryButton @click="showDescriptionModal = false">Close</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
