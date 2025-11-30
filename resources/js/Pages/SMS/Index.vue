<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    MessageSquare, 
    Search, 
    Plus, 
    Filter, 
    MoreVertical, 
    Edit, 
    Trash2, 
    Eye,
    CheckCircle,
    XCircle,
    Clock,
    Send
} from 'lucide-vue-next';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Pagination from '@/Components/Pagination.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
    smsLogs: Object,
    perPage: Number,
    filters: Object,
});

const toast = useToast();

// State
const showCreateModal = ref(false);
const showViewModal = ref(false);
const showActionsModal = ref(false);
const selectedSms = ref(null);
const selectedItems = ref([]);
const selectAll = ref(false);

const form = useForm({
    recipient_name: '',
    phone_number: '',
    message: '',
});

// Bulk Selection Logic
const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = props.smsLogs.data.map(sms => sms.id);
    } else {
        selectedItems.value = [];
    }
};

watch(selectedItems, (val) => {
    selectAll.value = val.length === props.smsLogs.data.length && props.smsLogs.data.length > 0;
});

// Actions
const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
    form.reset();
};

const openViewModal = (sms) => {
    selectedSms.value = sms;
    showViewModal.value = true;
};

const openActions = (sms) => {
    selectedSms.value = sms;
    showActionsModal.value = true;
};

const submitCreate = () => {
    form.post(route('sms.store'), {
        onSuccess: () => {
            closeCreateModal();
            toast.success('SMS sent successfully');
        },
        onError: () => {
            toast.error('Failed to send SMS');
        },
    });
};

const deleteSms = (sms) => {
    if (confirm('Are you sure you want to delete this SMS log?')) {
        router.delete(route('sms.destroy', sms.id), {
            preserveScroll: true,
            onSuccess: () => {
                showActionsModal.value = false;
                toast.success('SMS log deleted successfully');
            },
        });
    }
};

