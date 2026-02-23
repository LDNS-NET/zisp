<!-- resources/js/Pages/Inventory/Partials/EquipmentUsageTracker.vue -->
<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Card from '@/Components/Card.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import {
    Users,
    Calendar,
    Plus,
    Wrench,
    CheckCircle,
    AlertTriangle,
    XCircle,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    equipment: Object,
    users: Array,
});

const usageHistory = ref([]);
const availableItems = ref([]);
const loading = ref(false);
const showLogDialog = ref(false);
const formLoading = ref(false);

const usageForm = useForm({
    itemId: null,
    technicianId: null,
    workOrderId: null,
    conditionBefore: 'good',
    conditionAfter: 'good',
    notes: '',
    usageDate: new Date().toISOString().split('T')[0],
});

onMounted(() => {
    fetchData();
});

const fetchData = async () => {
    loading.value = true;
    try {
        const [usageResponse, itemsResponse] = await Promise.all([
            axios.get(route('equipment.usage.history')),
            axios.get(route('equipment.index')),
        ]);

        usageHistory.value = usageResponse.data;
        availableItems.value = itemsResponse.data.equipment?.data || [];
    } catch (error) {
        console.error('Error fetching data:', error);
    } finally {
        loading.value = false;
    }
};

const handleLogUsage = () => {
    usageForm.post(route('equipment.usage.log'), {
        onSuccess: () => {
            showLogDialog.value = false;
            usageForm.reset();
            usageForm.usageDate = new Date().toISOString().split('T')[0];
            fetchData();
        },
    });
};

const getConditionVariant = (condition) => {
    switch (condition) {
        case 'excellent':
            return 'default';
        case 'good':
            return 'secondary';
        case 'fair':
            return 'outline';
        case 'poor':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getConditionIcon = (condition) => {
    switch (condition) {
        case 'excellent':
            return CheckCircle;
        case 'good':
            return CheckCircle;
        case 'fair':
            return AlertTriangle;
        case 'poor':
            return XCircle;
        default:
            return Wrench;
    }
};
</script>

<template>
    <div class="space-y-6">
        <Card>
            <template #header>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Users class="h-5 w-5 text-blue-600" />
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            Equipment Usage History
                        </h3>
                    </div>
                    <PrimaryButton @click="showLogDialog = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Log Usage
                    </PrimaryButton>
                </div>
                <p class="text-sm text-gray-500">
                    Track equipment usage and condition changes
                </p>
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
                                Date
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Equipment
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Technician
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Condition
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Work Order
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Notes
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <tr
                            v-for="usage in usageHistory"
                            :key="usage.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <span class="dark:text-white">{{
                                        new Date(
                                            usage.usage_date ||
                                                usage.created_at,
                                        ).toLocaleDateString()
                                    }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{ usage.item?.name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ usage.item?.description }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{
                                        usage.technician?.name ||
                                        `Tech #${usage.technician_id}`
                                    }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ usage.technician?.email }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex flex-col items-center">
                                        <StatusBadge
                                            :variant="
                                                getConditionVariant(
                                                    usage.condition_before,
                                                )
                                            "
                                            class="mb-1"
                                        >
                                            <component
                                                :is="
                                                    getConditionIcon(
                                                        usage.condition_before,
                                                    )
                                                "
                                                class="mr-1 h-3 w-3"
                                            />
                                            Before
                                        </StatusBadge>
                                        <StatusBadge
                                            :variant="
                                                getConditionVariant(
                                                    usage.condition_after,
                                                )
                                            "
                                        >
                                            <component
                                                :is="
                                                    getConditionIcon(
                                                        usage.condition_after,
                                                    )
                                                "
                                                class="mr-1 h-3 w-3"
                                            />
                                            After
                                        </StatusBadge>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="usage.work_order_id" class="text-sm">
                                    WO#{{ usage.work_order_id }}
                                </div>
                                <span v-else class="text-gray-400">N/A</span>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    class="max-w-xs truncate text-sm text-gray-500"
                                >
                                    {{ usage.notes || 'No notes' }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!usageHistory.length">
                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                <Wrench
                                    class="mx-auto mb-4 h-12 w-12 text-gray-300"
                                />
                                <p>No equipment usage recorded</p>
                                <p class="mt-1 text-sm">
                                    Start by logging equipment usage
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Log Usage Modal -->
        <Modal :show="showLogDialog" @close="showLogDialog = false">
            <form
                @submit.prevent="handleLogUsage"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold dark:text-white">
                            Log Equipment Usage
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Record equipment usage and condition changes
                        </p>
                    </div>
                    <button
                        @click="showLogDialog = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="itemId" value="Equipment *" />
                        <select
                            id="itemId"
                            v-model="usageForm.itemId"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        >
                            <option value="">Select equipment</option>
                            <option
                                v-for="item in availableItems"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }} ({{ item.quantity }} available)
                            </option>
                        </select>
                        <InputError :message="usageForm.errors.itemId" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel
                                for="technicianId"
                                value="Technician *"
                            />
                            <select
                                id="technicianId"
                                v-model="usageForm.technicianId"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                required
                            >
                                <option value="">Select technician</option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }} ({{ user.username }})
                                </option>
                            </select>
                            <InputError
                                :message="usageForm.errors.technicianId"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="workOrderId"
                                value="Work Order ID"
                            />
                            <TextInput
                                id="workOrderId"
                                v-model="usageForm.workOrderId"
                                type="number"
                                class="mt-1 w-full"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel
                                for="conditionBefore"
                                value="Condition Before"
                            />
                            <select
                                id="conditionBefore"
                                v-model="usageForm.conditionBefore"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel
                                for="conditionAfter"
                                value="Condition After"
                            />
                            <select
                                id="conditionAfter"
                                v-model="usageForm.conditionAfter"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="usageDate" value="Usage Date" />
                        <TextInput
                            id="usageDate"
                            v-model="usageForm.usageDate"
                            type="date"
                            class="mt-1 w-full"
                            required
                        />
                        <InputError :message="usageForm.errors.usageDate" />
                    </div>

                    <div>
                        <InputLabel for="notes" value="Notes" />
                        <textarea
                            id="notes"
                            v-model="usageForm.notes"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            rows="2"
                            placeholder="Usage details, observations, etc."
                        ></textarea>
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showLogDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="usageForm.processing">
                        {{ usageForm.processing ? 'Logging...' : 'Log Usage' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
