<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { 
    Calendar, MapPin, User, Clock, Phone, Mail, Package, 
    CheckCircle, Edit, ArrowLeft, Play, XCircle, Wrench 
} from 'lucide-vue-next'

const props = defineProps({
    installation: Object,
    checklists: Array,
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

function startInstallation() {
    if (confirm('Start this installation?')) {
        router.post(route('tenant.installations.start', props.installation.id))
    }
}

function completeInstallation() {
    if (confirm('Mark this installation as completed?')) {
        router.post(route('tenant.installations.complete', props.installation.id))
    }
}

function cancelInstallation() {
    const reason = prompt('Reason for cancellation:')
    if (reason) {
        router.post(route('tenant.installations.cancel', props.installation.id), { reason })
    }
}
</script>

<template>
    <Head :title="`Installation #${installation.installation_number}`" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <Link
                                :href="route('tenant.installations.index')"
                                class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                            >
                                <ArrowLeft class="w-5 h-5" />
                            </Link>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    Installation #{{ installation.installation_number }}
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ installation.customer_name }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <Link
                                v-if="installation.status !== 'completed' && installation.status !== 'cancelled'"
                                :href="route('tenant.installations.edit', installation.id)"
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <Edit class="w-4 h-4 mr-2" />
                                Edit
                            </Link>
                            <button
                                v-if="installation.status === 'pending' || installation.status === 'scheduled'"
                                @click="startInstallation"
                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                            >
                                <Play class="w-4 h-4 mr-2" />
                                Start
                            </button>
                            <button
                                v-if="installation.status === 'in_progress'"
                                @click="completeInstallation"
                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                            >
                                <CheckCircle class="w-4 h-4 mr-2" />
                                Complete
                            </button>
                            <button
                                v-if="installation.status !== 'completed' && installation.status !== 'cancelled'"
                                @click="cancelInstallation"
                                class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                            >
                                <XCircle class="w-4 h-4 mr-2" />
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Status Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Status</div>
                        <StatusBadge :status="installation.status" :color="getStatusColor(installation.status)" />
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Priority</div>
                        <StatusBadge :status="installation.priority" :color="getPriorityColor(installation.priority)" />
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Type</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white capitalize">
                            {{ installation.installation_type }} - {{ installation.service_type }}
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Customer Information -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Customer Information</h3>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <User class="w-5 h-5 text-gray-400 mr-3 mt-0.5" />
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Name</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ installation.customer_name }}</div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <Phone class="w-5 h-5 text-gray-400 mr-3 mt-0.5" />
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Phone</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ installation.customer_phone }}</div>
                                    </div>
                                </div>
                                <div v-if="installation.customer_email" class="flex items-start">
                                    <Mail class="w-5 h-5 text-gray-400 mr-3 mt-0.5" />
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Email</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ installation.customer_email }}</div>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <MapPin class="w-5 h-5 text-gray-400 mr-3 mt-0.5" />
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Address</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ installation.installation_address }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Installation Details -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Installation Details</h3>
                            <div class="space-y-3">
                                <div v-if="installation.scheduled_date" class="flex items-start">
                                    <Calendar class="w-5 h-5 text-gray-400 mr-3 mt-0.5" />
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Scheduled Date</div>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ installation.scheduled_date }}
                                            <span v-if="installation.scheduled_time" class="ml-2">at {{ installation.scheduled_time }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="installation.estimated_duration" class="flex items-start">
                                    <Clock class="w-5 h-5 text-gray-400 mr-3 mt-0.5" />
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Estimated Duration</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ installation.estimated_duration }} minutes</div>
                                    </div>
                                </div>
                                <div v-if="installation.technician" class="flex items-start">
                                    <Wrench class="w-5 h-5 text-gray-400 mr-3 mt-0.5" />
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Assigned Technician</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ installation.technician.name }}</div>
                                    </div>
                                </div>
                                <div v-if="installation.equipment" class="flex items-start">
                                    <Package class="w-5 h-5 text-gray-400 mr-3 mt-0.5" />
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Equipment</div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ installation.equipment.name }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="installation.installation_notes || installation.technician_notes" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Notes</h3>
                            <div class="space-y-4">
                                <div v-if="installation.installation_notes">
                                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Installation Notes</div>
                                    <div class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ installation.installation_notes }}</div>
                                </div>
                                <div v-if="installation.technician_notes">
                                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Technician Notes</div>
                                    <div class="text-gray-900 dark:text-white whitespace-pre-wrap">{{ installation.technician_notes }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Timeline -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Timeline</h3>
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3"></div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">Created</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ installation.created_at }}</div>
                                    </div>
                                </div>
                                <div v-if="installation.picked_at" class="flex items-start">
                                    <div class="w-2 h-2 bg-purple-500 rounded-full mt-2 mr-3"></div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">Picked</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ installation.picked_at }}</div>
                                    </div>
                                </div>
                                <div v-if="installation.started_at" class="flex items-start">
                                    <div class="w-2 h-2 bg-yellow-500 rounded-full mt-2 mr-3"></div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">Started</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ installation.started_at }}</div>
                                    </div>
                                </div>
                                <div v-if="installation.completed_at" class="flex items-start">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mt-2 mr-3"></div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">Completed</div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">{{ installation.completed_at }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Checklists -->
                        <div v-if="checklists.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Checklists</h3>
                            <div class="space-y-2">
                                <div v-for="checklist in checklists" :key="checklist.id" class="text-sm text-gray-700 dark:text-gray-300">
                                    • {{ checklist.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
