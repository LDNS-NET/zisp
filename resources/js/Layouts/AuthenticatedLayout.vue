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
const collapsed = ref(false);

onMounted(() => {
    // Use the same localStorage key as the composable (ldns_theme)
    const savedTheme = localStorage.getItem('ldns_theme') || 'light';
    theme.value = savedTheme;
    applyTheme(savedTheme);

    // read persisted collapsed state (works after mount)
    const savedCollapsed = localStorage.getItem('ldns_sidebar_collapsed');
    if (savedCollapsed !== null) collapsed.value = savedCollapsed === 'true';
});

watch(theme, (val) => {
    localStorage.setItem('ldns_theme', val);
    applyTheme(val);
});

watch(collapsed, (val) => {
    localStorage.setItem('ldns_sidebar_collapsed', val);
});
</script>

<template>
    <!-- Wrapper -->
    <div :class="['min-h-screen flex w-full bg-white text-gray-900 transition-colors duration-300 dark:bg-slate-900 dark:text-gray-100', sidebarOpen ? 'overflow-hidden' : '']">
        <!-- Sidebar -->
        <aside
            :class="['fixed inset-y-0 left-0 z-40 flex-shrink-0 transform bg-white border-r border-gray-100 shadow-lg transition-all duration-200 ease-in-out lg:relative lg:translate-x-0 dark:bg-gray-800 dark:border-gray-700', collapsed ? 'w-16 sm:w-20 md:w-20' : 'w-64 sm:w-72 md:w-64', sidebarOpen ? 'translate-x-0' : '-translate-x-full']"
            role="navigation"
            aria-label="Main sidebar"
            :data-collapsed="collapsed"
            class="aside-root"
        >
            <!-- Mobile header inside aside: logo + close -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 lg:hidden">
                <button @click="sidebarOpen = false" class="p-2 rounded-md text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700" aria-label="Close sidebar">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Sidebar Links -->
            <nav class="h-[calc(100vh-4rem)] space-y-1 overflow-y-auto p-4">
                <div>
                    <!-- Collapse toggle: visible on lg and up, and also usable on smaller screens -->
                    <button
                        @click="collapsed = !collapsed"
                        :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        class="hidden lg:inline-flex text-gray-700 hover:bg-gray-100 rounded-md p-2 dark:text-gray-200 dark:hover:bg-gray-700"
                        aria-pressed="false"
                    >
                        <svg v-if="!collapsed" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14"></path></svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14"></path></svg>
                    </button>
                </div>
                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('dashboard')"
                        :active="route().current('dashboard')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <LayoutDashboard class="nav-icon h-4 w-4 text-blue-700 dark:text-blue-300" />
                        <span class="nav-label">Dashboard</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3 text-gray-500 uppercase font-semibold text-xs dark:text-gray-400">
                    Users
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('activeusers.index')"
                        :active="route().current('activeusers.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <Activity class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Active Users</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('users.index')"
                        :active="route().current('users.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <Users class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Users</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('leads.index')"
                        :active="route().current('leads.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <Phone class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Leads</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('tickets.index')"
                        :active="route().current('tickets.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <HelpCircle class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Tickets</span>
                    </NavLink>
                </div>
                <div class="mb-4 px-3 text-gray-500 uppercase font-semibold text-xs dark:text-gray-400">
                    Billing
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('packages.index')"
                        :active="route().current('packages.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <MailCheck class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Packages</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('vouchers.index')"
                        :active="route().current('vouchers.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <Gift class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Vouchers</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('payments.index')"
                        :active="route().current('payments.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <Banknote class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Payments</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('invoices.index')"
                        :active="route().current('invoices.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <DoorClosedLockedIcon class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Invoices</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3 text-gray-500 uppercase font-semibold text-xs dark:text-gray-400">
                    Communication
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('sms.index')"
                        :active="route().current('sms.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <MessageSquare class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">SMS</span>
                    </NavLink>
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('smstemplates.index')"
                        :active="route().current('smstemplates.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <Phone class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">SMS Templates</span>
                    </NavLink>
                </div>
                <div class="mb-4 px-3 text-gray-500 uppercase font-semibold text-xs dark:text-gray-400">
                    Network
                </div>

                <div class="mb-4 px-3">
                    <NavLink
                        :href="route('mikrotiks.index')"
                        :active="route().current('mikrotiks.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <NetworkIcon class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Mikrotiks</span>
                    </NavLink>
                </div>

                <!-- not sto be shown layout-->
                 <div class="mb-4 px-3">
                    <NavLink
                        :href="route('hotspot.index')"
                        :active="route().current('hotspot.index')"
                        class="flex items-center p-2 dark:text-white nav-link"
                    >
                        <Phone class="nav-icon h-4 w-4 text-purple-500" />
                        <span class="nav-label">Hotspot</span>
                    </NavLink>
                </div>
            </nav>
        </aside>

        <!-- Overlay (mobile) -->
        <div
            v-if="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-black/40 lg:hidden"
        ></div>

        <!-- Main Section -->
        <div class="flex flex-1 flex-col bg-gray-50 transition-colors duration-300 dark:bg-gray-900">
            <!-- Top Navbar -->
            <nav class="sticky top-0 z-40 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-slate-800">
                <div class="flex items-center gap-4">
                    <button
                        @click="sidebarOpen = true"
                        class="text-gray-900 focus:outline-none lg:hidden dark:text-white"
                        aria-label="Open sidebar"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                <div class="ml-2 flex items-center gap-3">
                    <!-- Quick theme toggle button -->
                    <button
                        type="button"
                        @click="setTheme(theme === 'dark' ? 'light' : 'dark')"
                        class="inline-flex items-center rounded-md p-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
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
                                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700"
                                aria-label="Open user menu"
                            >
                                <div class="h-8 w-8 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-700">
                                    <!-- simple avatar fallback -->
                                    <img v-if="$page.props.auth.user.avatar" :src="$page.props.auth.user.avatar" alt="avatar" class="h-full w-full object-cover" />
                                    <span v-else class="flex h-full w-full items-center justify-center text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $page.props.auth.user.name ? $page.props.auth.user.name.split(' ').map(s => s[0]).slice(0,2).join('') : 'U' }}</span>
                                </div>
                                <span class="hidden sm:inline text-sm font-medium text-gray-800 dark:text-white">{{ $page.props.auth.user.name }}</span>
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink @click.prevent="setTheme(theme === 'dark' ? 'light' : 'dark')" class="flex items-center gap-2 rounded-md p-2 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <SunIcon class="h-4 w-4" />
                                Theme
                            </DropdownLink>

                            <DropdownLink :href="route('profile.edit')" class="flex items-center gap-2">
                                <FolderEdit class="h-4 w-4" />
                                Profile
                            </DropdownLink>

                            <DropdownLink :href="route('settings.general.edit')" :active="route().current('settings.general.edit')" class="flex items-center gap-2">
                                <Settings class="h-4 w-4" />
                                Settings
                            </DropdownLink>

                            <DropdownLink href="#" :active="route().current('referal.index')" class="flex items-center gap-2">
                                <SendIcon class="h-4 w-4" />
                                Refer an isp
                            </DropdownLink>

                            <DropdownLink :href="route('logout')" method="post" as="button" class="flex items-center gap-2 text-red-600">
                                <LogOut class="h-4 w-4" />
                                Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </nav>

            <!-- Header -->
            <header v-if="$slots.header" class="border-b bg-cyan-50 transition-colors duration-300 dark:bg-cyan-900">
                <div class="mx-auto max-w-7xl px-4 py-3 text-black sm:px-6 lg:px-8 dark:text-white">
                    <slot name="header" />
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 flex justify-center p-6 transition-colors duration-300 dark:bg-slate-900">
                <div class="w-full max-w-7xl">
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 p-6">
                         <slot />
                     </div>
                 </div>
             </main>

            <!-- Footer -->
            <footer class="mt-auto border-t border-gray-200 bg-transparent px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-4">
                    <div class="text-center sm:text-left">© {{ new Date().getFullYear() }} <strong>ZiSP</strong>. All rights reserved.</div>
                    <div class="flex items-center gap-4">
                        <Link href="#" class="hover:text-gray-900 dark:hover:text-white">Help</Link>
                        <Link href="#" class="hover:text-gray-900 dark:hover:text-white">Privacy</Link>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>

