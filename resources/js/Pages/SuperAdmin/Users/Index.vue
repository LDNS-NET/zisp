<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { Search, Filter, MoreVertical, Trash2, Eye, Ban, CheckCircle, AlertCircle } from 'lucide-vue-next';
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    users: Object,
    filters: Object,
    countries: Array,
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const country = ref(props.filters.country || '');

// Debounced search
const updateSearch = debounce((value) => {
    router.get(
        route('superadmin.users.index'),
        { search: value, status: status.value, country: country.value },
        { preserveState: true, replace: true }
    );
}, 300);

// Watch filters
watch([status, country], () => {
    router.get(
        route('superadmin.users.index'),
        { search: search.value, status: status.value, country: country.value },
        { preserveState: true, replace: true }
    );
});

const confirmDelete = (userId) => {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        router.delete(route('superadmin.users.destroy', userId));
    }
};

const toggleSuspension = (user) => {
    const action = user.is_suspended ? 'unsuspend' : 'suspend';
    const message = user.is_suspended 
        ? 'Are you sure you want to activate this user?' 
        : 'Are you sure you want to suspend this user? They will lose access to the system.';
    
    if (confirm(message)) {
        router.post(route(`superadmin.users.${action}`, user.id));
    }
};
</script>

<template>
    <Head title="Manage Tenants" />

    <SuperAdminLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                    Manage Tenants
                </h2>
                <div class="text-sm text-gray-500">
                    {{ users.total }} total tenants
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filters Bar -->
            <div class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <!-- Search -->
                <div class="relative flex-1 max-w-md">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <Search class="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                        v-model="search"
                        @input="updateSearch($event.target.value)"
                        type="text"
                        placeholder="Search by name, email, phone..."
                        class="block w-full rounded-lg border-0 py-2.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    />
                </div>

                <!-- Filters -->
                <div class="flex gap-3">
                    <select
                        v-model="status"
                        class="block rounded-lg border-0 py-2.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>

                    <select
                        v-model="country"
                        class="block rounded-lg border-0 py-2.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-gray-900 dark:text-white dark:ring-gray-700 sm:text-sm sm:leading-6"
                    >
                        <option value="">All Countries</option>
                        <option v-for="c in countries" :key="c.country_code" :value="c.country_code">
                            {{ c.country }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tenant</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Contact</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Location</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Joined</th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold dark:bg-indigo-900 dark:text-indigo-300">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                                            <div class="text-sm text-gray-500">{{ user.tenant_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white">{{ user.email }}</div>
                                    <div class="text-sm text-gray-500">{{ user.phone }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="user.country_code" class="mr-2 text-lg">{{ user.country_code === 'KE' ? '🇰🇪' : (user.country_code === 'GH' ? '🇬🇭' : (user.country_code === 'NG' ? '🇳🇬' : '🌍')) }}</span>
                                        {{ user.country || 'Unknown' }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        user.is_suspended 
                                            ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' 
                                            : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                    ]">
                                        {{ user.is_suspended ? 'Suspended' : 'Active' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ new Date(user.created_at).toLocaleDateString() }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <Menu as="div" class="relative inline-block text-left">
                                        <MenuButton class="flex items-center rounded-full p-2 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:hover:text-gray-300">
                                            <MoreVertical class="h-5 w-5" />
                                        </MenuButton>

                                        <transition
                                            enter-active-class="transition ease-out duration-100"
                                            enter-from-class="transform opacity-0 scale-95"
                                            enter-to-class="transform opacity-100 scale-100"
                                            leave-active-class="transition ease-in duration-75"
                                            leave-from-class="transform opacity-100 scale-100"
                                            leave-to-class="transform opacity-0 scale-95"
                                        >
                                            <MenuItems class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:ring-gray-700">
                                                <MenuItem v-slot="{ active }">
                                                    <Link
                                                        :href="route('superadmin.users.show', user.id)"
                                                        :class="[active ? 'bg-gray-100 dark:bg-gray-700' : '', 'flex w-full items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200']"
                                                    >
                                                        <Eye class="mr-3 h-4 w-4 text-gray-400" />
                                                        View Details
                                                    </Link>
                                                </MenuItem>
                                                <MenuItem v-slot="{ active }">
                                                    <button
                                                        @click="toggleSuspension(user)"
                                                        :class="[active ? 'bg-gray-100 dark:bg-gray-700' : '', 'flex w-full items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200']"
                                                    >
                                                        <component :is="user.is_suspended ? CheckCircle : Ban" class="mr-3 h-4 w-4 text-gray-400" />
                                                        {{ user.is_suspended ? 'Activate User' : 'Suspend User' }}
                                                    </button>
                                                </MenuItem>
                                                <MenuItem v-slot="{ active }">
                                                    <button
                                                        @click="confirmDelete(user.id)"
                                                        :class="[active ? 'bg-gray-100 dark:bg-gray-700' : '', 'flex w-full items-center px-4 py-2 text-sm text-red-600 dark:text-red-400']"
                                                    >
                                                        <Trash2 class="mr-3 h-4 w-4" />
                                                        Delete User
                                                    </button>
                                                </MenuItem>
                                            </MenuItems>
                                        </transition>
                                    </Menu>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <AlertCircle class="h-12 w-12 mb-3 opacity-20" />
                                        <p class="text-lg font-medium">No tenants found</p>
                                        <p class="text-sm">Try adjusting your search or filters</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="users.links.length > 3" class="border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <Link :href="users.prev_page_url" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</Link>
                        <Link :href="users.next_page_url" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</Link>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing <span class="font-medium">{{ users.from }}</span> to <span class="font-medium">{{ users.to }}</span> of <span class="font-medium">{{ users.total }}</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <Link 
                                    v-for="(link, i) in users.links" 
                                    :key="i"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 focus:z-20 focus:outline-offset-0',
                                        link.active 
                                            ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' 
                                            : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-gray-700',
                                        i === 0 ? 'rounded-l-md' : '',
                                        i === users.links.length - 1 ? 'rounded-r-md' : '',
                                        !link.url ? 'pointer-events-none opacity-50' : ''
                                    ]"
                                />
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
