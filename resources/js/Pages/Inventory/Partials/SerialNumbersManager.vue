<!-- resources/js/Pages/Inventory/Partials/SerialNumbersManager.vue -->
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
import { Barcode, Plus, Search, Edit, Trash2, Wifi, X } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    equipment: Object,
});

const serials = ref([]);
const items = ref([]);
const loading = ref(false);
const searchTerm = ref('');
const statusFilter = ref('all');
const showCreateDialog = ref(false);
const formLoading = ref(false);

const form = useForm({
    itemId: null,
    serial: '',
    mac: '',
    status: 'available',
});

onMounted(() => {
    fetchData();
});

const fetchData = async () => {
    loading.value = true;
    try {
        const [serialsResponse, itemsResponse] = await Promise.all([
            axios.get(route('equipment.serials.index')),
            axios.get(route('equipment.index')),
        ]);

        serials.value = serialsResponse.data;
        items.value = itemsResponse.data.equipment?.data || [];
    } catch (error) {
        console.error('Error fetching data:', error);
    } finally {
        loading.value = false;
    }
};

const handleCreateSerial = () => {
    form.post(route('equipment.serials.store'), {
        onSuccess: () => {
            showCreateDialog.value = false;
            form.reset();
            fetchData();
        },
    });
};

const handleDeleteSerial = (id) => {
    if (confirm('Are you sure you want to delete this serial number?')) {
        router.delete(route('equipment.serials.destroy', id), {
            onSuccess: () => fetchData(),
        });
    }
};

const filteredSerials = computed(() => {
    return serials.value.filter((serial) => {
        const matchesSearch =
            serial.serial
                ?.toLowerCase()
                .includes(searchTerm.value.toLowerCase()) ||
            serial.mac
                ?.toLowerCase()
                .includes(searchTerm.value.toLowerCase()) ||
            serial.item?.name
                .toLowerCase()
                .includes(searchTerm.value.toLowerCase());
        const matchesStatus =
            statusFilter.value === 'all' ||
            serial.status === statusFilter.value;
        return matchesSearch && matchesStatus;
    });
});

const getStatusVariant = (status) => {
    switch (status) {
        case 'available':
            return 'default';
        case 'in_use':
            return 'secondary';
        case 'maintenance':
            return 'default';
        case 'retired':
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
                    Serial Numbers
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Manage serial numbers and MAC addresses
                </p>
            </div>
            <PrimaryButton @click="showCreateDialog = true">
                <Plus class="mr-2 h-4 w-4" />
                Add Serial
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
                        placeholder="Search serials, MAC addresses, or items..."
                        class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-4 dark:border-gray-600 dark:bg-gray-800"
                    />
                </div>
                <select
                    v-model="statusFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800"
                >
                    <option value="all">All Status</option>
                    <option value="available">Available</option>
                    <option value="in_use">In Use</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="retired">Retired</option>
                </select>
            </div>
        </Card>

        <!-- Serials Table -->
        <Card>
            <template #header>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    Registered Serials ({{ filteredSerials.length }})
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
                                Item
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Serial Number
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                MAC Address
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left font-semibold">
                                Last Updated
                            </th>
                            <th class="px-6 py-3 text-right font-semibold">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <tr
                            v-for="serial in filteredSerials"
                            :key="serial.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium dark:text-white">
                                    {{
                                        serial.item?.name ||
                                        `Item #${serial.item_id}`
                                    }}
                                </div>
                                <div
                                    v-if="serial.item?.description"
                                    class="text-sm text-gray-500"
                                >
                                    {{ serial.item.description }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Barcode class="h-4 w-4 text-gray-400" />
                                    <span class="font-mono dark:text-white">{{
                                        serial.serial || 'N/A'
                                    }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    v-if="serial.mac"
                                    class="flex items-center gap-2"
                                >
                                    <Wifi class="h-4 w-4 text-gray-400" />
                                    <span class="font-mono dark:text-white">{{
                                        serial.mac
                                    }}</span>
                                </div>
                                <span v-else class="text-gray-400">N/A</span>
                            </td>
                            <td class="px-6 py-4">
                                <StatusBadge
                                    :variant="getStatusVariant(serial.status)"
                                >
                                    {{
                                        serial.status
                                            ?.replace('_', ' ')
                                            .toUpperCase()
                                    }}
                                </StatusBadge>
                            </td>
                            <td class="px-6 py-4">
                                {{
                                    serial.updated_at
                                        ? new Date(
                                              serial.updated_at,
                                          ).toLocaleDateString()
                                        : 'N/A'
                                }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        class="rounded p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                    >
                                        <Edit class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="handleDeleteSerial(serial.id)"
                                        class="rounded p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredSerials.length">
                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-gray-500"
                            >
                                <Barcode
                                    class="mx-auto mb-4 h-12 w-12 text-gray-300"
                                />
                                <p>No serial numbers found</p>
                                <p class="mt-1 text-sm">
                                    Get started by adding your first serial
                                    number
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Create Serial Modal -->
        <Modal :show="showCreateDialog" @close="showCreateDialog = false">
            <form
                @submit.prevent="handleCreateSerial"
                class="bg-white p-6 dark:bg-gray-800"
            >
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-bold dark:text-white">
                        Add Serial Number
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
                                {{ item.name }}
                                {{
                                    item.track_serials
                                        ? '(Serial Tracking)'
                                        : ''
                                }}
                            </option>
                        </select>
                        <InputError :message="form.errors.itemId" />
                    </div>

                    <div>
                        <InputLabel for="serial" value="Serial Number" />
                        <TextInput
                            id="serial"
                            v-model="form.serial"
                            class="mt-1 w-full"
                        />
                        <InputError :message="form.errors.serial" />
                    </div>

                    <div>
                        <InputLabel for="mac" value="MAC Address" />
                        <TextInput
                            id="mac"
                            v-model="form.mac"
                            class="mt-1 w-full"
                            placeholder="00:1A:2B:3C:4D:5E"
                        />
                        <InputError :message="form.errors.mac" />
                    </div>

                    <div>
                        <InputLabel for="status" value="Status" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="available">Available</option>
                            <option value="in_use">In Use</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>
                </div>

                <div
                    class="mt-8 flex justify-end gap-3 border-t pt-5 dark:border-gray-700"
                >
                    <SecondaryButton @click="showCreateDialog = false"
                        >Cancel</SecondaryButton
                    >
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Adding...' : 'Add Serial' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
