<script setup>
import { ref, onMounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import { useTheme } from '@/composables/useTheme';

// Icons
import {
    LayoutDashboard,
    Users,
    Banknote,
    MessageSquare,
    LogOut,
    Settings,
    Building2,
    SunIcon,
    FolderEdit,
} from 'lucide-vue-next';

const { theme, applyTheme } = useTheme();
const sidebarOpen = ref(false);

onMounted(() => {
    const savedTheme = localStorage.getItem('superadmin_theme') || 'light';
    theme.value = savedTheme;
    applyTheme(savedTheme);
});

watch(theme, (val) => {
    localStorage.setItem('superadmin_theme', val);
    applyTheme(val);
});
</script>

<template>
    <div
        class="min-h-auto flex w-full bg-gray-50 text-gray-900 transition-colors duration-300 dark:bg-gray-900 dark:text-white"
    >
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 w-64 flex-shrink-0 transform bg-white shadow-lg transition-transform duration-200 ease-in-out lg:relative lg:translate-x-0 dark:bg-gray-800"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div
                class="flex items-center justify-between border-b border-gray-200 px-4 py-4 dark:border-gray-700"
            >
                <Link
                    :href="route('superadmin.dashboard')"
                    class="flex items-center space-x-2"
                >
                    <ApplicationLogo class="h-8 w-auto" />
                    <span class="text-lg font-semibold">SuperAdmin</span>
                </Link>
                <button
                    @click="sidebarOpen = false"
                    class="text-gray-500 hover:text-gray-700 lg:hidden dark:text-gray-300"
                >
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Sidebar Links -->
            <nav class="h-[calc(100vh-4rem)] space-y-1 overflow-y-auto p-4">
                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('superadmin.dashboard')"
                        :active="route().current('superadmin.dashboard')"
                        class="flex items-center p-2"
                    >
                        <LayoutDashboard class="mr-2 h-4 w-4 text-blue-500" />
                        Dashboard
                    </NavLink>

                    <NavLink
                        :href="route('superadmin.users.index')"
                        :active="route().current('superadmin.users.index')"
                        class="flex items-center p-2"
                    >
                        <LayoutDashboard class="mr-2 h-4 w-4 text-blue-500" />
                        All Tenants
                    </NavLink>

                    <NavLink
                        :href="route('superadmin.payments.index')"
                        :active="route().current('superadmin.payments.index')"
                        class="flex items-center p-2"
                    >
                        <Banknote class="mr-2 h-4 w-4 text-blue-500" />
                        Payments
                    </NavLink>
                </div>
                
            </nav>
        </aside>

        <!-- Overlay (mobile) -->
        <div
            v-if="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 z-20 bg-black opacity-50 lg:hidden"
        ></div>

        <!-- Main Section -->
        <div
            class="flex min-h-screen flex-1 flex-col bg-gray-50 transition-colors duration-300 dark:bg-gray-900"
        >
            <!-- Top Navbar -->
            <nav
                class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="items-left ml-2 flex gap-4 font-extrabold">
                    <button
                        @click="sidebarOpen = true"
                        class="text-gray-600 focus:outline-none lg:hidden dark:text-gray-300"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>

                    {{ $page.props.auth.user.name }}
                </div>

                <div class="ml-2 flex">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-200"
                            >
                                <Settings
                                    class="ml-1 h-5 w-auto text-gray-400"
                                />
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink
                                @click="
                                    theme = theme === 'dark' ? 'light' : 'dark'
                                "
                                class="flex items-center rounded-full p-2 hover:bg-gray-100 dark:hover:bg-gray-700"
                            >
                                <SunIcon class="mr-2 h-4 w-4" />
                                Theme
                            </DropdownLink>

                            <DropdownLink
                                :href="route('profile.edit')"
                                class="flex"
                            >
                                <FolderEdit class="mr-2 h-4 w-4" />
                                Profile
                            </DropdownLink>

                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex items-center"
                            >
                                <LogOut class="mr-2 h-4 w-4 text-red-600" />
                                Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </nav>

            <!-- Header -->
            <header
                v-if="$slots.header"
                class="bg-white shadow transition-colors duration-300 dark:bg-gray-800"
            >
                <div
                    class="mx-auto max-w-7xl px-4 py-6 text-gray-900 sm:px-6 lg:px-8 dark:text-white"
                >
                    <slot name="header" />
                </div>
            </header>

            <!-- Main Content -->
            <main
                class="flex-1 bg-gray-50 p-4 transition-colors duration-300 dark:bg-gray-900"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
