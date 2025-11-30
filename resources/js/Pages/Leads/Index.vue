<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Pagination from '@/Components/Pagination.vue';
import TextArea from '@/Components/TextArea.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { 
    Plus, 
    Search, 
    Edit, 
    Trash2, 
    Save, 
    X, 
    Phone, 
    Mail, 
    MapPin, 
    User,
    MoreVertical,
    XCircle,
    Users
} from 'lucide-vue-next';

const props = defineProps({
    leads: Object,
    filters: Object,
});

const showModal = ref(false);
const editing = ref(null);
const selectedTenantLeads = ref([]);
const showAddressModal = ref(false);
const fullAddress = ref('');
const search = ref(props.filters?.search || '');
const showActionsModal = ref(false);
const selectedLead = ref(null);

const form = useForm({
    name: '',
    phone_number: '',
    address: '',
    email_address: '',
    status: 'new',
});

function openCreate() {
    form.reset();
    editing.value = null;
    showModal.value = true;
}

function openEdit(lead) {
    editing.value = lead.id;
    form.name = lead.name;
    form.phone_number = lead.phone_number;
    form.email_address = lead.email_address;
    form.address = lead.address;
    form.status = lead.status || 'new';
    showModal.value = true;
}

function openActions(lead) {
    selectedLead.value = lead;
    showActionsModal.value = true;
}

const selectAll = ref(false);

watch(selectAll, (val) => {
    if (val) {
        selectedTenantLeads.value = props.leads.data.map((lead) => lead.id);
    } else {
        selectedTenantLeads.value = [];
    }
});

const allIds = computed(() => props.leads.data.map((l) => l.id));

watch(selectedTenantLeads, (val) => {
    selectAll.value = val.length === allIds.value.length && allIds.value.length > 0;
});

function submit() {
    if (editing.value) {
        form.put(route('leads.update', editing.value), {
            onSuccess: () => (showModal.value = false),
        });
    } else {
        form.post(route('leads.store'), {
            onSuccess: () => (showModal.value = false),
        });
    }
}

function remove(lead) {
    if (confirm('Delete this lead?')) {
        router.delete(route('leads.destroy', lead.id));
    }
}

const bulkDelete = () => {
    if (!selectedTenantLeads.value.length) return;
    if (!confirm('Are you sure you want to delete selected Leads?')) return;

    router.delete(route('leads.bulk-delete'), {
        data: { ids: selectedTenantLeads.value },
        onSuccess: () => {
            selectedTenantLeads.value = [];
            selectAll.value = false;
        },
    });
};

function truncateWords(text, wordCount = 2) {
    if (!text) return '';
    const words = text.split(' ');
    return words.length > wordCount
        ? words.slice(0, wordCount).join(' ') + '…'
        : text;
}

function showAddress(address) {
    fullAddress.value = address;
    showAddressModal.value = true;
}

// Debounce search
let timeout;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route('leads.index'),
            { search: value },
            { preserveScroll: true, replace: true }
        );
    }, 300);
});
</script>