const bulkDelete = () => {
    if (!selectedItems.value.length) return;
    
    if (confirm(`Are you sure you want to delete ${selectedItems.value.length} SMS logs?`)) {
        router.delete(route('sms.bulk-delete'), {
            data: { ids: selectedItems.value },
            preserveScroll: true,
            onSuccess: () => {
                selectedItems.value = [];
                selectAll.value = false;
                toast.success('Selected logs deleted successfully');
            },
        });
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="SMS Logs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <MessageSquare class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                        SMS Logs
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        View and manage sent SMS messages
                    </p>
                </div>
                <PrimaryButton @click="openCreateModal" class="flex items-center gap-2">
                    <Send class="w-4 h-4" />
                    <span>Send SMS</span>
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Stats/Filters Section could go here -->

            <!-- Main Content -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <!-- Bulk Actions Toolbar -->
                <div v-if="selectedItems.length > 0" class="bg-blue-50 dark:bg-blue-900/20 px-4 py-3 flex items-center justify-between border-b border-blue-100 dark:border-blue-800">
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                        {{ selectedItems.length }} selected
                    </span>
                    <button 
                        @click="bulkDelete"
                        class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm font-medium flex items-center gap-2"
                    >
                        <Trash2 class="w-4 h-4" />
                        Delete Selected
                    </button>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left">
                                    <Checkbox 
                                        :checked="selectAll"
                                        @update:checked="val => { selectAll = val; toggleSelectAll() }"
                                    />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipient</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Message</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sent At</th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="sms in smsLogs.data" :key="sms.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Checkbox 
                                        :value="sms.id"
                                        v-model:checked="selectedItems"
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ sms.recipient_name || 'Unknown' }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ sms.phone_number }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-300 max-w-xs truncate" :title="sms.message">
                                        {{ sms.message }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                        :class="{
                                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': sms.status === 'sent',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': sms.status === 'pending',
                                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': sms.status === 'failed'
                                        }"
                                    >
                                        {{ sms.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(sms.sent_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openActions(sms)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <MoreVertical class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="smsLogs.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-4 mb-4">
                                            <MessageSquare class="w-8 h-8 text-gray-400 dark:text-gray-500" />
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">No SMS logs found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by sending a new message.</p>
                                        <div class="mt-6">
                                            <PrimaryButton @click="openCreateModal">
                                                <Send class="w-4 h-4 mr-2" />
                                                Send SMS
                                            </PrimaryButton>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="md:hidden">
                    <div v-if="selectedItems.length > 0" class="p-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <Checkbox 
                            :checked="selectAll"
                            @update:checked="val => { selectAll = val; toggleSelectAll() }"
                        >
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Select All</span>
                        </Checkbox>
                    </div>
                    
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="sms in smsLogs.data" :key="sms.id" class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-start gap-3">
                                <Checkbox 
                                    :value="sms.id"
                                    v-model:checked="selectedItems"
                                    class="mt-1"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ sms.recipient_name || sms.phone_number }}
                                        </h3>
                                        <button @click="openActions(sms)" class="text-gray-400">
                                            <MoreVertical class="w-5 h-5" />
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        {{ sms.phone_number }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2 mb-3">
                                        {{ sms.message }}
                                    </p>
                                    <div class="flex items-center justify-between">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium capitalize"
                                            :class="{
                                                'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': sms.status === 'sent',
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': sms.status === 'pending',
                                                'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': sms.status === 'failed'
                                            }"
                                        >
                                            {{ sms.status }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                            <Clock class="w-3 h-3" />
                                            {{ formatDate(sms.sent_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Empty State Mobile -->
                        <div v-if="smsLogs.data.length === 0" class="p-8 text-center">
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-4 mb-4 inline-flex">
                                <MessageSquare class="w-8 h-8 text-gray-400 dark:text-gray-500" />
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No SMS logs</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Start by sending a message.</p>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="smsLogs.total > smsLogs.per_page" class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 sm:px-6">
                    <Pagination :links="smsLogs.links" />
                </div>
            </div>
        </div>

        <!-- Create SMS Modal -->
        <Modal :show="showCreateModal" @close="closeCreateModal">
            <div class="p-6 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                        Send New SMS
                    </h2>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-6 h-6" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate">
                    <div class="space-y-4">
                        <div>
                            <InputLabel for="recipient_name" value="Recipient Name" />
                            <TextInput
                                id="recipient_name"
                                v-model="form.recipient_name"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="John Doe"
                            />
                            <InputError :message="form.errors.recipient_name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="phone_number" value="Phone Number" />
                            <TextInput
                                id="phone_number"
                                v-model="form.phone_number"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="+254..."
                                required
                            />
                            <InputError :message="form.errors.phone_number" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="message" value="Message" />
                            <textarea
                                id="message"
                                v-model="form.message"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300"
                                placeholder="Type your message here..."
                                required
                            ></textarea>
                            <InputError :message="form.errors.message" class="mt-2" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-right">
                                {{ form.message.length }} characters
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <DangerButton type="button" @click="closeCreateModal">
                            Cancel
                        </DangerButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            <Send class="w-4 h-4 mr-2" />
                            Send Message
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- View Modal -->
        <Modal :show="showViewModal" @close="showViewModal = false">
            <div class="p-6 dark:bg-gray-800" v-if="selectedSms">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                        SMS Details
                    </h2>
                    <button @click="showViewModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                        <XCircle class="w-6 h-6" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recipient</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white font-medium">
                                {{ selectedSms.recipient_name || 'N/A' }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Phone</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white font-medium">
                                {{ selectedSms.phone_number }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</label>
                            <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="{
                                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': selectedSms.status === 'sent',
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': selectedSms.status === 'pending',
                                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': selectedSms.status === 'failed'
                                }"
                            >
                                {{ selectedSms.status }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Sent At</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                {{ formatDate(selectedSms.sent_at) }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Message</label>
                        <div class="mt-1 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ selectedSms.message }}</p>
                        </div>
                    </div>

                    <div v-if="selectedSms.status === 'failed' && selectedSms.error_message" class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-100 dark:border-red-800">
                        <label class="block text-xs font-medium text-red-600 dark:text-red-400 uppercase mb-1">Error Details</label>
                        <p class="text-sm text-red-700 dark:text-red-300">{{ selectedSms.error_message }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <PrimaryButton @click="showViewModal = false">Close</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Actions Modal (Mobile/Desktop) -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-6 dark:bg-gray-800" v-if="selectedSms">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    Actions for {{ selectedSms.recipient_name || selectedSms.phone_number }}
                </h3>
                <div class="space-y-3">
                    <button 
                        @click="openViewModal(selectedSms); showActionsModal = false"
                        class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                    >
                        <Eye class="w-4 h-4 mr-3 text-blue-500" />
                        View Details
                    </button>
                    
                    <button 
                        @click="deleteSms(selectedSms)"
                        class="w-full flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                    >
                        <Trash2 class="w-4 h-4 mr-3" />
                        Delete Log
                    </button>
                </div>
                <div class="mt-6 flex justify-end">
                    <button 
                        @click="showActionsModal = false"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
