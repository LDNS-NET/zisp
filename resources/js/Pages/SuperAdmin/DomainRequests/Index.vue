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
    MessageSquare
} from 'lucide-vue-next';

defineProps({
    requests: Object,
});

const selectedRequest = ref(null);
const showStatusModal = ref(false);
const showDeleteModal = ref(false);
const newStatus = ref('');
const rejectionReason = ref('');

const openStatusModal = (request, status) => {
    selectedRequest.value = request;
    newStatus.value = status;
    rejectionReason.value = request.rejection_reason || '';
    showStatusModal.value = true;
};

const openDeleteModal = (request) => {
    selectedRequest.value = request;
    showDeleteModal.value = true;
};

const updateStatus = () => {
    router.patch(route('superadmin.domain-requests.update', selectedRequest.value.id), {
        status: newStatus.value,
        rejection_reason: rejectionReason.value
    }, {
        onSuccess: () => {
            showStatusModal.value = false;
            selectedRequest.value = null;
            rejectionReason.value = '';
        }
    });
};

const confirmDelete = () => {
    router.delete(route('superadmin.domain-requests.destroy', selectedRequest.value.id), {
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
                                                <p v-if="request.status === 'rejected' && request.rejection_reason" class="text-xs text-red-500 max-w-[200px] truncate" :title="request.rejection_reason">
                                                    Reason: {{ request.rejection_reason }}
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
                                            <div class="flex justify-end gap-2">
                                                <button 
                                                    v-if="request.status === 'pending'"
                                                    @click="openStatusModal(request, 'accepted')"
                                                    class="p-2 text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors" 
                                                    title="Accept Request"
                                                >
                                                    <CheckCircle2 class="w-5 h-5" />
                                                </button>
                                                <button 
                                                    v-if="request.status === 'pending'"
                                                    @click="openStatusModal(request, 'rejected')"
                                                    class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" 
                                                    title="Reject Request"
                                                >
                                                    <XCircle class="w-5 h-5" />
                                                </button>
                                                <button 
                                                    @click="openDeleteModal(request)"
                                                    class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors" 
                                                    title="Delete Request"
                                                >
                                                    <Trash2 class="w-5 h-5" />
                                                </button>
                                            </div>
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

        <!-- Status Update Modal -->
        <Modal :show="showStatusModal" @close="showStatusModal = false" maxWidth="md">
            <div class="p-6 dark:bg-slate-900">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ newStatus === 'accepted' ? 'Accept Domain Request' : 'Reject Domain Request' }}
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

                    <div v-if="newStatus === 'rejected'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rejection Reason</label>
                        <textarea 
                            v-model="rejectionReason"
                            rows="3"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Type the reason for rejection..."
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
                            :class="newStatus === 'accepted' ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-500/20' : 'bg-red-600 hover:bg-red-700 shadow-red-500/20'"
                        >
                            Confirm {{ newStatus }}
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
