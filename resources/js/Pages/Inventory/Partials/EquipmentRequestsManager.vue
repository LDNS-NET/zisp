<!-- resources/js/Pages/Inventory/Partials/EquipmentRequestsManager.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import Card from '@/Components/Card.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import {
    Wrench,
    Search,
    Clock,
    CheckCircle,
    XCircle,
    AlertTriangle,
    RefreshCw,
    X,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    equipment: Object,
    users: Array,
});

const requests = ref([]);
const loading = ref(false);
const statusFilter = ref('all');
const selectedRequest = ref(null);
const showDetailsDialog = ref(false);

onMounted(() => {
    fetchRequests();
});

const fetchRequests = async () => {
    loading.value = true;
    try {
        const params = {};
        if (statusFilter.value !== 'all') {
            params.status = statusFilter.value;
        }

        const response = await axios.get(route('equipment.requests.index'), {
            params,
        });
        requests.value = response.data;
    } catch (error) {
        console.error('Error fetching requests:', error);
    } finally {
        loading.value = false;
    }
};

const handleApproveRequest = async (requestId) => {
    try {
        await axios.post(route('equipment.requests.approve', requestId));
        await fetchRequests();
    } catch (error) {
        console.error('Error approving request:', error);
    }
};

const handleRejectRequest = async (requestId) => {
    try {
        await axios.post(route('equipment.requests.reject', requestId));
        await fetchRequests();
    } catch (error) {
        console.error('Error rejecting request:', error);
    }
};

const getStatusIcon = (status) => {
    switch (status) {
        case 'PENDING':
            return Clock;
        case 'APPROVED':
            return CheckCircle;
        case 'REJECTED':
            return XCircle;
        case 'FULFILLED':
            return CheckCircle;
        default:
            return Clock;
    }
};

const getStatusVariant = (status) => {
    switch (status) {
        case 'PENDING':
            return 'secondary';
        case 'APPROVED':
            return 'default';
        case 'REJECTED':
            return 'destructive';
        case 'FULFILLED':
            return 'outline';
        default:
            return 'outline';
    }
};