<style scoped>
/* subtle improvements for avatar and transitions */
img[alt="avatar"] { display: block; }
button { transition: transform .06s ease, background-color .12s ease; }
button:active { transform: translateY(1px); }
/* make sure sidebar scroll region is comfortable */
aside nav { -webkit-overflow-scrolling: touch; }

/* tighten footer spacing on larger screens */
footer { --footer-padding-y: 0.5rem; padding-top: var(--footer-padding-y); padding-bottom: var(--footer-padding-y); }
@media (min-width: 1024px) {
  footer { padding-top: 0.4rem; padding-bottom: 0.4rem; }
}

/* nav link layout: show icon on the left and hide labels when collapsed */
.nav-link { display: flex; align-items: center; gap: .5rem; justify-content: flex-start; flex-direction: row; }
/* default: keep icon inline (no auto margin) */
.nav-link .nav-icon { margin-left: 0; flex: none; }
.nav-label { transition: opacity .15s ease, width .15s ease; white-space: nowrap; overflow: hidden; }

/* when aside is collapsed: hide labels but keep icons visible and centered */
aside[data-collapsed="true"] .nav-label {
  opacity: 0;
  width: 0;
  visibility: hidden;
  margin-right: 0;
  pointer-events: none;
}

/* center icons when collapsed and remove any offsets */
aside[data-collapsed="true"] .nav-link {
  justify-content: center;
  padding-left: .5rem;
  padding-right: .5rem;
  flex-direction: row; /* keep icon left for consistency */
}
aside[data-collapsed="true"] .nav-link .nav-icon,
aside[data-collapsed="true"] .nav-icon {
  margin-left: 0;
  display: inline-block;
}

/* ensure collapsed width doesn't cause overflow */
aside[data-collapsed="true"] { width: 4rem !important; }

/* small tweak: slightly larger icons when collapsed for readability */
aside[data-collapsed="true"] .nav-icon > * { width: 20px; height: 20px; }

</style>
