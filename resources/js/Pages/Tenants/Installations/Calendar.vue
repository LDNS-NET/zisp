<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { ArrowLeft, ChevronLeft, ChevronRight, Calendar as CalendarIcon, List } from 'lucide-vue-next'

const props = defineProps({
    installations: Array,
    month: Number,
    year: Number,
})

const currentMonth = ref(props.month)
const currentYear = ref(props.year)

const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
]

const calendarDays = computed(() => {
    const firstDay = new Date(currentYear.value, currentMonth.value - 1, 1)
    const lastDay = new Date(currentYear.value, currentMonth.value, 0)
    const daysInMonth = lastDay.getDate()
    const startingDayOfWeek = firstDay.getDay()
    
    const days = []
    
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < startingDayOfWeek; i++) {
        days.push({ date: null, installations: [] })
    }
    
    // Add days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`
        const dayInstallations = props.installations.filter(inst => inst.start.startsWith(dateStr))
        days.push({ date: day, installations: dayInstallations })
    }
    
    return days
})

function previousMonth() {
    if (currentMonth.value === 1) {
        currentMonth.value = 12
        currentYear.value--
    } else {
        currentMonth.value--
    }
    loadMonth()
}

function nextMonth() {
    if (currentMonth.value === 12) {
        currentMonth.value = 1
        currentYear.value++
    } else {
        currentMonth.value++
    }
    loadMonth()
}

function loadMonth() {
    router.get(route('tenant.installations.calendar'), {
        month: currentMonth.value,
        year: currentYear.value
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

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

function getPriorityBorderColor(priority) {
    const colors = {
        urgent: 'border-red-500',
        high: 'border-orange-500',
        medium: 'border-yellow-500',
        low: 'border-green-500',
    }
    return colors[priority] || 'border-gray-300'
}
</script>

<template>
    <Head title="Installation Calendar" />

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
                                    Installation Calendar
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    View installations by date
                                </p>
                            </div>
                        </div>
                        <Link
                            :href="route('tenant.installations.index')"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <List class="w-4 h-4 mr-2" />
                            List View
                        </Link>
                    </div>
                </div>

                <!-- Calendar -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <!-- Calendar Header -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <button
                            @click="previousMonth"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                        >
                            <ChevronLeft class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        </button>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ monthNames[currentMonth - 1] }} {{ currentYear }}
                        </h3>
                        <button
                            @click="nextMonth"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                        >
                            <ChevronRight class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        </button>
                    </div>

                    <!-- Day Headers -->
                    <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day"
                            class="p-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900">
                            {{ day }}
                        </div>
                    </div>

                    <!-- Calendar Grid -->
                    <div class="grid grid-cols-7">
                        <div
                            v-for="(day, index) in calendarDays"
                            :key="index"
                            class="min-h-[120px] border-r border-b border-gray-200 dark:border-gray-700 p-2"
                            :class="{ 'bg-gray-50 dark:bg-gray-900': !day.date }"
                        >
                            <div v-if="day.date" class="h-full flex flex-col">
                                <div class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                                    {{ day.date }}
                                </div>
                                <div class="flex-1 space-y-1 overflow-y-auto">
                                    <Link
                                        v-for="installation in day.installations"
                                        :key="installation.id"
                                        :href="route('tenant.installations.show', installation.id)"
                                        class="block p-1 rounded text-xs border-l-2 hover:bg-gray-100 dark:hover:bg-gray-700"
                                        :class="getPriorityBorderColor(installation.priority)"
                                        :style="{ backgroundColor: installation.backgroundColor + '20' }"
                                    >
                                        <div class="font-medium truncate" :style="{ color: installation.backgroundColor }">
                                            {{ installation.title }}
                                        </div>
                                        <div class="text-gray-600 dark:text-gray-400 truncate">
                                            {{ installation.technician || 'Unassigned' }}
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Legend</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded" style="background-color: #3B82F6;"></div>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Scheduled</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded" style="background-color: #F59E0B;"></div>
                            <span class="text-sm text-gray-700 dark:text-gray-300">In Progress</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded" style="background-color: #10B981;"></div>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Completed</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-4 h-4 rounded" style="background-color: #EF4444;"></div>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Cancelled</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Border colors indicate priority: 
                            <span class="text-red-500">Red (Urgent)</span>, 
                            <span class="text-orange-500">Orange (High)</span>, 
                            <span class="text-yellow-500">Yellow (Medium)</span>, 
                            <span class="text-green-500">Green (Low)</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
