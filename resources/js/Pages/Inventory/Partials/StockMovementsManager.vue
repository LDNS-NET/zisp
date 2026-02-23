<!-- resources/js/Pages/Inventory/Partials/StockMovementsManager.vue -->
<script setup>
import { ref, computed, onMounted } from 'vue';
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
    Move3d,
    Plus,
    Search,
    Calendar,
    ArrowUp,
    ArrowDown,
    RefreshCw,
    X,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    equipment: Object,
    locations: Array,
});

const movements = ref([]);
const items = ref([]);
const locations = ref(props.locations || []);
const loading = ref(false);
const searchTerm = ref('');
const directionFilter = ref('all');
const showCreateDialog = ref(false);

const form = useForm({
    itemId: null,
    locationId: null,
    quantity: 0,
    direction: 'IN',
    reference: '',
});

onMounted(() => {
    fetchData();
});

const fetchData = async () => {
    loading.value = true;
    try {
        const [movementsResponse, itemsResponse] = await Promise.all([
            axios.get(route('equipment.movements.index')),
            axios.get(route('equipment.index')),
        ]);

        movements.value = movementsResponse.data;
        items.value = itemsResponse.data.equipment?.data || [];
    } catch (error) {
        console.error('Error fetching data:', error);
    } finally {
        loading.value = false;
    }
};

const handleCreateMovement = () => {
    form.post(route('equipment.movements.store'), {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
            fetchData();
        },
    });
};

const filteredMovements = computed(() => {
    return movements.value.filter((movement) => {
        const matchesSearch =
            movement.item?.name
                .toLowerCase()
                .includes(searchTerm.value.toLowerCase()) ||
            movement.location?.name
                .toLowerCase()
                .includes(searchTerm.value.toLowerCase()) ||
            movement.reference
                ?.toLowerCase()
                .includes(searchTerm.value.toLowerCase());
        const matchesDirection =
            directionFilter.value === 'all' ||
            movement.direction === directionFilter.value;
        return matchesSearch && matchesDirection;
    });
});

const getDirectionIcon = (direction) => {
    switch (direction) {
        case 'IN':
            return ArrowDown;
        case 'OUT':
            return ArrowUp;
        case 'TRANSFER':
            return RefreshCw;
        default:
            return Move3d;
    }
};

const getDirectionColor = (direction) => {
    switch (direction) {
        case 'IN':
            return 'text-green-600';
        case 'OUT':
            return 'text-red-600';
        case 'TRANSFER':
            return 'text-blue-600';
        default:
            return 'text-gray-600';
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
                    Stock Movements
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Track all inventory movements and transfers
                </p>
            </div>
            <PrimaryButton @click="showCreateDialog = true">
                <Plus class="mr-2 h-4 w-4" />
                Record Movement
            </PrimaryButton>
        </div>

        <!-- Filters -->
        <Card>
            <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                <div class="relative">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        v-model="searchTerm"
                        type="text"
                        placeholder="Search items, locations, or references..."
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 dark:border-gray-600 dark:bg-gray-800"
                    />
                </div>
                <select
                    v-model="directionFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800"
                >
                    <option value="all">All Directions</option>
                    <option value="IN">Stock In</option>
                    <option value="OUT">Stock Out</option>
                    <option value="TRANSFER">Transfer</option>
                </select>
            </div>
        </Card>

        <!-- Movements Table -->
        <Card>
            <template #header>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    Movement History ({{ filteredMovements.length }})
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
                                Date
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Item
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Location
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Quantity
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Direction
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Reference
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <tr
                            v-for="movement in filteredMovements"
                            :key="movement.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <span class="dark:text-white">{{
                                        new Date(
                                            movement.created_at,
                                        ).toLocaleDateString()
                                    }}</span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{
                                        new Date(
                                            movement.created_at,
                                        ).toLocaleTimeString()
                                    }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{ movement.item?.name }}
                                </div>
                                <div
                                    v-if="movement.item?.description"
                                    class="text-sm text-gray-500"
                                >
                                    {{ movement.item.description }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{ movement.location?.name }}
                                </div>
                                <div
                                    v-if="movement.location?.address"
                                    class="text-sm text-gray-500"
                                >
                                    {{ movement.location.address }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    :class="[
                                        'font-bold',
                                        getDirectionColor(movement.direction),
                                    ]"
                                >
                                    {{
                                        movement.direction === 'IN'
                                            ? '+'
                                            : movement.direction === 'OUT'
                                              ? '-'
                                              : '→'
                                    }}
                                    {{ movement.quantity }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge
                                    variant="outline"
                                    class="flex items-center gap-1"
                                >
                                    <component
                                        :is="
                                            getDirectionIcon(movement.direction)
                                        "
                                        :class="
                                            getDirectionColor(
                                                movement.direction,
                                            )
                                        "
                                        class="h-4 w-4"
                                    />
                                    {{ movement.direction }}
                                </StatusBadge>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500">
                                    {{ movement.reference || 'N/A' }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredMovements.length">
                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                <Move3d
                                    class="mx-auto mb-4 h-12 w-12 text-gray-300"
                                />
                                <p>No stock movements found</p>
                                <p class="mt-1 text-sm">
                                    Get started by recording your first stock
                                    movement
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Create Movement Modal -->
        <Modal :show="showCreateDialog" @close="showCreateDialog = false">
            <form
                @submit.prevent="handleCreateMovement"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-bold dark:text-white">
                        Record Stock Movement
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
                        <InputLabel for="itemId" value="Item *" />
                        <select
                            id="itemId"
                            v-model="form.itemId"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        >
                            <option value="">Select an item</option>
                            <option
                                v-for="item in items"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }} (Stock: {{ item.quantity }})
                            </option>
                        </select>
                        <InputError :message="form.errors.itemId" />
                    </div>

                    <div>
                        <InputLabel for="locationId" value="Location *" />
                        <select
                            id="locationId"
                            v-model="form.locationId"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        >
                            <option value="">Select a location</option>
                            <option
                                v-for="location in locations"
                                :key="location.id"
                                :value="location.id"
                            >
                                {{ location.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.locationId" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="quantity" value="Quantity *" />
                            <TextInput
                                id="quantity"
                                v-model="form.quantity"
                                type="number"
                                class="mt-1 w-full"
                                required
                            />
                            <InputError :message="form.errors.quantity" />
                        </div>
                        <div>
                            <InputLabel for="direction" value="Direction *" />
                            <select
                                id="direction"
                                v-model="form.direction"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                required
                            >
                                <option value="IN">Stock In</option>
                                <option value="OUT">Stock Out</option>
                                <option value="TRANSFER">Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="reference" value="Reference" />
                        <TextInput
                            id="reference"
                            v-model="form.reference"
                            class="mt-1 w-full"
                            placeholder="Purchase order, work order, etc."
                        />
                        <InputError :message="form.errors.reference" />
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showCreateDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="form.processing">
                        {{
                            form.processing ? 'Recording...' : 'Record Movement'
                        }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
