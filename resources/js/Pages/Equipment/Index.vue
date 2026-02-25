<script setup>
import { ref, watch, computed } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import { 
    Plus, Edit, Trash2, Search, History, 
    UserPlus, UserMinus, Monitor, 
    AlertTriangle, CheckCircle2, Package, X,
    ShoppingCart
} from 'lucide-vue-next'
import axios from 'axios'
import SecondaryButton from '@/Components/SecondaryButton.vue'

const props = defineProps({
    equipment: Object,
    totalPrice: Number,
    filters: Object,
})

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
let searchTimeout;

watch([search, statusFilter], () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('equipment.index'),
            { search: search.value, status: statusFilter.value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 500);
});

const showModal = ref(false)
const showAssignModal = ref(false)
const showHistoryModal = ref(false)
const editing = ref(null)
const selectedEquipment = ref([])
const selectAll = ref(false)
const equipmentHistory = ref([])
const loadingHistory = ref(false)
const userSearchResults = ref([])
const searchingUsers = ref(false)
const selectedEquipmentForAssign = ref(null)

const form = useForm({
    name: '',
    brand: '',
    type: '',
    serial_number: '',
    mac_address: '',
    status: 'in_stock',
    condition: 'new',
    location: '',
    model: '',
    price: '',
    total_price: '',
    purchase_date: '',
    warranty_expiry: '',
    notes: '',
    quantity: 1,
    unit: 'pcs',
})

const usageForm = useForm({
    quantity: 1,
    details: '',
})

const showUsageModal = ref(false)
const selectedEquipmentForUsage = ref(null)

const assignForm = useForm({
    user_id: '',
    notes: '',
})

function openAddModal() {
    form.reset()
    editing.value = null
    showModal.value = true
}

function openEditModal(equip) {
    form.name = equip.name
    form.brand = equip.brand
    form.type = equip.type
    form.serial_number = equip.serial_number
    form.mac_address = equip.mac_address
    form.status = equip.status
    form.condition = equip.condition
    form.location = equip.location
    form.model = equip.model
    form.price = equip.price
    form.total_price = equip.total_price
    form.purchase_date = equip.purchase_date
    form.warranty_expiry = equip.warranty_expiry
    form.notes = equip.notes
    form.quantity = equip.quantity
    form.unit = equip.unit || 'pcs'
    editing.value = equip.id
    showModal.value = true
}

function openUsageModal(equip) {
    selectedEquipmentForUsage.value = equip
    usageForm.reset()
    usageForm.quantity = 1
    showUsageModal.value = true
}

function submitUsage() {
    usageForm.post(route('equipment.log-usage', selectedEquipmentForUsage.value.id), {
        onSuccess: () => {
            showUsageModal.value = false
            selectedEquipmentForUsage.value = null
        }
    })
}

function submit() {
    if (editing.value) {
        form.put(route('equipment.update', editing.value), {
            onSuccess: () => showModal.value = false
        })
    } else {
        form.post(route('equipment.store'), {
            onSuccess: () => showModal.value = false
        })
    }
}

function openAssignModal(equip) {
    selectedEquipmentForAssign.value = equip
    assignForm.reset()
    showAssignModal.value = true
}

function submitAssign() {
    assignForm.post(route('equipment.assign', selectedEquipmentForAssign.value.id), {
        onSuccess: () => {
            showAssignModal.value = false
            selectedEquipmentForAssign.value = null
        }
    })
}

function releaseEquipment(equip) {
    if (confirm("Release this equipment back to stock?")) {
        router.post(route('equipment.release', equip.id))
    }
}

async function viewHistory(equip) {
    showHistoryModal.value = true
    loadingHistory.value = true
    try {
        const response = await axios.get(route('equipment.history', equip.id))
        equipmentHistory.value = response.data
    } catch (e) {
        console.error(e)
    } finally {
        loadingHistory.value = false
    }
}

