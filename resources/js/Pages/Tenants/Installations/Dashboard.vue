<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { 
    ArrowLeft, Calendar, Clock, CheckCircle, AlertTriangle, 
    User, MapPin, Play, Eye, List 
} from 'lucide-vue-next'

const props = defineProps({
    stats: Object,
    todayInstallations: Array,
    activeTechnicians: Array,
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

function startInstallation(id) {
    if (confirm('Start this installation?')) {
        router.post(route('tenant.installations.start', id))
    }
}
</script>

<template>
    <Head title="Installation Dashboard" />

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
                                    Installation Dashboard
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Today's overview and active installations
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <Link
                                :href="route('tenant.installations.calendar')"
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <Calendar class="w-4 h-4 mr-2" />
                                Calendar
                            </Link>
                            <Link
                                :href="route('tenant.installations.index')"
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <List class="w-4 h-4 mr-2" />
                                List View
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-blue-600 dark:text-blue-400 mb-1">Today Scheduled</div>
                                <div class="text-3xl font-bold text-blue-700 dark:text-blue-300">{{ stats.today_scheduled }}</div>
                            </div>
                            <Calendar class="w-10 h-10 text-blue-500 opacity-50" />
                        </div>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-yellow-600 dark:text-yellow-400 mb-1">In Progress</div>
                                <div class="text-3xl font-bold text-yellow-700 dark:text-yellow-300">{{ stats.today_in_progress }}</div>
                            </div>
                            <Clock class="w-10 h-10 text-yellow-500 opacity-50" />
                        </div>
                    </div>

                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-green-600 dark:text-green-400 mb-1">Completed Today</div>
                                <div class="text-3xl font-bold text-green-700 dark:text-green-300">{{ stats.today_completed }}</div>
                            </div>
                            <CheckCircle class="w-10 h-10 text-green-500 opacity-50" />
                        </div>
                    </div>

                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-purple-600 dark:text-purple-400 mb-1">Upcoming</div>
                                <div class="text-3xl font-bold text-purple-700 dark:text-purple-300">{{ stats.upcoming }}</div>
                            </div>
                            <Calendar class="w-10 h-10 text-purple-500 opacity-50" />
                        </div>
                    </div>

                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg shadow p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-red-600 dark:text-red-400 mb-1">Overdue</div>
                                <div class="text-3xl font-bold text-red-700 dark:text-red-300">{{ stats.overdue }}</div>
                            </div>
                            <AlertTriangle class="w-10 h-10 text-red-500 opacity-50" />
                        </div>
                    </div>
                </div>

                <!-- Today's Installations -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Today's Active Installations</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Installations scheduled or in progress for today
                        </p>
                    </div>
                    <div class="p-6">
                        <div v-if="todayInstallations.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                            No active installations for today
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                v-for="installation in todayInstallations"
                                :key="installation.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                                {{ installation.customer_name }}
                                            </h4>
                                            <StatusBadge :status="installation.status" :color="getStatusColor(installation.status)" />
                                            <StatusBadge :status="installation.priority" :color="getPriorityColor(installation.priority)" />
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                            <div class="flex items-center text-gray-600 dark:text-gray-400">
                                                <MapPin class="w-4 h-4 mr-2" />
                                                {{ installation.installation_address }}
                                            </div>
                                            <div class="flex items-center text-gray-600 dark:text-gray-400">
                                                <User class="w-4 h-4 mr-2" />
                                                {{ installation.technician?.name || 'Unassigned' }}
                                            </div>
                                            <div class="flex items-center text-gray-600 dark:text-gray-400">
                                                <Calendar class="w-4 h-4 mr-2" />
                                                {{ installation.scheduled_date }}
                                                <span v-if="installation.scheduled_time" class="ml-1">at {{ installation.scheduled_time }}</span>
                                            </div>
                                            <div class="flex items-center text-gray-600 dark:text-gray-400">
                                                <Clock class="w-4 h-4 mr-2" />
                                                {{ installation.estimated_duration || 'N/A' }} minutes
                                            </div>
                                        </div>
                                        <div v-if="installation.installation_notes" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            <strong>Notes:</strong> {{ installation.installation_notes }}
                                        </div>
                                    </div>
                                    <div class="flex gap-2 ml-4">
                                        <Link
                                            :href="route('tenant.installations.show', installation.id)"
                                            class="p-2 text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20 rounded-lg"
                                            title="View Details"
                                        >
                                            <Eye class="w-5 h-5" />
                                        </Link>
                                        <button
                                            v-if="installation.status === 'scheduled'"
                                            @click="startInstallation(installation.id)"
                                            class="p-2 text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20 rounded-lg"
                                            title="Start Installation"
                                        >
                                            <Play class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Technicians -->
                <div v-if="activeTechnicians && activeTechnicians.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active Technicians</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Technicians with installations today
                        </p>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="technician in activeTechnicians"
                                :key="technician.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                            >
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/20 rounded-full flex items-center justify-center">
                                        <User class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ technician.name }}</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ technician.phone }}</p>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <strong>Today's Installations:</strong> {{ technician.installations?.length || 0 }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
