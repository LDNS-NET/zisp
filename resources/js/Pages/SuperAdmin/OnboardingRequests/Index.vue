<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { 
    Mail, 
    User, 
    Globe, 
    Building2, 
    MessageSquare, 
    Clock,
    CheckCircle2,
    XCircle
} from 'lucide-vue-next';

defineProps({
    requests: Object,
});

defineOptions({
    layout: SuperAdminLayout,
});

const getStatusColor = (status) => {
    switch (status) {
        case 'pending': return 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400';
        case 'contacted': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400';
        case 'closed': return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
        default: return 'bg-gray-100 text-gray-700';
    }
};
</script>

<template>
    <Head title="Onboarding Requests" />

    <template #header>
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-white">
                Onboarding Requests
            </h2>
        </div>
    </template>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white dark:bg-slate-900 shadow-sm sm:rounded-lg border border-gray-200 dark:border-slate-800">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div v-if="requests.data.length === 0" class="text-center py-12">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-800 mb-4">
                            <Mail class="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">No requests found</h3>
                        <p class="text-gray-500 dark:text-gray-400">There are no onboarding requests at the moment.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-slate-800">
                                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">User / ISP</th>
                                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Contact</th>
                                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Country</th>
                                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                <tr v-for="request in requests.data" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                                <User class="w-5 h-5" />
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-white">{{ request.name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                                    <Building2 class="w-3 h-3" /> {{ request.isp_name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2">
                                            <Mail class="w-4 h-4 text-gray-400" />
                                            {{ request.email }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2">
                                            <Globe class="w-4 h-4 text-gray-400" />
                                            {{ request.country }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span :class="['px-2.5 py-1 rounded-full text-xs font-medium capitalize', getStatusColor(request.status)]">
                                            {{ request.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                            <Clock class="w-4 h-4" />
                                            {{ new Date(request.created_at).toLocaleDateString() }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors" title="View Message">
                                                <MessageSquare class="w-5 h-5" />
                                            </button>
                                            <button class="p-2 text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors" title="Mark as Contacted">
                                                <CheckCircle2 class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="requests.links.length > 3" class="mt-6 flex justify-center">
                        <nav class="flex gap-1">
                            <template v-for="(link, k) in requests.links" :key="k">
                                <div v-if="link.url === null" class="px-4 py-2 text-sm text-gray-400 border border-gray-200 dark:border-slate-800 rounded-lg" v-html="link.label"></div>
                                <Link v-else :href="link.url" class="px-4 py-2 text-sm border border-gray-200 dark:border-slate-800 rounded-lg transition-colors" :class="{'bg-blue-600 text-white border-blue-600': link.active, 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-800': !link.active}" v-html="link.label"></Link>
                            </template>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
