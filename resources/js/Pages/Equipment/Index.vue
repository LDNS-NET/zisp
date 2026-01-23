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
import { Plus, Edit, Trash2, Save, X, Search } from 'lucide-vue-next'

const props = defineProps({
    equipment: Object,
    totalPrice: Number,
    Count: Object,
    filters: Object,
    Pagination: Object,
})

const search = ref(props.filters?.search || '');
let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('tenants.equipment.index'),
            { search: value },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
});

const showModal = ref(false)
const editing = ref(null)
const selectedTenantEquipment = ref([])
const selectAll = ref(false)



const form = useForm({
    name: '',
    type: '',
    serial_number: '',
    location: '',
    model: '',
    price: '',
    total_price: '',
    assigned_to: '',
})

function openAddModal() {
    form.reset()
    editing.value = null
    showModal.value = true
}

function openEditModal(equip) {
    form.name = equip.name
    form.type = equip.type
    form.serial_number = equip.serial_number
    form.location = equip.location
    form.model = equip.model
    form.price = equip.price
    form.total_price = equip.total_price
    form.assigned_to = equip.assigned_to
    editing.value = equip.id
    showModal.value = true
}

function submit() {
    if (editing.value) {
        form.put(route('tenants.equipment.update', editing.value), {
            onSuccess: () => showModal.value = false
        })
    } else {
        form.post(route('tenants.equipment.store'), {
            onSuccess: () => showModal.value = false
        })
    }
}

function remove(id) {
    if (confirm("Delete this Equipment")){
        router.delete(route('tenants.equipment.destroy',id))
    }
}

watch(selectAll, (val) => {
  if (val) {
    selectedTenantEquipment.value = props.equipment.data.map(equipment => equipment.id)
  } else {
    selectedTenantEquipment.value = []
  }
})

const allIds = computed(() => props.equipment.data.map(l => l.id))


//bulk delete
const bulkDelete = () => {
  if (!selectedTenantEquipment.value.length) return
  if (!confirm('Are you sure you want to delete Equipment?')) return

  router.delete(route('tenants.equipment.bulk-delete'), {
    data: { ids: selectedTenantEquipment.value },
    onSuccess: () => {
      selectedTenantEquipment.value = []
      router.visit(route('tenants.equipment.index'), {
        preserveScroll: true,
      })
    }
  })
}

</script>

<template>
<AuthenticatedLayout>
    <Head title="Equipment" />

    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Equipment</h2>
            
            <div class="flex items-center gap-4 w-full sm:w-auto">
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search equipment..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg leading-5 bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition duration-150 ease-in-out"
                    />
                </div>
            
                <PrimaryButton @click="openAddModal" class="flex items-center gap-2 whitespace-nowrap" >
                    <Plus class="h-4 w-4" /> Equipment
                </PrimaryButton>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow text-xl font-semibold text-gray-900 dark:text-white">
            Total Equipment Cost: KES {{ totalPrice.toLocaleString() }}
        </div>

        <!--bulk delete option opens if checkbox value is true-->
        <div v-if="selectedTenantEquipment.length" class="mb-4 flex items-center justify-between bg-yellow-50 dark:bg-yellow-900/20 p-3 border border-yellow-200 dark:border-yellow-700 rounded">
            <div class="flex gap-2">
                <span class="text-yellow-800 dark:text-yellow-200">{{ selectedTenantEquipment.length }} item(s) selected</span>
                <DangerButton @click="bulkDelete">Delete ({{ selectedTenantEquipment.length }})</DangerButton>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg shadow">
            <table class="w-full bg-white dark:bg-gray-800">
                <thead class="bg-gray-100 dark:bg-gray-700 text-left">
                    <tr>
                        <th class="px-4 py-3">
                            <input 
                                type="checkbox" 
                                v-model="selectAll"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700"
                            />
                        </th>
                        <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Name</th>
                        <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Type</th>
                        <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Serial</th>
                        <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Location</th>
                        <th class="px-4 py-2 text-gray-700 dark:text-gray-200">User</th>
                        <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Price (Ksh)</th>
                        <th class="px-4 py-2 text-gray-700 dark:text-gray-200">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in equipment.data" :key="item.id" class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-4 py-3">
                            <input 
                                type="checkbox" 
                                :value="item.id" 
                                v-model="selectedTenantEquipment"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700"
                            />
                        </td>
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.type }}</td>
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.serial_number }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ item.location ?? '-' }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ item.assigned_to ?? '-' }}</td>
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ item.total_price?.toLocaleString() ?? '0.00' }}</td>
                        <td class="px-4 py-2 space-x-2">
                            <button 
                                @click="openEditModal(item)" 
                                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                                title="Edit"
                            >
                                <Edit class="w-4 h-4" />
                            </button>
                            <button 
                                @click="remove(item.id)" 
                                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                title="Delete"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div v-show="equipment.total > 0" class="flex justify-center mt-6">
        <Pagination 
            :links="equipment.links" 
            :per-page="equipment.per_page"
            :total="equipment.total"
            :from="equipment.from"
            :to="equipment.to"
        />
    </div>

    <!-- Modal -->
    <Modal :show="showModal" @close="showModal = false">
        <form @submit.prevent="submit" class="p-6 space-y-4 bg-white dark:bg-gray-800">
            <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
                {{ editing ? 'Edit Equipment' : 'Add Equipment' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput v-model="form.name" id="name" class="mt-1 block w-full" />
                    <InputError :message="form.errors.name" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="type" value="Type" />
                    <TextInput v-model="form.type" id="type" class="mt-1 block w-full" />
                    <InputError :message="form.errors.type" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="serial_number" value="Serial Number" />
                    <TextInput v-model="form.serial_number" id="serial_number" class="mt-1 block w-full" />
                    <InputError :message="form.errors.serial_number" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="location" value="Location" />
                    <TextInput v-model="form.location" id="location" class="mt-1 block w-full" />
                    <InputError :message="form.errors.location" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="model" value="Model" />
                    <TextInput v-model="form.model" id="model" class="mt-1 block w-full" />
                    <InputError :message="form.errors.model" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="price" value="Price" />
                    <TextInput v-model="form.price" id="price" type="number" step="0.01" class="mt-1 block w-full" />
                    <InputError :message="form.errors.price" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="total_price" value="Total Price" />
                    <TextInput v-model="form.total_price" id="total_price" type="number" step="0.01" class="mt-1 block w-full" />
                    <InputError :message="form.errors.total_price" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="assigned_to" value="Assigned To" />
                    <TextInput v-model="form.assigned_to" id="assigned_to" class="mt-1 block w-full" />
                    <InputError :message="form.errors.assigned_to" class="mt-2" />
                </div>
            </div>

            <div class="mt-4 text-right">
                <PrimaryButton :disabled="form.processing">
                    {{ editing ? 'Update' : 'Save' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</AuthenticatedLayout>
</template>
