<script setup>
import { ref, computed } from 'vue'
import { Head, router, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Pagination from '@/Components/Pagination.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import { CheckCircle, Clock, MapPin, User, Calendar, Package, Play, Check, Eye, AlertCircle, X, XCircle } from 'lucide-vue-next'

const props = defineProps({
    installations: Object,
    availableInstallations: Array,
    stats: Object,
    filters: Object,
})

const statusFilter = ref(props.filters?.status || '')
const showPickModal = ref(false)
const selectedInstallation = ref(null)

const pickForm = useForm({
    scheduled_date: '',
    scheduled_time: '',
    estimated_duration: 120, // Default 2 hours
})

function getStatusColor(status) {
    const colors = {
        new: 'orange',
        pending: 'purple',
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

function openPickModal(installation) {
    selectedInstallation.value = installation
    pickForm.scheduled_date = ''
    pickForm.scheduled_time = ''
    pickForm.estimated_duration = 120
    showPickModal.value = true
}

function closePickModal() {
    showPickModal.value = false
    selectedInstallation.value = null
    pickForm.reset()
}

function submitPick() {
    pickForm.post(route('tenant.installations.pick', selectedInstallation.value.id), {
        onSuccess: () => {
            closePickModal()
        }
    })
}

function unpickInstallation(id) {
    if (confirm('Unpick this installation? It will become available for other technicians.')) {
        router.post(route('tenant.installations.unpick', id))
    }
}

function startInstallation(id) {
    if (confirm('Start this installation?')) {
        router.post(route('tenant.installations.start', id))
    }
}

function completeInstallation(id) {
    router.visit(route('tenant.installations.show', id))
}

function filterByStatus(status) {
    router.get(route('tenant.installations.my-installations'), { status }, { 
        preserveState: true, 
        preserveScroll: true 
    })
}
</script>

<template>
    <Head title="My Installations" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">My Installations</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        View and manage your assigned installations
                    </p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Assigned</div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.assigned }}</div>
                            </div>
                            <User class="w-8 h-8 text-gray-400" />
                        </div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-blue-600 dark:text-blue-400">Picked</div>
                                <div class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ stats.picked }}</div>
                            </div>
                            <CheckCircle class="w-8 h-8 text-blue-400" />
                        </div>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-yellow-600 dark:text-yellow-400">In Progress</div>
                                <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-300">{{ stats.in_progress }}</div>
                            </div>
                            <Clock class="w-8 h-8 text-yellow-400" />
                        </div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg shadow p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-green-600 dark:text-green-400">Completed Today</div>
                                <div class="text-2xl font-bold text-green-900 dark:text-green-300">{{ stats.completed_today }}</div>
                            </div>
                            <Check class="w-8 h-8 text-green-400" />
                        </div>
                    </div>
                </div>

                <!-- Available Installations -->
                <div v-if="availableInstallations.length > 0" class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <AlertCircle class="w-5 h-5 mr-2 text-orange-500" />
                        Available Installations (Not Picked)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="inst in availableInstallations" :key="inst.id" 
                             class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border-l-4 border-orange-500">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ inst.customer_name }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ inst.installation_number }}</div>
                                </div>
                                <StatusBadge :status="inst.priority" :color="getPriorityColor(inst.priority)" />
                            </div>
                            <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400 mb-3">
                                <div class="flex items-center">
                                    <User class="w-4 h-4 mr-2" />
                                    {{ inst.customer_phone }}
                                </div>
                                <div class="flex items-center">
                                    <MapPin class="w-4 h-4 mr-2" />
                                    {{ inst.installation_address.substring(0, 40) }}...
                                </div>
                            </div>
                            <button
                                @click="openPickModal(inst)"
                                class="w-full px-3 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm font-medium"
                            >
                                Pick This Job
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            @click="filterByStatus('')"
                            :class="[
                                statusFilter === '' 
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            All
                        </button>
                        <button
                            @click="filterByStatus('scheduled')"
                            :class="[
                                statusFilter === 'scheduled' 
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Scheduled
                        </button>
                        <button
                            @click="filterByStatus('in_progress')"
                            :class="[
                                statusFilter === 'in_progress' 
                                    ? 'border-yellow-500 text-yellow-600 dark:text-yellow-400' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            In Progress
                        </button>
                        <button
                            @click="filterByStatus('completed')"
                            :class="[
                                statusFilter === 'completed' 
                                    ? 'border-green-500 text-green-600 dark:text-green-400' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Completed
                        </button>
                    </nav>
                </div>

                <!-- My Installations Table -->
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
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ installation.installation_number }}</div>
                                        <div v-if="installation.picked_by" class="text-xs text-blue-600 dark:text-blue-400">
                                            ✓ Picked by you
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ installation.customer_name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ installation.customer_phone }}</div>
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
                                                title="View Details"
                                            >
                                                <Eye class="w-4 h-4" />
                                            </Link>
                                            <button
                                                v-if="installation.status === 'pending' && installation.picked_by"
                                                @click="unpickInstallation(installation.id)"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                title="Unpick Installation"
                                            >
                                                <XCircle class="w-4 h-4" />
                                            </button>
                                            <button
                                                v-if="installation.status === 'pending' && installation.picked_by"
                                                @click="startInstallation(installation.id)"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                title="Start Installation"
                                            >
                                                <Play class="w-4 h-4" />
                                            </button>
                                            <button
                                                v-if="installation.status === 'in_progress'"
                                                @click="completeInstallation(installation.id)"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                title="Complete Installation"
                                            >
                                                <Check class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="installations.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        No installations found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="installations.data.length > 0" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        <Pagination :links="installations.links" />
                    </div>
                </div>

                <!-- Pick Installation Modal -->
                <div v-if="showPickModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closePickModal"></div>
                        
                        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        Pick Installation
                                    </h3>
                                    <button @click="closePickModal" class="text-gray-400 hover:text-gray-500">
                                        <X class="w-5 h-5" />
                                    </button>
                                </div>
                                
                                <div v-if="selectedInstallation" class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ selectedInstallation.customer_name }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ selectedInstallation.installation_address }}</div>
                                </div>

                                <form @submit.prevent="submitPick" class="space-y-4">
                                    <div>
                                        <InputLabel for="scheduled_date" value="Scheduled Date *" />
                                        <input
                                            id="scheduled_date"
                                            v-model="pickForm.scheduled_date"
                                            type="date"
                                            :min="new Date().toISOString().split('T')[0]"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required
                                        />
                                        <div v-if="pickForm.errors.scheduled_date" class="text-red-600 text-sm mt-1">
                                            {{ pickForm.errors.scheduled_date }}
                                        </div>
                                    </div>

                                    <div>
                                        <InputLabel for="scheduled_time" value="Scheduled Time (Optional)" />
                                        <input
                                            id="scheduled_time"
                                            v-model="pickForm.scheduled_time"
                                            type="time"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        />
                                        <div v-if="pickForm.errors.scheduled_time" class="text-red-600 text-sm mt-1">
                                            {{ pickForm.errors.scheduled_time }}
                                        </div>
                                    </div>

                                    <div>
                                        <InputLabel for="estimated_duration" value="Estimated Duration (minutes) *" />
                                        <select
                                            id="estimated_duration"
                                            v-model="pickForm.estimated_duration"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required
                                        >
                                            <option :value="30">30 minutes</option>
                                            <option :value="60">1 hour</option>
                                            <option :value="90">1.5 hours</option>
                                            <option :value="120">2 hours</option>
                                            <option :value="180">3 hours</option>
                                            <option :value="240">4 hours</option>
                                            <option :value="300">5 hours</option>
                                            <option :value="360">6 hours</option>
                                            <option :value="480">8 hours</option>
                                        </select>
                                        <div v-if="pickForm.errors.estimated_duration" class="text-red-600 text-sm mt-1">
                                            {{ pickForm.errors.estimated_duration }}
                                        </div>
                                    </div>

                                    <div class="flex gap-3 mt-6">
                                        <button
                                            type="button"
                                            @click="closePickModal"
                                            class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="pickForm.processing"
                                            class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 disabled:opacity-50"
                                        >
                                            {{ pickForm.processing ? 'Picking...' : 'Pick Installation' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
