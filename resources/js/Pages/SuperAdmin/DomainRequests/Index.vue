<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Modal from '@/Components/Modal.vue';
import { 
    Globe, 
    User, 
    Clock,
    CheckCircle2,
    XCircle,
    X,
    AlertCircle,
    Trash2,
    MessageSquare,
    Ban,
    MoreVertical,
    Eye
} from 'lucide-vue-next';

defineProps({
    requests: Object,
});

const selectedRequest = ref(null);
const showActionsModal = ref(false);
const showStatusModal = ref(false);
const showDeleteModal = ref(false);
const showDetailsModal = ref(false);
const newStatus = ref('');
const adminMessage = ref('');

const openActions = (request) => {
    selectedRequest.value = request;
    showActionsModal.value = true;
};

const openStatusModal = (request, status) => {
    selectedRequest.value = request;
    newStatus.value = status;
    adminMessage.value = request.admin_message || request.rejection_reason || '';
    showStatusModal.value = true;
    showActionsModal.value = false;
};

const openDeleteModal = (request) => {
    selectedRequest.value = request;
    showDeleteModal.value = true;
    showActionsModal.value = false;
};

const openDetailsModal = (request) => {
    selectedRequest.value = request;
    showDetailsModal.value = true;
    showActionsModal.value = false;
};

const updateStatus = () => {
    router.patch(route('superadmin.requests.domains.update', selectedRequest.value.id), {
        status: newStatus.value,
        admin_message: adminMessage.value
    }, {
        onSuccess: () => {
            showStatusModal.value = false;
            selectedRequest.value = null;
            adminMessage.value = '';
        }
    });
};

const confirmDelete = () => {
    router.delete(route('superadmin.requests.domains.destroy', selectedRequest.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            selectedRequest.value = null;
        }
    });
};

const getStatusColor = (status) => {
    switch (status) {
        case 'pending': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400';
        case 'accepted': return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400';
        case 'rejected': return 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400';
        case 'revoked': return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
        default: return 'bg-gray-100 text-gray-700';
    }
};
</script>

