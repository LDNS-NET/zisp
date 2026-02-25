<!-- resources/js/Pages/Inventory/Partials/CableManagement.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Card from '@/Components/Card.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import {
    Cable,
    Plus,
    Search,
    Scissors,
    Ruler,
    Edit,
    User,
    X,
    Save,
    AlertTriangle,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    equipment: Object,
    users: Array,
});

const cables = ref([]);
const cableUsage = ref([]);
const loading = ref(false);
const searchTerm = ref('');
const showCreateDialog = ref(false);
const showCutDialog = ref(false);
const selectedCable = ref(null);
const formLoading = ref(false);

// New cable form
const newCableForm = useForm({
    name: '',
    description: '',
    cableType: '',
    length: 0,
    minStock: 10,
    unit: 'meters',
});

// Cut cable form
const cutForm = useForm({
    lengthUsed: 0,
    workOrderId: null,
    notes: '',
    technicianId: null,
});

onMounted(() => {
    fetchCables();
    fetchCableUsage();
});

const fetchCables = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('equipment.cables.index'));
        cables.value = response.data;
    } catch (error) {
        console.error('Error fetching cables:', error);
    } finally {
        loading.value = false;
    }
};

const fetchCableUsage = async () => {
    try {
        const response = await axios.get(route('equipment.cables.usage'));
        cableUsage.value = response.data.slice(0, 10);
    } catch (error) {
        console.error('Error fetching cable usage:', error);
    }
};

const handleCreateCable = () => {
    newCableForm.post(route('equipment.cables.store'), {
        onSuccess: () => {
            showCreateDialog.value = false;
            newCableForm.reset();
            fetchCables();
        },
    });
};

const handleCutCable = () => {
    if (!selectedCable.value) return;

    cutForm.post(route('equipment.cables.cut', selectedCable.value.id), {
        onSuccess: () => {
            showCutDialog.value = false;
            cutForm.reset();
            fetchCables();
            fetchCableUsage();
        },
    });
};

const openCutDialog = (cable) => {
    selectedCable.value = cable;
    cutForm.lengthUsed = 0;
    cutForm.workOrderId = null;
    cutForm.notes = '';
    cutForm.technicianId = null;
    showCutDialog.value = true;
};

const filteredCables = computed(() => {
    return cables.value.filter(
        (cable) =>
            cable.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
            cable.description
                ?.toLowerCase()
                .includes(searchTerm.value.toLowerCase()) ||
            cable.cableType
                ?.toLowerCase()
                .includes(searchTerm.value.toLowerCase()),
    );
});

const getCableStatus = (cable) => {
    const currentLength = cable.cable_length || cable.length || 0;
    const minLength = cable.min_stock || 10;

    if (currentLength === 0) return 'OUT_OF_STOCK';
    if (currentLength <= minLength) return 'LOW_STOCK';
    return 'IN_STOCK';
};

