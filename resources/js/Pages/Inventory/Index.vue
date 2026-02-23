<!-- resources/js/Pages/Inventory/Index.vue -->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Import all inventory components
import InventoryDashboard from './Partials/InventoryDashboard.vue';
import InventoryItemsManager from './Partials/InventoryItemsManager.vue';
import SerialNumbersManager from './Partials/SerialNumbersManager.vue';
import StockMovementsManager from './Partials/StockMovementsManager.vue';
import EquipmentRequestsManager from './Partials/EquipmentRequestsManager.vue';
import CableManagement from './Partials/CableManagement.vue';
import EquipmentUsageTracker from './Partials/EquipmentUsageTracker.vue';
import DepreciationManager from './Partials/DepreciationManager.vue';
import InventorySettings from './Partials/InventorySettings.vue';

import {
    Package,
    Barcode,
    Move3d,
    Wrench,
    Cable,
    Users,
    TrendingDown,
    Settings,
    LayoutDashboard,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    equipment: Object,
    totalPrice: Number,
    filters: Object,
    stats: Object,
    locations: Array,
    users: Array,
});

const currentView = ref('dashboard');
const selectedItem = ref(null);
const showEditModal = ref(false);

// Edit form
const editForm = useForm({
    id: null,
    name: '',
    brand: '',
    type: '',
    serial_number: '',
    mac_address: '',
    status: '',
    condition: '',
    location: '',
    model: '',
    price: '',
    total_price: '',
    purchase_date: '',
    warranty_expiry: '',
    notes: '',
    quantity: 1,
    unit: 'pcs',
    equipment_type: 'PROVISIONAL',
    min_stock: 5,
    track_serials: false,
    track_length: false,
    purchase_price: 0,
    depreciation_rate: 15,
    maintenance_interval: 90,
    cable_type: '',
    cable_length: 0,
});

const tabs = [
    {
        id: 'dashboard',
        label: 'Dashboard',
        icon: LayoutDashboard,
        component: InventoryDashboard,
    },
    {
        id: 'items',
        label: 'Items',
        icon: Package,
        component: InventoryItemsManager,
    },
    {
        id: 'serials',
        label: 'Serials',
        icon: Barcode,
        component: SerialNumbersManager,
    },
    {
        id: 'movements',
        label: 'Movements',
        icon: Move3d,
        component: StockMovementsManager,
    },
    {
        id: 'requests',
        label: 'Requests',
        icon: Wrench,
        component: EquipmentRequestsManager,
    },
    { id: 'cables', label: 'Cables', icon: Cable, component: CableManagement },
    {
        id: 'usage',
        label: 'Usage',
        icon: Users,
        component: EquipmentUsageTracker,
    },
    {
        id: 'depreciation',
        label: 'Depreciation',
        icon: TrendingDown,
        component: DepreciationManager,
    },
    {
        id: 'settings',
        label: 'Settings',
        icon: Settings,
        component: InventorySettings,
    },
];

const currentComponent = computed(() => {
    const tab = tabs.find((t) => t.id === currentView.value);
    return tab ? tab.component : InventoryDashboard;
});

const handleEditItem = (item) => {
    // Populate the form with the selected item's data
    editForm.id = item.id;
    editForm.name = item.name || '';
    editForm.brand = item.brand || '';
    editForm.type = item.type || '';
    editForm.serial_number = item.serial_number || '';
    editForm.mac_address = item.mac_address || '';
    editForm.status = item.status || 'in_stock';
    editForm.condition = item.condition || 'new';
    editForm.location = item.location || '';
    editForm.model = item.model || '';
    editForm.price = item.price || 0;
    editForm.total_price = item.total_price || 0;
    editForm.purchase_date = item.purchase_date || '';
    editForm.warranty_expiry = item.warranty_expiry || '';
    editForm.notes = item.notes || '';
    editForm.quantity = item.quantity || 1;
    editForm.unit = item.unit || 'pcs';
    editForm.equipment_type = item.equipment_type || 'PROVISIONAL';
    editForm.min_stock = item.min_stock || 5;
    editForm.track_serials = item.track_serials || false;
    editForm.track_length = item.track_length || false;
    editForm.purchase_price = item.purchase_price || 0;
    editForm.depreciation_rate = item.depreciation_rate || 15;
    editForm.maintenance_interval = item.maintenance_interval || 90;
    editForm.cable_type = item.cable_type || '';
    editForm.cable_length = item.cable_length || 0;

    selectedItem.value = item;
    showEditModal.value = true;

    console.log('Editing item:', item);
};

const submitEdit = () => {
    editForm.put(route('equipment.update', editForm.id), {
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
            // Refresh the current view if needed
            if (currentView.value === 'items') {
                // You might want to emit an event to refresh the items list
                // This depends on your component communication setup
            }
        },
        onError: (errors) => {
            console.error('Error updating item:', errors);
        },
    });
};