const searchUsers = async (q) => {
    if (q.length < 2) return
    searchingUsers.value = true
    try {
        const response = await axios.get(route('equipment.users.search', { q }))
        userSearchResults.value = response.data
    } catch (e) {
        console.error(e)
    } finally {
        searchingUsers.value = false
    }
}

function remove(id) {
    if (confirm("Delete this Equipment?")) {
        router.delete(route('equipment.destroy', id))
    }
}

watch(selectAll, (val) => {
    selectedEquipment.value = val ? props.equipment.data.map(e => e.id) : []
})

const bulkDelete = () => {
    if (!selectedEquipment.value.length) return
    if (!confirm('Are you sure you want to delete selected equipment?')) return

    router.delete(route('equipment.bulk-delete'), {
        data: { ids: selectedEquipment.value },
        onSuccess: () => {
            selectedEquipment.value = []
        }
    })
}

const getStatusClass = (status) => {
    const classes = {
        'in_stock': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'assigned': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'faulty': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'retired': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
        'lost': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    }
    return classes[status] || classes['retired']
}

const formatStatus = (status) => status.replace('_', ' ').toUpperCase()
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Inventory Management" />

        <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Equipment Inventory</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Track hardware, assignments, and movement.</p>
                </div>
                
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <Link :href="route('equipment.usage.log')" class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-amber-700 active:bg-amber-800 transition-all duration-200">
                        <ShoppingCart class="h-4 w-4 mr-2" /> Log Usage
                    </Link>
                    <PrimaryButton @click="openAddModal" class="flex items-center gap-2">
                        <Plus class="h-4 w-4" /> Add Item
                    </PrimaryButton>
                </div>
            </div>

            <!-- Stats & Filters -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center gap-4">
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
                        <Package class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Asset Value</p>
                        <p class="text-xl font-bold dark:text-white">KES {{ totalPrice.toLocaleString() }}</p>
                    </div>
                </div>

                <div class="md:col-span-2 bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search by name, serial, MAC, or model..."
                                class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none dark:text-white"
                            />
                        </div>
                        <select 
                            v-model="statusFilter"
                            class="px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-sm outline-none dark:text-white"
                        >
                            <option value="">All Statuses</option>
                            <option value="in_stock">In Stock</option>
                            <option value="assigned">Assigned</option>
                            <option value="faulty">Faulty</option>
                            <option value="retired">Retired</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden">
                <div v-if="selectedEquipment.length" class="p-3 bg-blue-50 dark:bg-blue-900/20 border-b dark:border-gray-700 flex justify-between items-center">
                    <span class="text-sm font-medium text-blue-700 dark:text-blue-400">
                        {{ selectedEquipment.length }} items selected
                    </span>
                    <button @click="bulkDelete" class="text-red-600 dark:text-red-400 hover:text-red-800 text-sm font-semibold">
                        Delete Selected
                    </button>
                </div>

                <div class="overflow-x-auto text-sm">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">
                                <th class="px-6 py-4 w-10">
                                    <input type="checkbox" v-model="selectAll" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700" />
                                </th>
                                <th class="px-6 py-4 font-semibold uppercase tracking-wider">Equipment</th>
                                <th class="px-6 py-4 font-semibold uppercase tracking-wider">Identity</th>
                                <th class="px-6 py-4 font-semibold uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 font-semibold uppercase tracking-wider">Stock</th>
                                <th class="px-6 py-4 font-semibold uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-4 font-semibold uppercase tracking-wider">User</th>
                                <th class="px-6 py-4 font-semibold uppercase tracking-wider">Value</th>
                                <th class="px-6 py-4 font-semibold uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="item in equipment.data" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <input type="checkbox" :value="item.id" v-model="selectedEquipment" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700" />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900 dark:text-white">{{ item.name }}</span>
                                        <span class="text-xs text-gray-500">{{ item.brand }} {{ item.model }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-1.5 text-xs">
                                            <span class="text-gray-400">SN:</span>
                                            <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded dark:text-gray-300">{{ item.serial_number }}</code>
                                        </div>
                                        <div v-if="item.mac_address" class="flex items-center gap-1.5 text-xs">
                                            <span class="text-gray-400">MAC:</span>
                                            <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded dark:text-gray-300">{{ item.mac_address }}</code>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="getStatusClass(item.status)" class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide">
                                        {{ formatStatus(item.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-mono font-bold" :class="item.quantity > 5 ? 'text-gray-900 dark:text-white' : 'text-red-600 dark:text-red-400'">
                                        {{ item.quantity }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500 uppercase tracking-tighter italic">
                                    {{ item.unit || 'pcs' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="item.assigned_user_id" class="flex flex-col">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ item.assignedUser?.username }}</span>
                                        <span class="text-[10px] text-gray-500 truncate max-w-[120px]">{{ item.assignedUser?.full_name }}</span>
                                    </div>
                                    <span v-else class="text-gray-400 text-xs italic">Not assigned</span>
                                </td>
                                <td class="px-6 py-4 font-mono text-gray-600 dark:text-gray-400">
                                    {{ item.total_price?.toLocaleString() ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end items-center gap-2">
                                        <!-- Actions Group -->
                                        <div class="flex items-center bg-gray-50 dark:bg-gray-900 rounded-lg p-1">
                                            <button v-if="item.status === 'in_stock'" @click="openAssignModal(item)" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition-shadow" title="Assign User">
                                                <UserPlus class="w-4 h-4" />
                                            </button>
                                            <button v-if="item.quantity > 0" @click="openUsageModal(item)" class="p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-md transition-shadow" title="Log Usage">
                                                <ShoppingCart class="w-4 h-4" />
                                            </button>
                                            <button v-if="item.status === 'assigned'" @click="releaseEquipment(item)" class="p-1.5 text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20 rounded-md transition-shadow" title="Release to Stock">
                                                <UserMinus class="w-4 h-4" />
                                            </button>
                                            <button @click="viewHistory(item)" class="p-1.5 text-gray-600 hover:bg-gray-200 dark:hover:bg-gray-800 rounded-md transition-shadow" title="View History">
                                                <History class="w-4 h-4" />
                                            </button>
                                            <div class="w-px h-4 bg-gray-200 dark:bg-gray-700 mx-1"></div>
                                            <button @click="openEditModal(item)" class="p-1.5 text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-md transition-shadow">
                                                <Edit class="w-4 h-4" />
                                            </button>
                                            <button @click="remove(item.id)" class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-shadow">
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!equipment.data.length" class="p-12 text-center">
                    <Package class="w-12 h-12 text-gray-300 mx-auto mb-4" />
                    <p class="text-gray-500 font-medium">No equipment found.</p>
                    <button @click="openAddModal" class="mt-2 text-blue-600 text-sm font-semibold hover:underline">Add your first item</button>
                </div>
            </div>

            <div v-show="equipment.total > 0" class="flex justify-center mt-6">
                <Pagination :links="equipment.links" />
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false" maxWidth="2xl">
            <form @submit.prevent="submit" class="p-6 bg-white dark:bg-gray-800">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold dark:text-white">{{ editing ? 'Edit Equipment' : 'Add New Equipment' }}</h2>
                    <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600"><X /></button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <InputLabel for="name" value="Equipment Name" required />
                        <TextInput v-model="form.name" id="name" class="mt-1 block w-full" placeholder="e.g. Huawei ONU 4-Port" />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="brand" value="Brand" />
                        <TextInput v-model="form.brand" id="brand" class="mt-1 block w-full" placeholder="e.g. MikroTik, Huawei" />
                        <InputError :message="form.errors.brand" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="model" value="Model/Version" />
                        <TextInput v-model="form.model" id="model" class="mt-1 block w-full" placeholder="e.g. HG8546M" />
                        <InputError :message="form.errors.model" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="type" value="Device Type" required />
                        <select v-model="form.type" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">Select Type</option>
                            <option value="Router">Router</option>
                            <option value="ONU">ONU/ONT</option>
                            <option value="Antenna">Antenna/CPE</option>
                            <option value="Cable">Cable / Drop</option>
                            <option value="Switch">Switch</option>
                            <option value="Tool">Field Tool</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="condition" value="Item Condition" required />
                        <select v-model="form.condition" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="new">New</option>
                            <option value="used">Used</option>
                            <option value="refurbished">Refurbished</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="serial_number" value="Serial Number" required />
                        <TextInput v-model="form.serial_number" id="serial_number" class="mt-1 block w-full" />
                        <InputError :message="form.errors.serial_number" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="mac_address" value="MAC Address" />
                        <TextInput v-model="form.mac_address" id="mac_address" class="mt-1 block w-full" placeholder="00:00:00:00:00:00" />
                        <InputError :message="form.errors.mac_address" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="status" value="Initial Status" />
                        <select v-model="form.status" :disabled="editing" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm bg-gray-50 opacity-80" v-if="editing">
                            <option :value="form.status">{{ formatStatus(form.status) }}</option>
                        </select>
                        <select v-else v-model="form.status" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="in_stock">In Stock</option>
                            <option value="faulty">Faulty</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="total_price" value="Valuation (KES)" />
                        <TextInput v-model="form.total_price" id="total_price" type="number" step="0.01" class="mt-1 block w-full" />
                    </div>

                    <div>
                        <InputLabel for="quantity" value="Quantity/Stock" required />
                        <TextInput v-model="form.quantity" id="quantity" type="number" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="form.errors.quantity" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="unit" value="Measuring Unit" required />
                        <select v-model="form.unit" id="unit" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="pcs">Pieces (Pcs)</option>
                            <option value="meters">Meters (m)</option>
                            <option value="feet">Feet (ft)</option>
                            <option value="rolls">Rolls</option>
                            <option value="boxes">Boxes</option>
                            <option value="pairs">Pairs</option>
                        </select>
                        <InputError :message="form.errors.unit" class="mt-1" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="notes" value="Storage Details / Notes" />
                        <textarea v-model="form.notes" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" rows="2"></textarea>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3 border-t dark:border-gray-700 pt-5">
                    <SecondaryButton @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ editing ? 'Update Equipment' : 'Add to Inventory' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Assignment Modal -->
        <Modal :show="showAssignModal" @close="showAssignModal = false">
            <div class="p-6 bg-white dark:bg-gray-800">
                <h3 class="text-lg font-bold mb-2 dark:text-white">Assign Equipment</h3>
                <p class="text-sm text-gray-500 mb-6">Device: {{ selectedEquipmentForAssign?.name }} ({{ selectedEquipmentForAssign?.serial_number }})</p>

                <div class="space-y-4">
                    <div>
                        <InputLabel value="Search Customer" />
                        <div class="relative mt-1">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input 
                                @input="e => searchUsers(e.target.value)"
                                placeholder="Name or Account Number..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded-lg outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>

                    <div v-if="userSearchResults.length" class="max-h-48 overflow-y-auto border dark:border-gray-700 rounded-lg divide-y dark:divide-gray-700">
                        <button 
                            v-for="user in userSearchResults" 
                            :key="user.id"
                            @click="assignForm.user_id = user.id"
                            type="button"
                            :class="assignForm.user_id === user.id ? 'bg-blue-50 dark:bg-blue-900/30 ring-2 ring-blue-500' : 'hover:bg-gray-50 dark:hover:bg-gray-900/40'"
                            class="w-full text-left p-3 flex justify-between items-center transition-colors"
                        >
                            <div class="flex flex-col">
                                <span class="font-bold text-sm dark:text-white">{{ user.username }}</span>
                                <span class="text-xs text-gray-500">{{ user.full_name }}</span>
                            </div>
                            <CheckCircle2 v-if="assignForm.user_id === user.id" class="w-5 h-5 text-blue-500" />
                        </button>
                    </div>

                    <div v-if="searchingUsers" class="text-center py-4 text-gray-400 text-sm italic">Searching...</div>

                    <div>
                        <InputLabel value="Assignment Notes" />
                        <textarea v-model="assignForm.notes" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded-md h-20" placeholder="e.g. New installation at main street"></textarea>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3 pt-5 border-t dark:border-gray-700">
                    <SecondaryButton @click="showAssignModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitAssign" :disabled="!assignForm.user_id || assignForm.processing">
                        Confirm Assignment
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Log Usage Modal -->
        <Modal :show="showUsageModal" @close="showUsageModal = false">
            <div class="p-6 bg-white dark:bg-gray-800">
                <h3 class="text-lg font-bold mb-2 dark:text-white">Log Equipment Usage</h3>
                <p class="text-sm text-gray-500 mb-6">Device: {{ selectedEquipmentForUsage?.name }} (Stock: {{ selectedEquipmentForUsage?.quantity }} {{ selectedEquipmentForUsage?.unit }})</p>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="usage_quantity" :value="`Quantity Used (${selectedEquipmentForUsage?.unit})`" required />
                        <TextInput 
                            v-model="usageForm.quantity" 
                            id="usage_quantity" 
                            type="number" 
                            step="0.01"
                            class="mt-1 block w-full" 
                            :max="selectedEquipmentForUsage?.quantity"
                            min="0.01"
                        />
                        <InputError :message="usageForm.errors.quantity" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="usage_details" value="Usage Details / Reason" />
                        <textarea 
                            v-model="usageForm.details" 
                            id="usage_details"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded-md h-20" 
                            placeholder="e.g. Used for main street installation"
                        ></textarea>
                        <InputError :message="usageForm.errors.details" class="mt-1" />
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3 pt-5 border-t dark:border-gray-700">
                    <SecondaryButton @click="showUsageModal = false">Cancel</SecondaryButton>
                    <PrimaryButton @click="submitUsage" :disabled="usageForm.processing">
                        Log Usage
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- History Modal -->
        <Modal :show="showHistoryModal" @close="showHistoryModal = false" maxWidth="3xl">
            <div class="p-6 bg-white dark:bg-gray-800">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold dark:text-white">Activity Log</h3>
                    <button @click="showHistoryModal = false" class="text-gray-400 hover:text-gray-600"><X /></button>
                </div>

                <div v-if="loadingHistory" class="text-center py-20">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                </div>

                <div v-else class="space-y-6">
                    <div v-if="!equipmentHistory.length" class="text-center py-20 text-gray-400">
                        No history logs available for this item.
                    </div>

                    <div class="relative space-y-8 before:absolute before:inset-0 before:left-8 before:h-full before:w-0.5 before:bg-gray-100 dark:before:bg-gray-700">
                        <div v-for="log in equipmentHistory" :key="log.id" class="relative pl-14">
                            <div class="absolute left-6 top-0 w-4 h-4 rounded-full bg-white dark:bg-gray-800 border-2 border-blue-500 z-10"></div>
                            
                            <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl border border-gray-100 dark:border-gray-700">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold uppercase tracking-wider">
                                        {{ log.action.replace('_', ' ') }}
                                    </span>
                                    <span class="text-[10px] text-gray-400">{{ new Date(log.created_at).toLocaleString() }}</span>
                                </div>
                                
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ log.description }}</p>

                                <div class="mt-3 flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                                    <div class="w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-[8px] font-bold">
                                        {{ log.performer?.name?.charAt(0) }}
                                    </div>
                                    <span class="text-[10px] text-gray-500">Performed by: {{ log.performer?.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-right">
                    <SecondaryButton @click="showHistoryModal = false">Close</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
