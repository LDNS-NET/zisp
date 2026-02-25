<!-- resources/js/Pages/Inventory/Partials/InventoryItemsManager.vue -->
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
    Package,
    Plus,
    Search,
    Edit,
    Trash2,
    DollarSign,
    TrendingUp,
    Wrench,
    Calendar,
    AlertTriangle,
    Save,
    X,
    TrendingDown,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    equipment: Object,
    onEditItem: Function,
});

const emit = defineEmits(['edit-item']);

const items = ref([]);
const loading = ref(false);
const searchTerm = ref('');
const statusFilter = ref('all');
const equipmentTypeFilter = ref('all');
const showCreateDialog = ref(false);
const showMaintenanceDialog = ref(false);
const showPriceUpdateDialog = ref(false);
const selectedItem = ref(null);
const formLoading = ref(false);

// Form for creating new item
const form = useForm({
    name: '',
    description: '',
    brand: '',
    model: '',
    type: '',
    trackSerials: false,
    trackLength: false,
    quantity: 0,
    minStock: 5,
    equipmentType: 'PROVISIONAL',
    purchasePrice: 0,
    depreciationRate: 15,
    maintenanceInterval: 90,
    unit: 'pcs',
    status: 'in_stock',
    condition: 'new',
});

// Price update form
const priceUpdateForm = useForm({
    newPrice: 0,
    effectiveDate: new Date().toISOString().split('T')[0],
    reason: '',
    approvedBy: '',
});

// Maintenance form
const maintenanceForm = useForm({
    maintenanceDate: new Date().toISOString().split('T')[0],
    notes: '',
    nextMaintenanceInDays: 90,
});

onMounted(() => {
    fetchItems();
});

const fetchItems = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('equipment.index'));
        items.value = response.data.equipment?.data || [];
    } catch (error) {
        console.error('Error fetching items:', error);
    } finally {
        loading.value = false;
    }
};

const handleCreateItem = () => {
    form.post(route('equipment.store'), {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
            fetchItems();
        },
    });
};

const handlePriceUpdate = () => {
    if (!selectedItem.value) return;

    priceUpdateForm.post(
        route('equipment.update-price', selectedItem.value.id),
        {
            onSuccess: () => {
                showPriceUpdateDialog.value = false;
                priceUpdateForm.reset();
                fetchItems();
            },
        },
    );
};

const handleScheduleMaintenance = () => {
    if (!selectedItem.value) return;

    maintenanceForm.post(
        route('equipment.schedule-maintenance', selectedItem.value.id),
        {
            onSuccess: () => {
                showMaintenanceDialog.value = false;
                maintenanceForm.reset();
                fetchItems();
            },
        },
    );
};

const handleDeleteItem = (id) => {
    if (confirm('Are you sure you want to delete this item?')) {
        router.delete(route('equipment.destroy', id), {
            onSuccess: () => fetchItems(),
        });
    }
};

const openPriceUpdateDialog = (item) => {
    selectedItem.value = item;
    priceUpdateForm.newPrice = item.purchase_price || 0;
    priceUpdateForm.effectiveDate = new Date().toISOString().split('T')[0];
    priceUpdateForm.reason = '';
    priceUpdateForm.approvedBy = '';
    showPriceUpdateDialog.value = true;
};

const openMaintenanceDialog = (item) => {
    selectedItem.value = item;
    maintenanceForm.maintenanceDate = new Date().toISOString().split('T')[0];
    maintenanceForm.notes = '';
    maintenanceForm.nextMaintenanceInDays = 90;
    showMaintenanceDialog.value = true;
};

const filteredItems = computed(() => {
    return items.value.filter((item) => {
        const matchesSearch =
            item.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
            item.description
                ?.toLowerCase()
                .includes(searchTerm.value.toLowerCase());
        const matchesStatus =
            statusFilter.value === 'all' || item.status === statusFilter.value;
        const matchesType =
            equipmentTypeFilter.value === 'all' ||
            item.equipment_type === equipmentTypeFilter.value;
        return matchesSearch && matchesStatus && matchesType;
    });
});

const getEquipmentTypeBadge = (item) => {
    const type = item.equipment_type || 'PROVISIONAL';
    const config = {
        PERMANENT: {
            label: 'Permanent',
            variant: 'default',
            icon: TrendingDown,
        },
        PROVISIONAL: {
            label: 'Provisional',
            variant: 'outline',
            icon: Package,
        },
    };
    return config[type] || config.PROVISIONAL;
};

