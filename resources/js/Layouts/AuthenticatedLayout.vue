<script setup>
import { ref, onMounted, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Toast from '@/Components/Toast.vue';
import { useTheme } from '@/composables/useTheme';
import {
    LayoutDashboard,
    Users,
    Banknote,
    MessageSquare,
    LogOut,
    Settings,
    Sun,
    Moon,
    FolderEdit,
    Menu,
    X,
    ChevronRight,
    HelpCircle,
    Network,
    Gift,
    FileText,
    Smartphone,
    Layers,
    Activity,
    ChevronLeft,
    Globe,
    Bell,
    Shield,
    BarChart3
} from 'lucide-vue-next';

const { theme, setTheme } = useTheme();
const showingNavigationDropdown = ref(false);
const sidebarOpen = ref(false);
const collapsed = ref(false);

// Persist collapsed state
onMounted(() => {
    const savedCollapsed = localStorage.getItem('ldns_sidebar_collapsed');
    if (savedCollapsed !== null) {
        collapsed.value = savedCollapsed === 'true';
    }
});

watch(collapsed, (val) => {
    localStorage.setItem('ldns_sidebar_collapsed', val);
});

const navigation = [
    { name: 'Dashboard', href: route('dashboard'), icon: LayoutDashboard, active: 'dashboard' },
    
    { header: 'Analytics' },
    { name: 'Traffic Analytics', href: route('analytics.traffic'), icon: BarChart3, active: 'analytics.*' },
    
    { header: 'User Management' },
    { name: 'Online Users', href: route('activeusers.index'), icon: Activity, active: 'activeusers.*', countKey: 'online_users' },
    { name: 'All Users', href: route('users.index'), icon: Users, active: 'users.*', countKey: 'all_users' },
    { name: 'Leads', href: route('leads.index'), icon: Layers, active: 'leads.*', countKey: 'leads' },
    { name: 'Tickets', href: route('tickets.index'), icon: HelpCircle, active: 'tickets.*', countKey: 'tickets' },
    
    { header: 'Billing & Finance' },
    { name: 'Packages', href: route('packages.index'), icon: Layers, active: 'packages.*', countKey: 'packages' },
    { name: 'Vouchers', href: route('vouchers.index'), icon: Gift, active: 'vouchers.*', countKey: 'vouchers' },
    { name: 'Payments', href: route('payments.index'), icon: Banknote, active: 'payments.*' },
    { name: 'Invoices', href: route('invoices.index'), icon: FileText, active: 'invoices.*', countKey: 'invoices' },

    { header: 'Network Management' },
    { name: 'Mikrotiks', href: route('mikrotiks.index'), icon: Network, active: 'mikrotiks.*', countKey: 'mikrotiks' },
    /*{ name: 'Hotspot', href: route('hotspot.index'), icon: Wifi, active: 'hotspot.*' },*/
    
    { header: 'Communication' },
    { name: 'SMS', href: route('sms.index'), icon: MessageSquare, active: 'sms.*' },
    { name: 'Templates', href: route('smstemplates.index'), icon: Smartphone, active: 'smstemplates.*' },
];

const page = usePage();
const user = page.props.auth.user;
const tenantLogo = page.props.tenant?.logo;

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}
</script>