<template>
    <Head title="Leads" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Users class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        Leads
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage potential clients and inquiries
                    </p>
                </div>
                <PrimaryButton @click="openCreate" class="flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    <span>Add Lead</span>
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Search and Bulk Actions -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-center bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm">
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search leads..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out"
                    />
                </div>

                <div v-if="selectedTenantLeads.length" class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ selectedTenantLeads.length }} selected</span>
                    <DangerButton @click="bulkDelete" class="flex items-center gap-2">
                        <Trash2 class="w-4 h-4" /> Delete
                    </DangerButton>
                </div>
            </div>

            <!-- Leads Table (Desktop) / Cards (Mobile) -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left">
                                    <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Address</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="lead in leads.data" :key="lead.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" :value="lead.id" v-model="selectedTenantLeads" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-slate-900 dark:border-slate-600" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold">
                                            {{ lead.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ lead.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                            <Phone class="w-3 h-3" /> {{ lead.phone_number }}
                                        </div>
                                        <div v-if="lead.email_address" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                            <Mail class="w-3 h-3" /> {{ lead.email_address }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button @click="showAddress(lead.address)" class="flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                        <MapPin class="w-3 h-3" />
                                        {{ truncateWords(lead.address, 3) }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full capitalize',
                                        lead.status === 'new' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                                        lead.status === 'contacted' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' :
                                        lead.status === 'converted' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    ]">
                                        {{ lead.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openActions(lead)" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="leads.data.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <User class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
                                        <p class="text-lg font-medium">No leads found</p>
                                        <p class="text-sm">Try adding a new lead</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden divide-y divide-gray-200 dark:divide-slate-700">
                    <div v-for="lead in leads.data" :key="lead.id" class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold">
                                    {{ lead.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ lead.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ lead.phone_number }}</div>
                                </div>
                            </div>
                            <span :class="[
                                'px-2 py-0.5 text-xs font-semibold rounded-full capitalize',
                                lead.status === 'new' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                                lead.status === 'contacted' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' :
                                lead.status === 'converted' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                                'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                            ]">
                                {{ lead.status }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-2 text-sm">
                            <div v-if="lead.email_address" class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                <Mail class="w-4 h-4 text-gray-400" /> {{ lead.email_address }}
                            </div>
                            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                <MapPin class="w-4 h-4 text-gray-400" /> {{ truncateWords(lead.address, 5) }}
                            </div>
                        </div>

                        <button @click="openActions(lead)" class="w-full flex items-center justify-center gap-2 py-2 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                            <MoreVertical class="w-4 h-4" /> Manage Lead
                        </button>
                    </div>
                    <div v-if="leads.data.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No leads found.
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="leads.links.length > 3" class="flex justify-center mt-6">
                <Pagination :links="leads.links" />
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ editing ? 'Edit Lead' : 'Add New Lead' }}
                </h3>
                <form @submit.prevent="submit">
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="name" value="Full Name" />
                            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required autofocus />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Phone Number" />
                            <TextInput id="phone" v-model="form.phone_number" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.phone_number" />
                        </div>
                        <div>
                            <InputLabel for="email" value="Email Address (Optional)" />
                            <TextInput id="email" v-model="form.email_address" type="email" class="mt-1 block w-full" />
                            <InputError :message="form.errors.email_address" />
                        </div>
                        <div>
                            <InputLabel for="address" value="Address" />
                            <TextArea id="address" v-model="form.address" class="mt-1 block w-full" rows="3" />
                            <InputError :message="form.errors.address" />
                        </div>
                        <div>
                            <InputLabel for="status" value="Status" />
                            <select v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="converted">Converted</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="showModal = false">Cancel</DangerButton>
                        <PrimaryButton :disabled="form.processing">{{ editing ? 'Update Lead' : 'Add Lead' }}</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Actions Modal (Compact) -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-4 dark:bg-slate-800 dark:text-white" v-if="selectedLead">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100 dark:border-slate-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate pr-4">
                        {{ selectedLead.name }}
                    </h3>
                    <button @click="showActionsModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-1">
                    <button @click="openEdit(selectedLead); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40">
                            <Edit class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Edit Lead</span>
                    </button>

                    <button @click="showAddress(selectedLead.address); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 group-hover:bg-green-100 dark:group-hover:bg-green-900/40">
                            <MapPin class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">View Address</span>
                    </button>

                    <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>

                    <button @click="remove(selectedLead); showActionsModal = false" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-left group">
                        <div class="p-1.5 rounded-md bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 group-hover:bg-red-100 dark:group-hover:bg-red-900/40">
                            <Trash2 class="w-4 h-4" />
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">Delete Lead</span>
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Address Modal -->
        <Modal :show="showAddressModal" @close="showAddressModal = false" maxWidth="sm">
            <div class="p-6 dark:bg-slate-800 dark:text-white">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Full Address</h3>
                <div class="bg-gray-50 dark:bg-slate-700 p-4 rounded-lg text-gray-700 dark:text-gray-300">
                    {{ fullAddress }}
                </div>
                <div class="mt-6 flex justify-end">
                    <PrimaryButton @click="showAddressModal = false">Close</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