const getMaintenanceStatus = (item) => {
    const nextMaintenance = item.next_maintenance_date;
    if (!nextMaintenance) return null;

    const today = new Date();
    const maintenanceDate = new Date(nextMaintenance);
    const daysUntil = Math.ceil(
        (maintenanceDate - today) / (1000 * 60 * 60 * 24),
    );

    if (daysUntil < 0) {
        return {
            status: 'OVERDUE',
            color: 'text-red-600',
            bg: 'bg-red-50',
            days: Math.abs(daysUntil),
        };
    } else if (daysUntil <= 7) {
        return {
            status: 'DUE_SOON',
            color: 'text-orange-600',
            bg: 'bg-orange-50',
            days: daysUntil,
        };
    } else {
        return {
            status: 'SCHEDULED',
            color: 'text-green-600',
            bg: 'bg-green-50',
            days: daysUntil,
        };
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
                    Inventory Items
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Manage all equipment, prices, and maintenance schedules
                </p>
            </div>
            <PrimaryButton
                @click="showCreateDialog = true"
                class="bg-gradient-to-r from-blue-600 to-blue-700"
            >
                <Plus class="mr-2 h-4 w-4" />
                Add Item
            </PrimaryButton>
        </div>

        <!-- Filters -->
        <Card>
            <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-3">
                <div class="relative">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        v-model="searchTerm"
                        type="text"
                        placeholder="Search items..."
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    />
                </div>
                <select
                    v-model="statusFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="all">All Status</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                <select
                    v-model="equipmentTypeFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="all">All Types</option>
                    <option value="PERMANENT">Permanent Assets</option>
                    <option value="PROVISIONAL">Provisional Equipment</option>
                </select>
            </div>
        </Card>

        <!-- Items Table -->
        <Card>
            <template #header>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    All Items ({{ filteredItems.length }})
                </h3>
                <p class="text-sm text-gray-500">
                    Manage inventory items, update prices, and schedule
                    maintenance
                </p>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50"
                        >
                            <th class="px-6 py-3 text-left font-semibold">
                                Item
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Stock
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Price
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Maintenance
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
                            v-for="item in filteredItems"
                            :key="item.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{ item.name }}
                                </div>
                                <div
                                    v-if="item.description"
                                    class="text-sm text-gray-500"
                                >
                                    {{ item.description }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge
                                    :variant="
                                        getEquipmentTypeBadge(item).variant
                                    "
                                    class="flex items-center gap-1"
                                >
                                    <component
                                        :is="getEquipmentTypeBadge(item).icon"
                                        class="h-3 w-3"
                                    />
                                    {{ getEquipmentTypeBadge(item).label }}
                                </StatusBadge>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{ item.quantity }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Min: {{ item.min_stock || 5 }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    v-if="item.purchase_price"
                                    class="flex items-center gap-2"
                                >
                                    <span class="font-medium dark:text-white"
                                        >KES
                                        {{
                                            item.purchase_price.toLocaleString()
                                        }}</span
                                    >
                                    <button
                                        @click="openPriceUpdateDialog(item)"
                                        class="rounded p-1 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20"
                                        title="Update Price"
                                    >
                                        <TrendingUp class="h-3 w-3" />
                                    </button>
                                </div>
                                <button
                                    v-else
                                    @click="openPriceUpdateDialog(item)"
                                    class="rounded border px-2 py-1 text-xs hover:bg-gray-50 dark:hover:bg-gray-800"
                                >
                                    Set Price
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    v-if="getMaintenanceStatus(item)"
                                    :class="[
                                        'inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium',
                                        getMaintenanceStatus(item).bg,
                                        getMaintenanceStatus(item).color,
                                    ]"
                                >
                                    <AlertTriangle
                                        v-if="
                                            getMaintenanceStatus(item)
                                                .status === 'OVERDUE'
                                        "
                                        class="h-3 w-3"
                                    />
                                    <Calendar
                                        v-else-if="
                                            getMaintenanceStatus(item)
                                                .status === 'DUE_SOON'
                                        "
                                        class="h-3 w-3"
                                    />
                                    {{ getMaintenanceStatus(item).days }} days
                                </div>
                                <span v-else class="text-sm text-gray-400"
                                    >Not scheduled</span
                                >
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge :status="item.status" />
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <button
                                        v-if="
                                            item.equipment_type === 'PERMANENT'
                                        "
                                        @click="openMaintenanceDialog(item)"
                                        class="rounded p-1.5 text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20"
                                        title="Schedule Maintenance"
                                    >
                                        <Wrench class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="$emit('edit-item', item)"
                                        class="rounded p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                    >
                                        <Edit class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="handleDeleteItem(item.id)"
                                        class="rounded p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredItems.length">
                            <td
                                colspan="7"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                <Package
                                    class="mx-auto mb-4 h-12 w-12 text-gray-300"
                                />
                                <p>No items found</p>
                                <p class="mt-1 text-sm">
                                    Get started by creating your first inventory
                                    item
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Create Item Modal -->
        <Modal
            :show="showCreateDialog"
            @close="showCreateDialog = false"
            maxWidth="2xl"
        >
            <form
                @submit.prevent="handleCreateItem"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-bold dark:text-white">
                        Create New Inventory Item
                    </h2>
                    <button
                        @click="showCreateDialog = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="name" value="Item Name *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                class="mt-1 w-full"
                                required
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="type" value="Item Type *" />
                            <select
                                id="type"
                                v-model="form.type"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="">Select Type</option>
                                <option value="Router">Router</option>
                                <option value="ONU">ONU/ONT</option>
                                <option value="Antenna">Antenna/CPE</option>
                                <option value="Cable">Cable</option>
                                <option value="Switch">Switch</option>
                                <option value="Tool">Tool</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            rows="2"
                        ></textarea>
                    </div>

                    <!-- Equipment Type -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel
                                for="equipmentType"
                                value="Equipment Type *"
                            />
                            <select
                                id="equipmentType"
                                v-model="form.equipmentType"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="PROVISIONAL">
                                    Provisional (Consumable)
                                </option>
                                <option value="PERMANENT">
                                    Permanent (Asset)
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="unit" value="Unit" />
                            <select
                                id="unit"
                                v-model="form.unit"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            >
                                <option value="pcs">Pieces (Pcs)</option>
                                <option value="meters">Meters (m)</option>
                                <option value="feet">Feet (ft)</option>
                                <option value="rolls">Rolls</option>
                            </select>
                        </div>
                    </div>

                    <!-- Stock Info -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <InputLabel
                                for="quantity"
                                value="Initial Stock *"
                            />
                            <TextInput
                                id="quantity"
                                v-model="form.quantity"
                                type="number"
                                class="mt-1 w-full"
                                required
                            />
                        </div>
                        <div>
                            <InputLabel for="minStock" value="Min Stock" />
                            <TextInput
                                id="minStock"
                                v-model="form.minStock"
                                type="number"
                                class="mt-1 w-full"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="purchasePrice"
                                value="Purchase Price"
                            />
                            <TextInput
                                id="purchasePrice"
                                v-model="form.purchasePrice"
                                type="number"
                                class="mt-1 w-full"
                            />
                        </div>
                    </div>

                    <!-- Tracking Options -->
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="form.trackSerials"
                                class="rounded dark:bg-gray-900"
                            />
                            <span class="text-sm dark:text-white"
                                >Track Serial Numbers</span
                            >
                        </label>
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="form.trackLength"
                                class="rounded dark:bg-gray-900"
                            />
                            <span class="text-sm dark:text-white"
                                >Track Length (Cables)</span
                            >
                        </label>
                    </div>

                    <!-- Permanent Equipment Fields -->
                    <div
                        v-if="form.equipmentType === 'PERMANENT'"
                        class="border-t pt-4 dark:border-gray-700"
                    >
                        <h4 class="mb-3 font-medium dark:text-white">
                            Asset Settings
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel
                                    for="depreciationRate"
                                    value="Depreciation Rate (%/year)"
                                />
                                <TextInput
                                    id="depreciationRate"
                                    v-model="form.depreciationRate"
                                    type="number"
                                    class="mt-1 w-full"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    for="maintenanceInterval"
                                    value="Maintenance Interval (days)"
                                />
                                <TextInput
                                    id="maintenanceInterval"
                                    v-model="form.maintenanceInterval"
                                    type="number"
                                    class="mt-1 w-full"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showCreateDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Item' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Price Update Modal -->
        <Modal
            :show="showPriceUpdateDialog"
            @close="showPriceUpdateDialog = false"
        >
            <form
                @submit.prevent="handlePriceUpdate"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2
                            class="flex items-center gap-2 text-xl font-bold dark:text-white"
                        >
                            <DollarSign class="h-5 w-5 text-green-600" />
                            Update Item Price
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Update purchase price for {{ selectedItem?.name }}
                        </p>
                    </div>
                    <button
                        @click="showPriceUpdateDialog = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Current Price" />
                            <div
                                class="mt-1 rounded border bg-gray-50 p-2 text-sm dark:bg-gray-900"
                            >
                                KES
                                {{
                                    (
                                        selectedItem?.purchase_price || 0
                                    ).toLocaleString()
                                }}
                            </div>
                        </div>
                        <div>
                            <InputLabel
                                for="newPrice"
                                value="New Price (KES) *"
                            />
                            <TextInput
                                id="newPrice"
                                v-model="priceUpdateForm.newPrice"
                                type="number"
                                class="mt-1 w-full"
                                required
                            />
                            <InputError
                                :message="priceUpdateForm.errors.newPrice"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel
                            for="effectiveDate"
                            value="Effective Date *"
                        />
                        <TextInput
                            id="effectiveDate"
                            v-model="priceUpdateForm.effectiveDate"
                            type="date"
                            class="mt-1 w-full"
                            required
                        />
                        <InputError
                            :message="priceUpdateForm.errors.effectiveDate"
                        />
                    </div>

                    <div>
                        <InputLabel for="reason" value="Reason for Update *" />
                        <select
                            id="reason"
                            v-model="priceUpdateForm.reason"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            required
                        >
                            <option value="">Select reason</option>
                            <option value="MARKET_PRICE_CHANGE">
                                Market Price Change
                            </option>
                            <option value="SUPPLIER_PRICE_UPDATE">
                                Supplier Price Update
                            </option>
                            <option value="COST_ADJUSTMENT">
                                Cost Adjustment
                            </option>
                            <option value="INFLATION_ADJUSTMENT">
                                Inflation Adjustment
                            </option>
                            <option value="OTHER">Other</option>
                        </select>
                        <InputError :message="priceUpdateForm.errors.reason" />
                    </div>

                    <div>
                        <InputLabel for="approvedBy" value="Approved By *" />
                        <TextInput
                            id="approvedBy"
                            v-model="priceUpdateForm.approvedBy"
                            class="mt-1 w-full"
                            required
                        />
                        <InputError
                            :message="priceUpdateForm.errors.approvedBy"
                        />
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showPriceUpdateDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton
                        :disabled="priceUpdateForm.processing"
                        class="bg-gradient-to-r from-green-600 to-green-700"
                    >
                        <Save class="mr-2 h-4 w-4" />
                        {{
                            priceUpdateForm.processing
                                ? 'Updating...'
                                : 'Update Price'
                        }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Maintenance Modal -->
        <Modal
            :show="showMaintenanceDialog"
            @close="showMaintenanceDialog = false"
        >
            <form
                @submit.prevent="handleScheduleMaintenance"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2
                            class="flex items-center gap-2 text-xl font-bold dark:text-white"
                        >
                            <Wrench class="h-5 w-5 text-orange-600" />
                            Schedule Maintenance
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Schedule maintenance for {{ selectedItem?.name }}
                        </p>
                    </div>
                    <button
                        @click="showMaintenanceDialog = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel
                            for="maintenanceDate"
                            value="Maintenance Date *"
                        />
                        <TextInput
                            id="maintenanceDate"
                            v-model="maintenanceForm.maintenanceDate"
                            type="date"
                            class="mt-1 w-full"
                            required
                        />
                        <InputError
                            :message="maintenanceForm.errors.maintenanceDate"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="nextMaintenanceInDays"
                            value="Next Maintenance (days)"
                        />
                        <TextInput
                            id="nextMaintenanceInDays"
                            v-model="maintenanceForm.nextMaintenanceInDays"
                            type="number"
                            class="mt-1 w-full"
                        />
                        <InputError
                            :message="
                                maintenanceForm.errors.nextMaintenanceInDays
                            "
                        />
                    </div>

                    <div>
                        <InputLabel for="notes" value="Notes" />
                        <textarea
                            id="notes"
                            v-model="maintenanceForm.notes"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            rows="3"
                        ></textarea>
                        <InputError :message="maintenanceForm.errors.notes" />
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showMaintenanceDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="maintenanceForm.processing">
                        {{
                            maintenanceForm.processing
                                ? 'Scheduling...'
                                : 'Schedule Maintenance'
                        }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
