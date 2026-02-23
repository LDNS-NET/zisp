<!-- resources/js/Pages/Inventory/Partials/InventorySettings.vue -->
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
import {
    Settings,
    MapPin,
    Plus,
    Edit,
    Trash2,
    Save,
    Download,
    Upload,
    X,
} from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    locations: Array,
});

const locations = ref(props.locations || []);
const loading = ref(false);
const showLocationDialog = ref(false);

const locationForm = useForm({
    name: '',
    address: '',
});

const systemSettings = ref({
    lowStockThreshold: 5,
    autoReorder: true,
    depreciationRate: 15,
    maintenanceReminderDays: 30,
    enableBarcodeScanning: false,
});

onMounted(() => {
    fetchLocations();
});

const fetchLocations = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('equipment.locations.index'));
        locations.value = response.data;
    } catch (error) {
        console.error('Error fetching locations:', error);
    } finally {
        loading.value = false;
    }
};

const handleCreateLocation = () => {
    locationForm.post(route('equipment.locations.store'), {
        onSuccess: () => {
            showLocationDialog.value = false;
            locationForm.reset();
            fetchLocations();
        },
    });
};

const handleDeleteLocation = (id) => {
    if (confirm('Are you sure you want to delete this location?')) {
        axios
            .delete(route('equipment.locations.destroy', id))
            .then(() => fetchLocations())
            .catch((error) => console.error('Error deleting location:', error));
    }
};

const handleSaveSettings = async () => {
    try {
        await axios.post(
            route('equipment.settings.update'),
            systemSettings.value,
        );
        alert('Settings saved successfully!');
    } catch (error) {
        console.error('Error saving settings:', error);
        alert('Failed to save settings');
    }
};

const exportInventoryData = async () => {
    try {
        const response = await axios.get(route('equipment.export'));
        const data = response.data;

        const blob = new Blob([JSON.stringify(data, null, 2)], {
            type: 'application/json',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `inventory-export-${new Date().toISOString().split('T')[0]}.json`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Error exporting data:', error);
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
                    Inventory Settings
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Configure system preferences and locations
                </p>
            </div>
            <div class="flex gap-2">
                <SecondaryButton @click="exportInventoryData">
                    <Download class="mr-2 h-4 w-4" />
                    Export Data
                </SecondaryButton>
                <PrimaryButton @click="handleSaveSettings">
                    <Save class="mr-2 h-4 w-4" />
                    Save Settings
                </PrimaryButton>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- System Settings -->
            <Card>
                <template #header>
                    <div class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            System Settings
                        </h3>
                    </div>
                    <p class="text-sm text-gray-500">
                        Configure inventory management preferences
                    </p>
                </template>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel
                                for="lowStockThreshold"
                                value="Low Stock Threshold"
                            />
                            <TextInput
                                id="lowStockThreshold"
                                v-model="systemSettings.lowStockThreshold"
                                type="number"
                                class="mt-1 w-full"
                            />
                        </div>
                        <div>
                            <InputLabel
                                for="depreciationRate"
                                value="Depreciation Rate (%)"
                            />
                            <TextInput
                                id="depreciationRate"
                                v-model="systemSettings.depreciationRate"
                                type="number"
                                step="0.1"
                                class="mt-1 w-full"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel
                            for="maintenanceReminderDays"
                            value="Maintenance Reminder (Days)"
                        />
                        <TextInput
                            id="maintenanceReminderDays"
                            v-model="systemSettings.maintenanceReminderDays"
                            type="number"
                            class="mt-1 w-full"
                        />
                    </div>

                    <div class="space-y-3">
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="systemSettings.autoReorder"
                                class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            />
                            <span class="text-sm dark:text-white"
                                >Enable Auto Reorder</span
                            >
                        </label>

                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="systemSettings.enableBarcodeScanning"
                                class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            />
                            <span class="text-sm dark:text-white"
                                >Enable Barcode Scanning</span
                            >
                        </label>
                    </div>
                </div>
            </Card>

            <!-- Stock Locations -->
            <Card>
                <template #header>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <MapPin class="h-5 w-5" />
                            <h3
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Stock Locations
                            </h3>
                        </div>
                        <PrimaryButton
                            @click="showLocationDialog = true"
                            size="sm"
                        >
                            <Plus class="mr-1 h-4 w-4" />
                            Add Location
                        </PrimaryButton>
                    </div>
                    <p class="text-sm text-gray-500">
                        Manage storage locations for inventory items
                    </p>
                </template>

                <div v-if="loading" class="flex justify-center py-4">
                    <div
                        class="h-6 w-6 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
                    ></div>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="location in locations"
                        :key="location.id"
                        class="flex items-center justify-between rounded-lg border p-3 dark:border-gray-700"
                    >
                        <div>
                            <div class="font-medium dark:text-white">
                                {{ location.name }}
                            </div>
                            <div
                                v-if="location.address"
                                class="text-sm text-gray-500"
                            >
                                {{ location.address }}
                            </div>
                            <div class="mt-1 text-xs text-gray-400">
                                Created:
                                {{
                                    new Date(
                                        location.created_at,
                                    ).toLocaleDateString()
                                }}
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                class="rounded p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                            >
                                <Edit class="h-4 w-4" />
                            </button>
                            <button
                                @click="handleDeleteLocation(location.id)"
                                class="rounded p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="!locations.length"
                        class="py-8 text-center text-gray-500"
                    >
                        <MapPin class="mx-auto mb-4 h-12 w-12 text-gray-300" />
                        <p>No locations configured</p>
                        <p class="mt-1 text-sm">
                            Add your first storage location
                        </p>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Maintenance & Reports -->
        <Card>
            <template #header>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    Maintenance & Reports
                </h3>
                <p class="text-sm text-gray-500">
                    System maintenance tools and reporting options
                </p>
            </template>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <button
                    class="flex h-20 flex-col items-center justify-center gap-2 rounded-lg border transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                >
                    <Download class="h-5 w-5 text-blue-600" />
                    <span class="text-sm font-medium dark:text-white"
                        >Stock Report</span
                    >
                </button>
                <button
                    class="flex h-20 flex-col items-center justify-center gap-2 rounded-lg border transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                >
                    <Upload class="h-5 w-5 text-green-600" />
                    <span class="text-sm font-medium dark:text-white"
                        >Import Data</span
                    >
                </button>
                <button
                    class="flex h-20 flex-col items-center justify-center gap-2 rounded-lg border transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                >
                    <Settings class="h-5 w-5 text-orange-600" />
                    <span class="text-sm font-medium dark:text-white"
                        >System Check</span
                    >
                </button>
            </div>
        </Card>

        <!-- Add Location Modal -->
        <Modal :show="showLocationDialog" @close="showLocationDialog = false">
            <form
                @submit.prevent="handleCreateLocation"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-bold dark:text-white">
                        Add Stock Location
                    </h2>
                    <button
                        @click="showLocationDialog = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="name" value="Location Name *" />
                        <TextInput
                            id="name"
                            v-model="locationForm.name"
                            class="mt-1 w-full"
                            required
                        />
                        <InputError :message="locationForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="address" value="Address" />
                        <TextInput
                            id="address"
                            v-model="locationForm.address"
                            class="mt-1 w-full"
                        />
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showLocationDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="locationForm.processing">
                        {{
                            locationForm.processing
                                ? 'Adding...'
                                : 'Add Location'
                        }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
