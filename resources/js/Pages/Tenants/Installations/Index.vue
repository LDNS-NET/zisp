<script setup>
import { ref, watch, computed } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import SelectInput from '@/Components/SelectInput.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Calendar, MapPin, User, Clock, Filter, Eye, Edit, Trash2, CheckCircle, XCircle, Play } from 'lucide-vue-next'

const props = defineProps({
    installations: Object,
    stats: Object,
    technicians: Array,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const statusFilter = ref(props.filters?.status || '')
const priorityFilter = ref(props.filters?.priority || '')
const technicianFilter = ref(props.filters?.technician_id || '')
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')

let searchTimeout
watch([search, statusFilter, priorityFilter, technicianFilter, dateFrom, dateTo], () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(
            route('tenant.installations.index'),
            {
                search: search.value,
                status: statusFilter.value,
                priority: priorityFilter.value,
                technician_id: technicianFilter.value,
                date_from: dateFrom.value,
                date_to: dateTo.value,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        )
    }, 300)
})

function getStatusColor(status) {
    const colors = {
        scheduled: 'blue',
        in_progress: 'yellow',
        completed: 'green',
        cancelled: 'red',
        on_hold: 'gray',
    }
    return colors[status] || 'gray'
}

function getPriorityColor(priority) {
    const colors = {
        urgent: 'red',
        high: 'orange',
        medium: 'yellow',
        low: 'green',
    }
    return colors[priority] || 'gray'
}

function startInstallation(id) {
    if (confirm('Start this installation?')) {
        router.post(route('tenant.installations.start', id))
    }
}

function completeInstallation(id) {
    router.visit(route('tenant.installations.show', id))
}

function cancelInstallation(id) {
    const reason = prompt('Reason for cancellation:')
    if (reason) {
        router.post(route('tenant.installations.cancel', id), { reason })
    }
}

function deleteInstallation(id) {
    if (confirm('Delete this installation?')) {
        router.delete(route('tenant.installations.destroy', id))
    }
}
</script>

<template>
    <Head title="Installation Management" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Installation Management</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Schedule and track field installations
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <Link
                            :href="route('tenant.installations.calendar')"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <Calendar class="w-4 h-4 mr-2" />
                            Calendar View
                        </Link>
                        <Link
                            :href="route('tenant.installations.dashboard')"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <MapPin class="w-4 h-4 mr-2" />
                            Dashboard
                        </Link>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg shadow p-4">
                        <div class="text-sm text-blue-600 dark:text-blue-400">Scheduled</div>
                        <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ stats.scheduled }}</div>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg shadow p-4">
                        <div class="text-sm text-yellow-600 dark:text-yellow-400">In Progress</div>
                        <div class="text-2xl font-bold text-yellow-700 dark:text-yellow-300">{{ stats.in_progress }}</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg shadow p-4">
                        <div class="text-sm text-green-600 dark:text-green-400">Completed</div>
                        <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ stats.completed }}</div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg shadow p-4">
                        <div class="text-sm text-red-600 dark:text-red-400">Cancelled</div>
                        <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ stats.cancelled }}</div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg shadow p-4">
                        <div class="text-sm text-purple-600 dark:text-purple-400">Today</div>
                        <div class="text-2xl font-bold text-purple-700 dark:text-purple-300">{{ stats.today }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div class="md:col-span-2">
                            <InputLabel value="Search" />
                            <TextInput
                                v-model="search"
                                type="text"
                                placeholder="Search installations..."
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel value="Status" />
                            <SelectInput v-model="statusFilter" class="mt-1 block w-full">
                                <option value="">All Status</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="on_hold">On Hold</option>
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Priority" />
                            <SelectInput v-model="priorityFilter" class="mt-1 block w-full">
                                <option value="">All Priorities</option>
                                <option value="urgent">Urgent</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Technician" />
                            <SelectInput v-model="technicianFilter" class="mt-1 block w-full">
                                <option value="">All Technicians</option>
                                <option v-for="tech in technicians" :key="tech.id" :value="tech.id">
                                    {{ tech.name }}
                                </option>
                            </SelectInput>
                        </div>
                        <div>
                            <InputLabel value="Date Range" />
                            <div class="flex gap-2 mt-1">
                                <input
                                    v-model="dateFrom"
                                    type="date"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                                <input
                                    v-model="dateTo"
                                    type="date"
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Installations Table -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Installation #
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Technician
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Scheduled
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Priority
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
                                <tr v-for="installation in installations.data" :key="installation.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ installation.installation_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ installation.customer_name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ installation.customer_phone }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ installation.technician?.name || 'Unassigned' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        <div>{{ installation.scheduled_date }}</div>
                                        <div v-if="installation.scheduled_time" class="text-xs text-gray-500">{{ installation.scheduled_time }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                            {{ installation.installation_type }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <StatusBadge :status="installation.priority" :color="getPriorityColor(installation.priority)" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <StatusBadge :status="installation.status" :color="getStatusColor(installation.status)" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end gap-2">
                                            <Link
                                                :href="route('tenant.installations.show', installation.id)"
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                title="View"
                                            >
                                                <Eye class="w-4 h-4" />
                                            </Link>
                                            <button
                                                v-if="installation.status === 'scheduled'"
                                                @click="startInstallation(installation.id)"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                title="Start"
                                            >
                                                <Play class="w-4 h-4" />
                                            </button>
                                            <Link
                                                v-if="installation.status !== 'completed'"
                                                :href="route('tenant.installations.edit', installation.id)"
                                                class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                                                title="Edit"
                                            >
                                                <Edit class="w-4 h-4" />
                                            </Link>
                                            <button
                                                @click="deleteInstallation(installation.id)"
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
                        <Pagination :links="installations.links" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