const getStatusVariant = (status) => {
    switch (status) {
        case 'IN_STOCK':
            return 'default';
        case 'LOW_STOCK':
            return 'secondary';
        case 'OUT_OF_STOCK':
            return 'destructive';
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
                    Cable Management
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Track cable inventory, usage, and length-based stock
                </p>
            </div>
            <PrimaryButton @click="showCreateDialog = true">
                <Plus class="mr-2 h-4 w-4" />
                Add Cable Type
            </PrimaryButton>
        </div>

        <!-- Filters -->
        <Card>
            <div class="p-4">
                <div class="relative">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        v-model="searchTerm"
                        type="text"
                        placeholder="Search cables by name, type, or description..."
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 dark:border-gray-600 dark:bg-gray-800"
                    />
                </div>
            </div>
        </Card>

        <!-- Cables Table -->
        <Card>
            <template #header>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    Cable Inventory ({{ filteredCables.length }})
                </h3>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50"
                        >
                            <th class="px-6 py-3 text-left font-semibold">
                                Cable Details
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Length
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Price/m
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Status
                            </th>
                            <th class="px-6 py-3 text-right font-semibold">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <tr
                            v-for="cable in filteredCables"
                            :key="cable.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{ cable.name }}
                                </div>
                                <div
                                    v-if="cable.description"
                                    class="text-sm text-gray-500"
                                >
                                    {{ cable.description }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge
                                    variant="outline"
                                    class="flex items-center gap-1"
                                >
                                    <Cable class="h-3 w-3" />
                                    {{ cable.cable_type || cable.type }}
                                </StatusBadge>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Ruler class="h-4 w-4 text-gray-400" />
                                    <span class="dark:text-white"
                                        >{{
                                            cable.cable_length ||
                                            cable.length ||
                                            0
                                        }}m</span
                                    >
                                </div>
                                <div class="text-xs text-gray-500">
                                    Min: {{ cable.min_stock || 10 }}m
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="dark:text-white"
                                    >KES
                                    {{
                                        (
                                            cable.purchase_price || 0
                                        ).toLocaleString()
                                    }}</span
                                >
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge
                                    :variant="
                                        getStatusVariant(getCableStatus(cable))
                                    "
                                >
                                    {{
                                        getCableStatus(cable).replace('_', ' ')
                                    }}
                                </StatusBadge>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="openCutDialog(cable)"
                                        :disabled="
                                            getCableStatus(cable) ===
                                            'OUT_OF_STOCK'
                                        "
                                        class="rounded p-1.5 text-amber-600 hover:bg-amber-50 disabled:opacity-50 dark:hover:bg-amber-900/20"
                                        title="Cut Cable"
                                    >
                                        <Scissors class="h-4 w-4" />
                                    </button>
                                    <button
                                        class="rounded p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                    >
                                        <Edit class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredCables.length">
                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                <Cable
                                    class="mx-auto mb-4 h-12 w-12 text-gray-300"
                                />
                                <p>No cables found</p>
                                <p class="mt-1 text-sm">
                                    Get started by adding your first cable type
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Recent Cable Usage -->
        <Card>
            <template #header>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    Recent Cable Usage
                </h3>
                <p class="text-sm text-gray-500">
                    Track cable cuts and usage history
                </p>
            </template>

            <div class="space-y-3">
                <div
                    v-for="usage in cableUsage"
                    :key="usage.id"
                    class="flex items-center justify-between rounded-lg border p-3 dark:border-gray-700"
                >
                    <div class="flex items-center gap-3">
                        <Scissors class="h-4 w-4 text-gray-400" />
                        <div>
                            <div class="font-medium dark:text-white">
                                {{ usage.cable_item?.name }}
                            </div>
                            <div class="text-sm text-gray-500">
                                Used {{ usage.length_used }}m • Remaining
                                {{ usage.remaining_length }}m
                            </div>
                            <div
                                v-if="usage.notes"
                                class="mt-1 text-xs text-gray-400"
                            >
                                {{ usage.notes }}
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div
                            class="flex items-center gap-1 text-sm font-medium dark:text-white"
                        >
                            <User class="h-3 w-3" />
                            {{ usage.technician?.name || 'Technician' }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{
                                new Date(usage.created_at).toLocaleDateString()
                            }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="!cableUsage.length"
                    class="py-4 text-center text-gray-500"
                >
                    <Scissors class="mx-auto mb-2 h-8 w-8 text-gray-300" />
                    <p>No cable usage recorded</p>
                    <p class="text-sm">Cable cuts will appear here</p>
                </div>
            </div>
        </Card>

        <!-- Create Cable Modal -->
        <Modal :show="showCreateDialog" @close="showCreateDialog = false">
            <form
                @submit.prevent="handleCreateCable"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-bold dark:text-white">
                        Add New Cable Type
                    </h2>
                    <button
                        @click="showCreateDialog = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="name" value="Cable Name *" />
                        <TextInput
                            id="name"
                            v-model="newCableForm.name"
                            class="mt-1 w-full"
                            required
                        />
                        <InputError :message="newCableForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="newCableForm.description"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            rows="2"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="cableType" value="Cable Type *" />
                            <TextInput
                                id="cableType"
                                v-model="newCableForm.cableType"
                                class="mt-1 w-full"
                                required
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="length"
                                value="Length (meters) *"
                            />
                            <TextInput
                                id="length"
                                v-model="newCableForm.length"
                                type="number"
                                class="mt-1 w-full"
                                required
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel
                                for="minStock"
                                value="Min Stock (meters)"
                            />
                            <TextInput
                                id="minStock"
                                v-model="newCableForm.minStock"
                                type="number"
                                class="mt-1 w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="unit" value="Unit" />
                            <select
                                id="unit"
                                v-model="newCableForm.unit"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="meters">Meters (m)</option>
                                <option value="feet">Feet (ft)</option>
                                <option value="rolls">Rolls</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showCreateDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="newCableForm.processing">
                        {{
                            newCableForm.processing ? 'Adding...' : 'Add Cable'
                        }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Cut Cable Modal -->
        <Modal :show="showCutDialog" @close="showCutDialog = false">
            <form
                @submit.prevent="handleCutCable"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold dark:text-white">
                            Cut Cable
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Record cable usage from {{ selectedCable?.name }}
                        </p>
                    </div>
                    <button
                        @click="showCutDialog = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel
                            for="lengthUsed"
                            value="Length Used (meters) *"
                        />
                        <TextInput
                            id="lengthUsed"
                            v-model="cutForm.lengthUsed"
                            type="number"
                            class="mt-1 w-full"
                            :max="
                                selectedCable?.cable_length ||
                                selectedCable?.length
                            "
                            min="0.1"
                            step="0.1"
                            required
                        />
                        <div class="mt-1 text-xs text-gray-500">
                            Available:
                            {{
                                selectedCable?.cable_length ||
                                selectedCable?.length ||
                                0
                            }}m
                        </div>
                        <InputError :message="cutForm.errors.lengthUsed" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel
                                for="technicianId"
                                value="Technician *"
                            />
                            <select
                                id="technicianId"
                                v-model="cutForm.technicianId"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                required
                            >
                                <option value="">Select Technician</option>
                                <option
                                    v-for="user in users"
                                    :key="user.id"
                                    :value="user.id"
                                >
                                    {{ user.name }} ({{ user.username }})
                                </option>
                            </select>
                            <InputError
                                :message="cutForm.errors.technicianId"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="workOrderId"
                                value="Work Order ID"
                            />
                            <TextInput
                                id="workOrderId"
                                v-model="cutForm.workOrderId"
                                type="number"
                                class="mt-1 w-full"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="notes" value="Notes" />
                        <textarea
                            id="notes"
                            v-model="cutForm.notes"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            rows="2"
                            placeholder="Installation details or location"
                        ></textarea>
                        <InputError :message="cutForm.errors.notes" />
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showCutDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="cutForm.processing">
                        {{ cutForm.processing ? 'Cutting...' : 'Cut Cable' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
