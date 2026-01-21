<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Activity, ChevronLeft, Calendar, User, Globe } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    activities: Object,
});
</script>

<template>
    <Head title="Staff Activity Log" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('settings.staff.index')" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors">
                    <ChevronLeft class="h-6 w-6" />
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Full Audit Trail
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Timestamp</th>
                                        <th class="px-6 py-3">Staff Member</th>
                                        <th class="px-6 py-3">Action</th>
                                        <th class="px-6 py-3">Network Profile</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="activity in activities.data" :key="activity.id" class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2 text-gray-500">
                                                <Calendar class="h-4 w-4" />
                                                {{ new Date(activity.created_at).toLocaleString() }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 text-xs font-bold">
                                                    {{ activity.user?.name.charAt(0) }}
                                                </div>
                                                <span class="font-medium">{{ activity.user?.name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-medium text-gray-900 dark:text-white">{{ activity.description }}</span>
                                                <span class="text-[10px] text-gray-400 uppercase tracking-wider">{{ activity.action }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                                <span class="flex items-center gap-1">
                                                    <Globe class="h-3 w-3" />
                                                    {{ activity.ip_address }}
                                                </span>
                                                <span class="truncate max-w-[150px]" :title="activity.user_agent">
                                                    {{ activity.user_agent }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!activities.data.length">
                                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                            <Activity class="h-12 w-12 mx-auto mb-4 opacity-20" />
                                            <p>No activity records found.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6">
                            <Pagination :links="activities.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
