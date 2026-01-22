<script setup>
import { ref, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import SelectInput from '@/Components/SelectInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Plus, Edit, Trash2, MapPin, Star } from 'lucide-vue-next'

const props = defineProps({
    technicians: Object,
    stats: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')

let searchTimeout
watch([search, statusFilter], () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(
            route('tenant.technicians.index'),
            { search: search.value, status: statusFilter.value },
            { preserveState: true, preserveScroll: true, replace: true }
        )
    }, 300)
})

const showModal = ref(false)
const editing = ref(null)

const form = useForm({
    name: '',
    email: '',
    phone: '',
    employee_id: '',
    status: 'active',
    specialization: '',
    skills: [],
    notes: '',
})

function openAddModal() {
    form.reset()
    editing.value = null
    showModal.value = true
}

function openEditModal(tech) {
    form.name = tech.name
    form.email = tech.email
    form.phone = tech.phone
    form.employee_id = tech.employee_id
    form.status = tech.status
    form.specialization = tech.specialization
    form.skills = tech.skills || []
    form.notes = tech.notes
    editing.value = tech.id
    showModal.value = true
}

function submit() {
    if (editing.value) {
        form.put(route('tenant.technicians.update', editing.value), {
            onSuccess: () => showModal.value = false
        })
    } else {
        form.post(route('tenant.technicians.store'), {
            onSuccess: () => showModal.value = false
        })
    }
}

function remove(id) {
    if (confirm('Delete this technician?')) {
        router.delete(route('tenant.technicians.destroy', id))
    }
}

function getStatusColor(status) {
    const colors = {
        active: 'green',
        inactive: 'red',
        on_leave: 'yellow',
    }
    return colors[status] || 'gray'
}
</script>

<template>
    <Head title="Technicians" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Technicians</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Manage field technicians and track their performance
                        </p>
                    </div>
                    <PrimaryButton @click="openAddModal">
                        <Plus class="w-4 h-4 mr-2" />
                        Add Technician
                    </PrimaryButton>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg shadow p-4">
                        <div class="text-sm text-green-600 dark:text-green-400">Active</div>
                        <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ stats.active }}</div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg shadow p-4">
                        <div class="text-sm text-red-600 dark:text-red-400">Inactive</div>
                        <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ stats.inactive }}</div>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg shadow p-4">
                        <div class="text-sm text-yellow-600 dark:text-yellow-400">On Leave</div>
                        <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ stats.on_leave }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Search" />
                            <TextInput
                                v-model="search"
                                type="text"
                                placeholder="Search technicians..."
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel value="Status" />
                            <SelectInput v-model="statusFilter" class="mt-1 block w-full">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </SelectInput>
                        </div>
                    </div>
                </div>

                <!-- Technicians Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Technician
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Employee ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Contact
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Specialization
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Performance
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="tech in technicians.data" :key="tech.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ tech.name }}</div>
                                        <div v-if="tech.email" class="text-sm text-gray-500 dark:text-gray-400">{{ tech.email }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ tech.employee_id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ tech.phone }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ tech.specialization || 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <Star class="w-4 h-4 text-yellow-400" />
                                            <span class="text-sm text-gray-900 dark:text-white">{{ tech.average_rating.toFixed(1) }}</span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">({{ tech.completed_installations }} jobs)</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <StatusBadge :status="tech.status" :color="getStatusColor(tech.status)" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                @click="openEditModal(tech)"
                                                class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                                                title="Edit"
                                            >
                                                <Edit class="w-4 h-4" />
                                            </button>
                                            <button
                                                @click="remove(tech.id)"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                title="Delete"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <Pagination :links="technicians.links" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Modal :show="showModal" @close="showModal = false" max-width="2xl">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    {{ editing ? 'Edit Technician' : 'Add Technician' }}
                </h3>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="name" value="Name *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="employee_id" value="Employee ID *" />
                            <TextInput
                                id="employee_id"
                                v-model="form.employee_id"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.employee_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Phone *" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.phone" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.email" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="status" value="Status *" />
                            <SelectInput id="status" v-model="form.status" class="mt-1 block w-full" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="on_leave">On Leave</option>
                            </SelectInput>
                            <InputError :message="form.errors.status" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="specialization" value="Specialization" />
                            <TextInput
                                id="specialization"
                                v-model="form.specialization"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="e.g., Fiber, Wireless"
                            />
                            <InputError :message="form.errors.specialization" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="notes" value="Notes" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                        <InputError :message="form.errors.notes" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </button>
                        <PrimaryButton :disabled="form.processing">
                            {{ editing ? 'Update' : 'Create' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