const closeEditModal = () => {
    showEditModal.value = false;
    editForm.reset();
    editForm.clearErrors();
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Inventory Management" />

        <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div v-if="currentView !== 'dashboard'">
                        <button
                            @click="currentView = 'dashboard'"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            <LayoutDashboard class="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </button>
                    </div>
                    <div>
                        <h1
                            class="text-3xl font-bold text-gray-900 dark:text-white"
                        >
                            Inventory Management
                        </h1>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <span v-if="currentView === 'dashboard'"
                                >Overview and analytics</span
                            >
                            <span v-else-if="currentView === 'items'"
                                >Manage inventory items with depreciation
                                tracking</span
                            >
                            <span v-else-if="currentView === 'serials'"
                                >Track serial numbers and MAC addresses</span
                            >
                            <span v-else-if="currentView === 'movements'"
                                >Monitor stock movements and transfers</span
                            >
                            <span v-else-if="currentView === 'requests'"
                                >Handle technician equipment requests</span
                            >
                            <span v-else-if="currentView === 'cables'"
                                >Manage cable inventory and usage tracking</span
                            >
                            <span v-else-if="currentView === 'usage'"
                                >Track equipment usage and condition
                                changes</span
                            >
                            <span v-else-if="currentView === 'depreciation'"
                                >Asset depreciation and replacement
                                planning</span
                            >
                            <span v-else-if="currentView === 'settings'"
                                >System settings and configuration</span
                            >
                        </p>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-6 overflow-x-auto pb-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="currentView = tab.id"
                        :class="[
                            currentView === tab.id
                                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300',
                            'flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors',
                        ]"
                    >
                        <component :is="tab.icon" class="h-5 w-5" />
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Current View -->
            <div class="mt-6">
                <component
                    :is="currentComponent"
                    :equipment="equipment"
                    :total-price="totalPrice"
                    :filters="filters"
                    :stats="stats"
                    :locations="locations"
                    :users="users"
                    :selected-item="selectedItem"
                    @edit-item="handleEditItem"
                />
            </div>
        </div>

        <!-- Edit Item Modal -->
        <Modal :show="showEditModal" @close="closeEditModal" maxWidth="2xl">
            <form
                @submit.prevent="submitEdit"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-bold dark:text-white">
                        Edit Equipment
                    </h2>
                    <button
                        type="button"
                        @click="closeEditModal"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <!-- Basic Info -->
                    <div class="md:col-span-2">
                        <InputLabel
                            for="edit_name"
                            value="Equipment Name"
                            required
                        />
                        <TextInput
                            v-model="editForm.name"
                            id="edit_name"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="editForm.errors.name"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel for="edit_brand" value="Brand" />
                        <TextInput
                            v-model="editForm.brand"
                            id="edit_brand"
                            class="mt-1 block w-full"
                        />
                        <InputError
                            :message="editForm.errors.brand"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel for="edit_model" value="Model/Version" />
                        <TextInput
                            v-model="editForm.model"
                            id="edit_model"
                            class="mt-1 block w-full"
                        />
                        <InputError
                            :message="editForm.errors.model"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="edit_type"
                            value="Device Type"
                            required
                        />
                        <select
                            v-model="editForm.type"
                            id="edit_type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            required
                        >
                            <option value="">Select Type</option>
                            <option value="Router">Router</option>
                            <option value="ONU">ONU/ONT</option>
                            <option value="Antenna">Antenna/CPE</option>
                            <option value="Cable">Cable / Drop</option>
                            <option value="Switch">Switch</option>
                            <option value="Tool">Field Tool</option>
                        </select>
                        <InputError
                            :message="editForm.errors.type"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="edit_condition"
                            value="Item Condition"
                            required
                        />
                        <select
                            v-model="editForm.condition"
                            id="edit_condition"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="new">New</option>
                            <option value="used">Used</option>
                            <option value="refurbished">Refurbished</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel
                            for="edit_serial_number"
                            value="Serial Number"
                            required
                        />
                        <TextInput
                            v-model="editForm.serial_number"
                            id="edit_serial_number"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="editForm.errors.serial_number"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="edit_mac_address"
                            value="MAC Address"
                        />
                        <TextInput
                            v-model="editForm.mac_address"
                            id="edit_mac_address"
                            class="mt-1 block w-full"
                            placeholder="00:00:00:00:00:00"
                        />
                        <InputError
                            :message="editForm.errors.mac_address"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel for="edit_status" value="Status" />
                        <select
                            v-model="editForm.status"
                            id="edit_status"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="in_stock">In Stock</option>
                            <option value="assigned">Assigned</option>
                            <option value="faulty">Faulty</option>
                            <option value="retired">Retired</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>

                    <!-- Equipment Type for Permanent/Provisional -->
                    <div>
                        <InputLabel
                            for="edit_equipment_type"
                            value="Equipment Type"
                        />
                        <select
                            v-model="editForm.equipment_type"
                            id="edit_equipment_type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="PROVISIONAL">
                                Provisional (Consumable)
                            </option>
                            <option value="PERMANENT">Permanent (Asset)</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="edit_unit" value="Unit" />
                        <select
                            v-model="editForm.unit"
                            id="edit_unit"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="pcs">Pieces (Pcs)</option>
                            <option value="meters">Meters (m)</option>
                            <option value="feet">Feet (ft)</option>
                            <option value="rolls">Rolls</option>
                            <option value="boxes">Boxes</option>
                            <option value="pairs">Pairs</option>
                        </select>
                    </div>

                    <!-- Stock Info -->
                    <div>
                        <InputLabel
                            for="edit_quantity"
                            value="Quantity"
                            required
                        />
                        <TextInput
                            v-model="editForm.quantity"
                            id="edit_quantity"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="editForm.errors.quantity"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="edit_min_stock"
                            value="Min Stock Level"
                        />
                        <TextInput
                            v-model="editForm.min_stock"
                            id="edit_min_stock"
                            type="number"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <!-- Price Info -->
                    <div>
                        <InputLabel for="edit_price" value="Unit Price (KES)" />
                        <TextInput
                            v-model="editForm.price"
                            id="edit_price"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="edit_purchase_price"
                            value="Purchase Price (KES)"
                        />
                        <TextInput
                            v-model="editForm.purchase_price"
                            id="edit_purchase_price"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="edit_total_price"
                            value="Total Value (KES)"
                        />
                        <TextInput
                            v-model="editForm.total_price"
                            id="edit_total_price"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <!-- Dates -->
                    <div>
                        <InputLabel
                            for="edit_purchase_date"
                            value="Purchase Date"
                        />
                        <TextInput
                            v-model="editForm.purchase_date"
                            id="edit_purchase_date"
                            type="date"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <div>
                        <InputLabel
                            for="edit_warranty_expiry"
                            value="Warranty Expiry"
                        />
                        <TextInput
                            v-model="editForm.warranty_expiry"
                            id="edit_warranty_expiry"
                            type="date"
                            class="mt-1 block w-full"
                        />
                    </div>

                    <!-- Permanent Equipment Fields -->
                    <template v-if="editForm.equipment_type === 'PERMANENT'">
                        <div class="md:col-span-2">
                            <h4 class="mb-2 font-medium dark:text-white">
                                Asset Settings
                            </h4>
                        </div>
                        <div>
                            <InputLabel
                                for="edit_depreciation_rate"
                                value="Depreciation Rate (%/year)"
                            />
                            <TextInput
                                v-model="editForm.depreciation_rate"
                                id="edit_depreciation_rate"
                                type="number"
                                step="0.1"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="edit_maintenance_interval"
                                value="Maintenance Interval (days)"
                            />
                            <TextInput
                                v-model="editForm.maintenance_interval"
                                id="edit_maintenance_interval"
                                type="number"
                                class="mt-1 block w-full"
                            />
                        </div>
                    </template>

                    <!-- Cable Fields -->
                    <template
                        v-else-if="
                            editForm.type === 'Cable' || editForm.track_length
                        "
                    >
                        <div class="md:col-span-2">
                            <h4 class="mb-2 font-medium dark:text-white">
                                Cable Settings
                            </h4>
                        </div>
                        <div>
                            <InputLabel
                                for="edit_cable_type"
                                value="Cable Type"
                            />
                            <TextInput
                                v-model="editForm.cable_type"
                                id="edit_cable_type"
                                class="mt-1 block w-full"
                                placeholder="e.g., Fiber, Cat6, Coaxial"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="edit_cable_length"
                                value="Length (meters)"
                            />
                            <TextInput
                                v-model="editForm.cable_length"
                                id="edit_cable_length"
                                type="number"
                                step="0.1"
                                class="mt-1 block w-full"
                            />
                        </div>
                    </template>

                    <!-- Tracking Options -->
                    <div class="md:col-span-2">
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="editForm.track_serials"
                                    class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                />
                                <span class="text-sm dark:text-white"
                                    >Track Serial Numbers</span
                                >
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="editForm.track_length"
                                    class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                                />
                                <span class="text-sm dark:text-white"
                                    >Track Length (Cables)</span
                                >
                            </label>
                        </div>
                    </div>

                    <!-- Location & Notes -->
                    <div class="md:col-span-2">
                        <InputLabel
                            for="edit_location"
                            value="Location/Storage"
                        />
                        <TextInput
                            v-model="editForm.location"
                            id="edit_location"
                            class="mt-1 block w-full"
                            placeholder="Shelf, Bin, Warehouse, etc."
                        />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="edit_notes" value="Notes" />
                        <textarea
                            v-model="editForm.notes"
                            id="edit_notes"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            rows="3"
                        ></textarea>
                        <InputError
                            :message="editForm.errors.notes"
                            class="mt-1"
                        />
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="closeEditModal"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="editForm.processing">
                        {{
                            editForm.processing
                                ? 'Updating...'
                                : 'Update Equipment'
                        }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