const getPriorityVariant = (priority) => {
    switch (priority) {
        case 'URGENT':
            return 'destructive';
        case 'HIGH':
            return 'default';
        case 'MEDIUM':
            return 'secondary';
        case 'LOW':
            return 'outline';
        default:
            return 'outline';
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center"
        >
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Equipment Requests
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Manage technician equipment requests and approvals
                </p>
            </div>
            <SecondaryButton @click="fetchRequests">
                <RefreshCw class="mr-2 h-4 w-4" />
                Refresh
            </SecondaryButton>
        </div>

        <!-- Filters -->
        <Card>
            <div class="p-4">
                <select
                    v-model="statusFilter"
                    @change="fetchRequests"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800 sm:w-64"
                >
                    <option value="all">All Status</option>
                    <option value="PENDING">Pending</option>
                    <option value="APPROVED">Approved</option>
                    <option value="REJECTED">Rejected</option>
                    <option value="FULFILLED">Fulfilled</option>
                </select>
            </div>
        </Card>

        <!-- Requests Table -->
        <Card>
            <template #header>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    Equipment Requests ({{ requests.length }})
                </h3>
            </template>

            <div v-if="loading" class="flex justify-center py-8">
                <div
                    class="h-8 w-8 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
                ></div>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50"
                        >
                            <th class="px-6 py-3 text-left font-semibold">
                                Technician
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Reason
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Items
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Priority
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Requested
                            </th>
                            <th class="px-6 py-3 text-right font-semibold">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <tr
                            v-for="request in requests"
                            :key="request.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{
                                        request.technician?.name ||
                                        `Technician #${request.technician_id}`
                                    }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ request.technician?.email }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    class="max-w-xs truncate"
                                    :title="request.reason"
                                >
                                    {{ request.reason }}
                                </div>
                                <div
                                    v-if="request.notes"
                                    class="truncate text-sm text-gray-500"
                                    :title="request.notes"
                                >
                                    {{ request.notes }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    {{ request.items?.length || 0 }} item{{
                                        request.items?.length !== 1 ? 's' : ''
                                    }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{
                                        request.items
                                            ?.map((i) => i.item?.name)
                                            .join(', ')
                                    }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge
                                    :variant="
                                        getPriorityVariant(request.priority)
                                    "
                                >
                                    {{ request.priority }}
                                </StatusBadge>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge
                                    :variant="getStatusVariant(request.status)"
                                    class="flex items-center gap-1"
                                >
                                    <component
                                        :is="getStatusIcon(request.status)"
                                        class="h-3 w-3"
                                    />
                                    {{ request.status }}
                                </StatusBadge>
                            </td>
                            <td class="px-6 py-4">
                                {{
                                    new Date(
                                        request.requested_at ||
                                            request.created_at,
                                    ).toLocaleDateString()
                                }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="
                                            selectedRequest = request;
                                            showDetailsDialog = true;
                                        "
                                        class="rounded border px-2 py-1 text-xs hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        View
                                    </button>
                                    <template
                                        v-if="request.status === 'PENDING'"
                                    >
                                        <button
                                            @click="
                                                handleApproveRequest(request.id)
                                            "
                                            class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            @click="
                                                handleRejectRequest(request.id)
                                            "
                                            class="rounded border px-2 py-1 text-xs hover:bg-gray-50 dark:hover:bg-gray-800"
                                        >
                                            Reject
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!requests.length">
                            <td
                                colspan="7"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                <Wrench
                                    class="mx-auto mb-4 h-12 w-12 text-gray-300"
                                />
                                <p>No equipment requests found</p>
                                <p
                                    v-if="statusFilter !== 'all'"
                                    class="mt-1 text-sm"
                                >
                                    Try adjusting your status filter
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Request Details Modal -->
        <Modal
            :show="showDetailsDialog"
            @close="showDetailsDialog = false"
            maxWidth="2xl"
        >
            <div class="bg-white p-6 dark:bg-gray-800">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-bold dark:text-white">
                            Request Details
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Request #{{ selectedRequest?.id }}
                        </p>
                    </div>
                    <button
                        @click="showDetailsDialog = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div v-if="selectedRequest" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Technician
                            </p>
                            <p class="dark:text-white">
                                {{ selectedRequest.technician?.name }}
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ selectedRequest.technician?.email }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                Priority
                            </p>
                            <StatusBadge
                                :variant="
                                    getPriorityVariant(selectedRequest.priority)
                                "
                            >
                                {{ selectedRequest.priority }}
                            </StatusBadge>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-500">Reason</p>
                        <p class="dark:text-white">
                            {{ selectedRequest.reason }}
                        </p>
                    </div>

                    <div v-if="selectedRequest.notes">
                        <p class="text-sm font-medium text-gray-500">Notes</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ selectedRequest.notes }}
                        </p>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-500">
                            Requested Items
                        </p>
                        <div class="space-y-2">
                            <div
                                v-for="(item, index) in selectedRequest.items"
                                :key="index"
                                class="flex items-center justify-between rounded border p-2 dark:border-gray-700"
                            >
                                <div>
                                    <div class="font-medium dark:text-white">
                                        {{ item.item?.name }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Quantity: {{ item.quantity }}
                                        <span v-if="item.serial">
                                            • Serial:
                                            {{ item.serial.serial }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-6 flex justify-end gap-3 border-t pt-4 dark:border-gray-700"
                    >
                        <SecondaryButton @click="showDetailsDialog = false"
                            >Close</SecondaryButton
                        >
                        <template v-if="selectedRequest.status === 'PENDING'">
                            <PrimaryButton
                                @click="
                                    handleApproveRequest(selectedRequest.id)
                                "
                            >
                                Approve Request
                            </PrimaryButton>
                            <SecondaryButton
                                @click="handleRejectRequest(selectedRequest.id)"
                            >
                                Reject
                            </SecondaryButton>
                        </template>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