<template>
    <div class="h-screen overflow-hidden bg-gray-50 dark:bg-slate-950 flex transition-colors duration-300">
        
        <!-- Mobile Sidebar Overlay -->
        <div 
            v-if="sidebarOpen" 
            class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm lg:hidden transition-opacity"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside 
            :class="[
                'fixed lg:static inset-y-0 left-0 z-50 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 transition-all duration-300 ease-in-out flex flex-col',
                collapsed ? 'lg:w-20' : 'lg:w-72',
                sidebarOpen ? 'translate-x-0 w-72' : '-translate-x-full lg:translate-x-0'
            ]"
        >
            <!-- Logo Area -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-gray-100 dark:border-slate-800">
                <!-- Tenant Logo -->
                <div v-if="tenantLogo && !collapsed" class="flex-shrink-0 hidden lg:block">
                    <img 
                        :src="tenantLogo" 
                        alt="Tenant Logo" 
                        class="h-12 w-12 object-contain rounded-lg transition-all duration-300"
                    />
                </div>

                <!-- Mobile Logo -->
                <div v-if="tenantLogo" class="flex-shrink-0 lg:hidden">
                    <img 
                        :src="tenantLogo" 
                        alt="Tenant Logo" 
                        class="h-12 w-12 object-contain rounded-lg transition-all duration-300"
                    />
                </div>

                <!-- Collapse Button -->
                <button 
                    @click="collapsed = !collapsed"
                    class="hidden lg:flex p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-800 dark:hover:text-gray-300 transition-colors flex-shrink-0 ml-auto"
                >
                    <component :is="collapsed ? ChevronRight : ChevronLeft" class="w-5 h-5" />
                </button>

                <!-- Mobile Close Button -->
                <button @click="sidebarOpen = false" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 ml-auto">
                    <X class="w-6 h-6" />
                </button>
            </div>

            <!-- Navigation -->
            <div class="flex-1 overflow-y-auto py-4 px-3 space-y-1 custom-scrollbar">
                <template v-for="(item, index) in navigation" :key="index">
                    <!-- Section Header -->
                    <div 
                        v-if="item.header" 
                        :class="['mt-6 mb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider transition-all duration-300', collapsed ? 'lg:hidden' : 'block']"
                    >
                        {{ item.header }}
                    </div>
                    
                    <!-- Link -->
                    <Link 
                        v-else
                        :href="item.href"
                        :class="[
                            'group flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200',
                            route().current(item.active) 
                                ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400' 
                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                        ]"
                        :title="collapsed ? item.name : ''"
                    >
                        <component 
                            :is="item.icon" 
                            :class="[
                                'flex-shrink-0 w-5 h-5 transition-colors duration-200',
                                route().current(item.active) ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300',
                                collapsed ? 'mx-auto' : 'mr-3'
                            ]" 
                        />
                        <span :class="['transition-all duration-300 whitespace-nowrap', collapsed ? 'lg:hidden' : 'block']">
                            {{ item.name }}
                        </span>
                        
                        <!-- Count Badge -->
                        <span 
                            v-if="item.countKey && $page.props.sidebarCounts && $page.props.sidebarCounts[item.countKey] > 0 && !collapsed" 
                            class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300"
                        >
                            {{ $page.props.sidebarCounts[item.countKey] }}
                        </span>
                    </Link>
                </template>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0">
            
            <!-- Top Header -->
            <div v-if="$page.props.auth.impersonated_by" class="bg-orange-600 text-white px-4 py-2 flex items-center justify-between z-50">
                <div class="flex items-center gap-2">
                    <Shield class="w-4 h-4" />
                    <span class="text-sm font-medium">
                        You are currently impersonating <strong>{{ user.name }}</strong>
                    </span>
                </div>
                <Link 
                    :href="route('impersonate.leave')" 
                    method="post" 
                    as="button"
                    class="text-xs font-bold uppercase tracking-wider bg-white text-orange-600 px-3 py-1 rounded hover:bg-orange-50 transition-colors"
                >
                    Leave Impersonation
                </Link>
            </div>
            <header class="h-16 bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 flex items-center justify-between px-4 sm:px-6 lg:px-8 z-30 sticky top-0">
                
                <!-- Left: Mobile Toggle -->
                <div class="flex items-center gap-4">
                    <button 
                        @click="toggleSidebar" 
                        class="lg:hidden p-2 -ml-2 text-gray-500 hover:bg-gray-100 rounded-lg dark:text-gray-400 dark:hover:bg-slate-800"
                    >
                        <Menu class="w-6 h-6" />
                    </button>
                    <span 
                        :class="['font-bold text-xl text-gray-900 dark:text-white transition-opacity duration-300', collapsed ? 'lg:opacity-0 lg:hidden' : 'opacity-100']"
                    >
                        {{ user.name }}
                    </span>
                </div>

                

                <!-- Right: Actions -->
                <div class="flex items-center gap-3 sm:gap-4">
                    <!-- Notifications -->
                    <Link 
                        :href="route('domain-requests.index')" 
                        class="relative p-2 text-gray-500 hover:bg-gray-100 rounded-full dark:text-gray-400 dark:hover:bg-slate-800 transition-colors"
                        title="Notifications"
                    >
                        <Bell class="w-5 h-5" />
                        <span 
                            v-if="user.unread_notifications_count > 0"
                            class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white ring-2 ring-white dark:ring-slate-900"
                        >
                            {{ user.unread_notifications_count }}
                        </span>
                    </Link>

                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-3 pl-3 pr-1 py-1.5 rounded-full hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-slate-700">
                                <div class="h-9 w-9 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-700 dark:text-blue-300 font-bold text-sm border border-blue-200 dark:border-blue-800">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                            </button>
                        </template>

                        <template #content>
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 sm:hidden">
                                <div class="font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                            </div>
                            <!-- Theme Toggle -->
                                <button 
                                    @click="setTheme(theme === 'dark' ? 'light' : 'dark')"
                                    class="p-2 text-gray-500 hover:bg-gray-100 rounded-full dark:text-gray-400 dark:hover:bg-slate-800 transition-colors"
                                    :title="theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                                >
                                    <Sun v-if="theme === 'dark'" class="w-5 h-5" />
                                    <Moon v-else class="w-5 h-5" />
                                </button>
                            <DropdownLink :href="route('profile.edit')" class="flex items-center gap-2">
                                <FolderEdit class="w-4 h-4" /> Profile
                            </DropdownLink>
                            <DropdownLink :href="route('settings.general.edit')" class="flex items-center gap-2">
                                <Settings class="w-4 h-4" /> Settings
                            </DropdownLink>
                            <DropdownLink :href="route('domain-requests.index')" class="flex items-center gap-2">
                                <Globe class="w-4 h-4" /> Domain Settings
                            </DropdownLink>
                            <div class="border-t border-gray-100 dark:border-slate-700 my-1"></div>
                            <DropdownLink :href="route('logout')" method="post" as="button" class="flex items-center gap-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                <LogOut class="w-4 h-4" /> Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Page Header (Title & Actions) -->
            <div v-if="$slots.header" class="bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800">
                <div class="px-4 py-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </div>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 dark:bg-slate-950 p-4 sm:p-6 lg:p-8">
                <div class="max-w-7xl mx-auto">
                    <slot />
                </div>
            </main>
        </div>
    </div>
    <Toast />
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.3);
    border-radius: 20px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background-color: rgba(156, 163, 175, 0.5);
}
</style>
