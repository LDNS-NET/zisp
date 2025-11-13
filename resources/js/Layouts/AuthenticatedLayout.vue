<script setup>
import { ref, onMounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import { useTheme } from '@/composables/useTheme';
import {
    LayoutDashboard,
    Users,
    Banknote,
    MessageSquare,
    LogOut,
    Settings,
    SunIcon,
    // Moon icon for theme toggle
    Moon,
    FolderEdit,
    AlertCircleIcon,
    ReceiptCent,
    Link2Icon,
    MailCheck,
    Activity,
    Phone,
    HelpCircle,
    NetworkIcon,
    DoorClosedLockedIcon,
    Gift,
    SendIcon,
} from 'lucide-vue-next';

const { theme, applyTheme, setTheme } = useTheme();
const sidebarOpen = ref(false);

onMounted(() => {
    // Use the same localStorage key as the composable (ldns_theme)
    const savedTheme = localStorage.getItem('ldns_theme') || 'light';
    theme.value = savedTheme;
    applyTheme(savedTheme);
});

watch(theme, (val) => {
    localStorage.setItem('ldns_theme', val);
    applyTheme(val);
});
</script>

<template>
    <!-- Wrapper -->
    <div
        class="min-h-auto flex w-full bg-gray-50 text-black transition-colors duration-300 dark:bg-gray-900 dark:text-white"
    >
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-30 w-64 flex-shrink-0 transform bg-gray-200 shadow-lg transition-transform duration-200 ease-in-out lg:relative lg:translate-x-0 dark:bg-gray-800"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- <div
                class="flex items-center justify-between border-b border-gray-200 px-4 py-4 dark:border-gray-700"
            >
                <Link
                    :href="route('dashboard')"
                    class="flex items-center space-x-2"
                >
                   <ApplicationLogo class="h-8 w-auto" />
                    <span
                        class="text-lg font-semibold text-black dark:text-white"
                        >ZiSP</span
                    >
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
            </div>-->

            <!-- Sidebar Links -->
            <nav class="h-[calc(100vh-4rem)] space-y-1 overflow-y-auto p-4">
                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('dashboard')"
                        :active="route().current('dashboard')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <LayoutDashboard
                            class="mr-2 h-4 w-4 text-blue-700 dark:text-blue-300"
                        />
                        Dashboard
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('activeusers.index')"
                        :active="route().current('activeusers.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <Activity class="mr-2 h-4 w-4 text-purple-500" />
                        Active Users
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('users.index')"
                        :active="route().current('users.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <Users class="mr-2 h-4 w-4 text-purple-500" />
                        Users
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('leads.index')"
                        :active="route().current('leads.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <Phone class="mr-2 h-4 w-4 text-purple-500" />
                        Leads
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('tickets.index')"
                        :active="route().current('tickets.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <HelpCircle class="mr-2 h-4 w-4 text-purple-500" />
                        Tickets
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('packages.index')"
                        :active="route().current('packages.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <MailCheck class="mr-2 h-4 w-4 text-purple-500" />
                        Packages
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('vouchers.index')"
                        :active="route().current('vouchers.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <Gift class="mr-2 h-4 w-4 text-purple-500" />
                        Vouchers
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('payments.index')"
                        :active="route().current('payments.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <Banknote class="mr-2 h-4 w-4 text-purple-500" />
                        Payments
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('invoices.index')"
                        :active="route().current('invoices.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <DoorClosedLockedIcon
                            class="mr-2 h-4 w-4 text-purple-500"
                        />
                        Invoices
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('sms.index')"
                        :active="route().current('sms.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <MessageSquare class="mr-2 h-4 w-4 text-purple-500" />
                        SMS
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('smstemplates.index')"
                        :active="route().current('smstemplates.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <Phone class="mr-2 h-4 w-4 text-purple-500" />
                        SMS Templates
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('mikrotiks.index')"
                        :active="route().current('mikrotiks.index')"
                        class="flex items-center p-2 dark:text-white"
                    >
                        <NetworkIcon class="mr-2 h-4 w-4 text-purple-500" />
                        Mikrotiks
                    </NavLink>
                </div>

                <div class="m-4 py-52 align-bottom">
                    <h2 class="flex">
                        <AlertCircleIcon class="mr-5 h-7 w-auto text-red-500" />
                        <span>Coming soon</span>
                    </h2>

                    <div
                        class="m-2 rounded-xl border bg-blue-400 py-2 text-black dark:bg-blue-700"
                    >
                        <div class="mb-2 px-2">
                            <NavLink
                                href="#"
                                :active="route().current('invoices.index')"
                                class="flex items-center p-2 dark:text-white"
                            >
                                <ReceiptCent
                                    class="mr-2 h-4 w-4 text-blue-500"
                                />
                                Emails
                            </NavLink>
                        </div>

                        <div class="mb-2 px-2">
                            <NavLink
                                href="#"
                                :active="route().current('Tportal.index')"
                                class="flex items-center p-2 dark:text-white"
                            >
                                <Link2Icon
                                    class="mr-2 h-4 w-4 text-green-500"
                                />
                                Tenant portal
                            </NavLink>
                        </div>

                        <div class="mb-2 px-2">
                            <NavLink
                                href="#"
                                :active="route().current('Tportal.index')"
                                class="flex items-center p-2 dark:text-white"
                            >
                                <NetworkIcon
                                    class="mr-2 h-4 w-4 text-green-500"
                                />
                                Mikrotik Integration
                            </NavLink>
                        </div>
                    </div>
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
                class="flex items-center justify-between border-b border-gray-200 bg-gray-300 px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-700"
            >
                <div class="items-left ml-2 flex gap-4 font-extrabold">
                    <button
                        @click="sidebarOpen = true"
                        class="text-gray-900 focus:outline-none lg:hidden dark:text-white"
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
                <div class="ml-2 flex items-center gap-2">
                    <!-- Quick theme toggle button -->
                    <button
                        type="button"
                        @click="setTheme(theme === 'dark' ? 'light' : 'dark')"
                        class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-200"
                        title="Toggle theme"
                    >
                        <template v-if="theme === 'dark'">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>
                        </template>
                        <template v-else>
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="5"></circle></svg>
                        </template>
                    </button>

                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-200"
                            >
                                <Settings
                                    class="ml-1 h-5 w-auto text-gray-900 dark:text-white"
                                />
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink
                                @click.prevent="setTheme(theme === 'dark' ? 'light' : 'dark')"
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
                                :href="route('settings.general.edit')"
                                :active="
                                    route().current('settings.general.edit')
                                "
                                class="flex"
                            >
                                <Settings class="mr-2 h-4 w-4" />
                                Settings
                            </DropdownLink>

                            <DropdownLink
                                href="#"
                                :active="route().current('referal.index')"
                                class="flex"
                            >
                                <SendIcon class="mr-2 h-4 w-4" />
                                Refer an isp
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
                class="border bg-cyan-100 transition-colors duration-300 dark:bg-cyan-900"
            >
                <div
                    class="mx-auto max-w-7xl px-2 py-2 text-black sm:px-4 lg:px-6 dark:text-white"
                >
                    <slot name="header" />
                </div>
            </header>

            <!-- Main Content -->
            <main
                class="flex justify-center rounded-xl p-4 transition-colors duration-300 dark:bg-gray-900"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