<template>
    <Head title="Domain Requests" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                    Domain Requests
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white dark:bg-slate-900 shadow-sm sm:rounded-lg border border-gray-200 dark:border-slate-800">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div v-if="requests.data.length === 0" class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-800 mb-4">
                                <Globe class="w-8 h-8 text-gray-400" />
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No requests found</h3>
                            <p class="text-gray-500 dark:text-gray-400">There are no domain requests at the moment.</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-slate-800">
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Tenant</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Requested Domain</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                    <tr v-for="request in requests.data" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                                    <User class="w-5 h-5" />
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900 dark:text-white">{{ request.tenant?.name || 'Unknown' }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ request.tenant_id }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="text-sm font-medium capitalize text-gray-700 dark:text-gray-300">
                                                {{ request.type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm font-mono text-gray-600 dark:text-gray-300">
                                                {{ request.requested_domain }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col gap-1">
                                                <span :class="['px-2.5 py-1 rounded-full text-xs font-medium capitalize w-fit', getStatusColor(request.status)]">
                                                    {{ request.status }}
                                                </span>
                                                <p v-if="request.admin_message || request.rejection_reason" class="text-xs text-gray-500 dark:text-gray-400 max-w-[200px] truncate" :title="request.admin_message || request.rejection_reason">
                                                    {{ request.admin_message || request.rejection_reason }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                                <Clock class="w-4 h-4" />
                                                {{ new Date(request.created_at).toLocaleDateString() }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <button 
                                                @click="openActions(request)"
                                                class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-700" 
                                                title="Manage Request"
                                            >
                                                <MoreVertical class="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="requests.links.length > 3" class="mt-6 flex justify-center">
                            <nav class="flex gap-1">
                                <template v-for="(link, k) in requests.links" :key="k">
                                    <div v-if="link.url === null" class="px-4 py-2 text-sm text-gray-400 border border-gray-200 dark:border-slate-800 rounded-lg" v-html="link.label"></div>
                                    <Link v-else :href="link.url" class="px-4 py-2 text-sm border border-gray-200 dark:border-slate-800 rounded-lg transition-colors" :class="{'bg-blue-600 text-white border-blue-600': link.active, 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-800': !link.active}" v-html="link.label"></Link>
                                </template>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Modal -->
        <Modal :show="showActionsModal" @close="showActionsModal = false" maxWidth="sm">
            <div class="p-6 dark:bg-slate-900">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Manage Request</h3>
                    <button @click="showActionsModal = false" class="text-gray-400 hover:text-gray-500">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-3">
                    <button 
                        @click="openDetailsModal(selectedRequest)"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors border border-gray-100 dark:border-slate-700"
                    >
                        <Eye class="w-5 h-5 text-blue-500" />
                        View Details
                    </button>

                    <button 
                        v-if="selectedRequest?.status === 'pending'"
                        @click="openStatusModal(selectedRequest, 'accepted')"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors border border-emerald-100 dark:border-emerald-900/30"
                    >
                        <CheckCircle2 class="w-5 h-5" />
                        Accept Request
                    </button>

                    <button 
                        v-if="selectedRequest?.status === 'pending'"
                        @click="openStatusModal(selectedRequest, 'rejected')"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors border border-red-100 dark:border-red-900/30"
                    >
                        <XCircle class="w-5 h-5" />
                        Reject Request
                    </button>

                    <button 
                        v-if="selectedRequest?.status === 'accepted'"
                        @click="openStatusModal(selectedRequest, 'revoked')"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors border border-orange-100 dark:border-orange-900/30"
                    >
                        <Ban class="w-5 h-5" />
                        Revoke Domain
                    </button>

                    <div class="border-t border-gray-100 dark:border-slate-800 my-2"></div>

                    <button 
                        @click="openDeleteModal(selectedRequest)"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors border border-gray-100 dark:border-slate-700"
                    >
                        <Trash2 class="w-5 h-5 text-gray-400" />
                        Delete Request
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Status Update Modal -->
        <Modal :show="showStatusModal" @close="showStatusModal = false" maxWidth="md">
            <div class="p-6 dark:bg-slate-900">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ newStatus === 'accepted' ? 'Accept Domain Request' : (newStatus === 'rejected' ? 'Reject Domain Request' : 'Revoke Domain Access') }}
                    </h3>
                    <button @click="showStatusModal = false" class="text-gray-400 hover:text-gray-500">
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <div class="space-y-4" v-if="selectedRequest">
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-slate-800 border border-gray-100 dark:border-slate-700">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Tenant:</span>
                                <p class="font-medium text-gray-900 dark:text-white">{{ selectedRequest.tenant?.name }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Type:</span>
                                <p class="font-medium text-gray-900 dark:text-white capitalize">{{ selectedRequest.type }}</p>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-500">Requested Domain:</span>
                                <p class="font-mono font-medium text-blue-600 dark:text-blue-400">{{ selectedRequest.requested_domain }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Message to Tenant (Optional)
                        </label>
                        <textarea 
                            v-model="adminMessage"
                            rows="3"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                            :placeholder="newStatus === 'accepted' ? 'Provide DNS instructions or welcome message...' : 'Reason for this action...'"
                        ></textarea>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button 
                            @click="showStatusModal = false"
                            class="flex-1 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-800 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-50 dark:hover:bg-slate-800 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="updateStatus"
                            class="flex-1 px-4 py-2 rounded-lg text-white font-semibold transition shadow-lg"
                            :class="newStatus === 'accepted' ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-500/20' : (newStatus === 'rejected' ? 'bg-red-600 hover:bg-red-700 shadow-red-500/20' : 'bg-orange-600 hover:bg-orange-700 shadow-orange-500/20')"
                        >
                            Confirm {{ newStatus }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Details Modal -->
        <Modal :show="showDetailsModal" @close="showDetailsModal = false" maxWidth="md">
            <div class="p-6 dark:bg-slate-900" v-if="selectedRequest">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Request Details</h3>
                    <button @click="showDetailsModal = false" class="text-gray-400 hover:text-gray-500">
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-100 dark:border-slate-700">
                            <span class="text-xs text-gray-500 uppercase font-bold tracking-wider">Status</span>
                            <div class="mt-1">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium capitalize', getStatusColor(selectedRequest.status)]">
                                    {{ selectedRequest.status }}
                                </span>
                            </div>
                        </div>
                        <div class="p-3 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-100 dark:border-slate-700">
                            <span class="text-xs text-gray-500 uppercase font-bold tracking-wider">Type</span>
                            <p class="mt-1 font-medium text-gray-900 dark:text-white capitalize">{{ selectedRequest.type }}</p>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800">
                        <span class="text-xs text-blue-600 dark:text-blue-400 uppercase font-bold tracking-wider">Requested Domain</span>
                        <p class="mt-1 font-mono text-lg font-bold text-blue-700 dark:text-blue-300">{{ selectedRequest.requested_domain }}</p>
                    </div>

                    <div v-if="selectedRequest.admin_message || selectedRequest.rejection_reason" class="space-y-2">
                        <span class="text-xs text-gray-500 uppercase font-bold tracking-wider">Admin Message</span>
                        <div class="p-4 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-100 dark:border-slate-700 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                            {{ selectedRequest.admin_message || selectedRequest.rejection_reason }}
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button 
                            @click="showDetailsModal = false"
                            class="px-6 py-2 rounded-lg bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-200 dark:hover:bg-slate-700 transition"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false" maxWidth="sm">
            <div class="p-6 dark:bg-slate-900 text-center">
                <div class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-600 dark:text-red-400 mx-auto mb-4">
                    <Trash2 class="w-8 h-8" />
                </div>
                
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Delete Request?</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-8">
                    Are you sure you want to delete this domain request? This action cannot be undone.
                </p>

                <div class="flex gap-3">
                    <button 
                        @click="showDeleteModal = false"
                        class="flex-1 px-4 py-2 rounded-lg border border-gray-200 dark:border-slate-800 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-50 dark:hover:bg-slate-800 transition"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="confirmDelete"
                        class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-white font-semibold hover:bg-red-700 transition shadow-lg shadow-red-500/20"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </Modal>
    </SuperAdminLayout>
</template>
