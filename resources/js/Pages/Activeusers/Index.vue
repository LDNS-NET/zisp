<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Activity, Users, Wifi, Network, Clock, User, MapPin } from 'lucide-vue-next';

const props = defineProps({
    activeUsers: {
        type: Array,
        default: () => [],
    },
    message: String,
});

// Filter state
const selectedType = ref('all');

// User counts by type
const userCounts = computed(() => {
    const users = props.activeUsers || [];
    return {
        all: users.length,
        hotspot: users.filter(u => u.user_type === 'hotspot').length,
        pppoe: users.filter(u => u.user_type === 'pppoe').length,
        static: users.filter(u => u.user_type === 'static').length,
    };
});

// Filtered users based on selected type
const filteredUsers = computed(() => {
    if (selectedType.value === 'all') return props.activeUsers || [];
    return (props.activeUsers || []).filter(u => u.user_type === selectedType.value);
});

// Format uptime/session time
const formatSessionTime = (time) => {
    if (!time) return 'N/A';
    return time;
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Active Users" />

        <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-slate-950 dark:via-slate-900 dark:to-indigo-950">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 dark:from-blue-900 dark:via-indigo-900 dark:to-purple-900">
                <div class="relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10">
                        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 40px 40px;"></div>
                    </div>
                    
                    <div class="relative px-4 py-8 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-7xl">
                            <div class="flex items-center gap-3">
                                <div class="rounded-xl bg-white/20 p-3 backdrop-blur-sm">
                                    <Activity class="h-8 w-8 text-white" />
                                </div>
                                <div>
                                    <h1 class="text-3xl font-bold text-white">Active Users</h1>
                                    <p class="text-blue-100">Real-time network user monitoring</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="mb-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- All Users -->
                    <button
                        @click="selectedType = 'all'"
                        :class="[
                            'group relative overflow-hidden rounded-2xl p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl',
                            selectedType === 'all' 
                                ? 'bg-gradient-to-br from-blue-500 to-blue-600 ring-2 ring-blue-400 dark:from-blue-600 dark:to-blue-700' 
                                : 'bg-white dark:bg-slate-800'
                        ]"
                    >
                        <div :class="selectedType === 'all' ? 'opacity-10' : 'opacity-0'" class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-white/20 transition-opacity group-hover:opacity-10"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p :class="selectedType === 'all' ? 'text-blue-100' : 'text-gray-600 dark:text-gray-400'" class="text-sm font-medium">Total Online</p>
                                    <p :class="selectedType === 'all' ? 'text-white' : 'text-gray-900 dark:text-white'" class="mt-2 text-4xl font-bold">{{ userCounts.all }}</p>
                                </div>
                                <div :class="selectedType === 'all' ? 'bg-white/20' : 'bg-blue-100 dark:bg-blue-900/30'" class="rounded-xl p-3">
                                    <Users :class="selectedType === 'all' ? 'text-white' : 'text-blue-600 dark:text-blue-400'" class="h-8 w-8" />
                                </div>
                            </div>
                        </div>
                    </button>

                    <!-- Hotspot-->
                    <button
                        @click="selectedType = 'hotspot'"
                        :class="[
                            'group relative overflow-hidden rounded-2xl p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl',
                            selectedType === 'hotspot' 
                                ? 'bg-gradient-to-br from-emerald-500 to-emerald-600 ring-2 ring-emerald-400 dark:from-emerald-600 dark:to-emerald-700' 
                                : 'bg-white dark:bg-slate-800'
                        ]"
                    >
                        <div :class="selectedType === 'hotspot' ? 'opacity-10' : 'opacity-0'" class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-white/20 transition-opacity group-hover:opacity-10"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p :class="selectedType === 'hotspot' ? 'text-emerald-100' : 'text-gray-600 dark:text-gray-400'" class="text-sm font-medium">Hotspot</p>
                                    <p :class="selectedType === 'hotspot' ? 'text-white' : 'text-gray-900 dark:text-white'" class="mt-2 text-4xl font-bold">{{ userCounts.hotspot }}</p>
                                </div>
                                <div :class="selectedType === 'hotspot' ? 'bg-white/20' : 'bg-emerald-100 dark:bg-emerald-900/30'" class="rounded-xl p-3">
                                    <Wifi :class="selectedType === 'hotspot' ? 'text-white' : 'text-emerald-600 dark:text-emerald-400'" class="h-8 w-8" />
                                </div>
                            </div>
                        </div>
                    </button>

                    <!-- PPPoE -->
                    <button
                        @click="selectedType = 'pppoe'"
                        :class="[
                            'group relative overflow-hidden rounded-2xl p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl',
                            selectedType === 'pppoe' 
                                ? 'bg-gradient-to-br from-purple-500 to-purple-600 ring-2 ring-purple-400 dark:from-purple-600 dark:to-purple-700' 
                                : 'bg-white dark:bg-slate-800'
                        ]"
                    >
                        <div :class="selectedType === 'pppoe' ? 'opacity-10' : 'opacity-0'" class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-white/20 transition-opacity group-hover:opacity-10"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p :class="selectedType === 'pppoe' ? 'text-purple-100' : 'text-gray-600 dark:text-gray-400'" class="text-sm font-medium">PPPoE</p>
                                    <p :class="selectedType === 'pppoe' ? 'text-white' : 'text-gray-900 dark:text-white'" class="mt-2 text-4xl font-bold">{{ userCounts.pppoe }}</p>
                                </div>
                                <div :class="selectedType === 'pppoe' ? 'bg-white/20' : 'bg-purple-100 dark:bg-purple-900/30'" class="rounded-xl p-3">
                                    <Network :class="selectedType === 'pppoe' ? 'text-white' : 'text-purple-600 dark:text-purple-400'" class="h-8 w-8" />
                                </div>
                            </div>
                        </div>
                    </button>

                    <!-- Static -->
                    <button
                        @click="selectedType = 'static'"
                        :class="[
                            'group relative overflow-hidden rounded-2xl p-6 shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-2xl',
                            selectedType === 'static' 
                                ? 'bg-gradient-to-br from-amber-500 to-amber-600 ring-2 ring-amber-400 dark:from-amber-600 dark:to-amber-700' 
                                : 'bg-white dark:bg-slate-800'
                        ]"
                    >
                        <div :class="selectedType === 'static' ? 'opacity-10' : 'opacity-0'" class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-white/20 transition-opacity group-hover:opacity-10"></div>
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p :class="selectedType === 'static' ? 'text-amber-100' : 'text-gray-600 dark:text-gray-400'" class="text-sm font-medium">Static</p>
                                    <p :class="selectedType === 'static' ? 'text-white' : 'text-gray-900 dark:text-white'" class="mt-2 text-4xl font-bold">{{ userCounts.static }}</p>
                                </div>
                                <div :class="selectedType === 'static' ? 'bg-white/20' : 'bg-amber-100 dark:bg-amber-900/30'" class="rounded-xl p-3">
                                    <MapPin :class="selectedType === 'static' ? 'text-white' : 'text-amber-600 dark:text-amber-400'" class="h-8 w-8" />
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- Users Table Card -->
                <div class="rounded-2xl bg-white p-6 shadow-lg dark:bg-slate-800">
                    <div class="mb-6 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ selectedType === 'all' ? 'All Active Users' : `${selectedType.charAt(0).toUpperCase() + selectedType.slice(1)} Users` }}
                        </h3>
                        <span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                            {{ filteredUsers.length }} online
                        </span>
                    </div>

                    <!-- Message if no Mikrotik -->
                    <div v-if="message" class="rounded-lg bg-yellow-50 p-4 dark:bg-yellow-900/20">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">{{ message }}</p>
                    </div>

                    <!-- Table -->
                    <div v-else class="overflow-x-auto">
                        <table v-if="filteredUsers.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-slate-700/50">
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        <div class="flex items-center gap-2">
                                            <User class="h-4 w-4" />
                                            Username
                                        </div>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Type
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        IP Address
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        MAC Address
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        <div class="flex items-center gap-2">
                                            <Clock class="h-4 w-4" />
                                            Session Info
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(user, index) in filteredUsers" :key="index" class="transition-colors hover:bg-gray-50 dark:hover:bg-slate-700/30">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                                <User class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                            </div>
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ user.username || 'Unknown' }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400': user.user_type === 'hotspot',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400': user.user_type === 'pppoe',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400': user.user_type === 'static',
                                        }" class="inline-flex rounded-full px-3 py-1 text-xs font-semibold">
                                            {{ user.user_type }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900 dark:text-gray-300">
                                        {{ user.ip || 'N/A' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-gray-600 dark:text-gray-400">
                                        {{ user.mac || 'N/A' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                        {{ formatSessionTime(user.session_start) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Empty State -->
                        <div v-else class="py-12 text-center">
                            <Activity class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No active users</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ selectedType === 'all' ? 'No users are currently online' : `No ${selectedType} users are currently online` }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
