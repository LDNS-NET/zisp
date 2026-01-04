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
    LogOut,
    Settings,
    Sun,
    Moon,
    FolderEdit,
    Menu,
    X,
    Shield,
    BarChart,
    Bell,
    ChevronDown,
    ChevronRight,
} from 'lucide-vue-next';

const { theme, setTheme } = useTheme();
const showingNavigationDropdown = ref(false);
const sidebarOpen = ref(false);
const collapsed = ref(false);

// Persist collapsed state
onMounted(() => {
    const savedCollapsed = localStorage.getItem('ldns_superadmin_sidebar_collapsed');
    if (savedCollapsed !== null) {
        collapsed.value = savedCollapsed === 'true';
    }
});

watch(collapsed, (val) => {
    localStorage.setItem('ldns_superadmin_sidebar_collapsed', val);
});

const navigation = [
    { name: 'Dashboard', href: route('superadmin.dashboard'), icon: LayoutDashboard, active: 'superadmin.dashboard' },
    { name: 'All Tenants', href: route('superadmin.users.index'), icon: Users, active: 'superadmin.users.*' },
    { name: 'Payments', href: route('superadmin.payments.index'), icon: Banknote, active: 'superadmin.payments.*' },
    { name: 'Payment Gateways', href: route('superadmin.payment-gateways.index'), icon: Globe, active: 'superadmin.payment-gateways.*' },
    { name: 'Pricing Plans', href: route('superadmin.pricing-plans.index'), icon: Banknote, active: 'superadmin.pricing-plans.*' },
    { name: 'System Settings', href: route('superadmin.system-settings.index'), icon: Settings, active: 'superadmin.system-settings.*' },
    { name: 'SMS Gateways', href: route('superadmin.sms-gateways.index'), icon: MessageSquare, active: 'superadmin.sms-gateways.*' },
    { name: 'Admins', href: route('superadmin.admins.index'), icon: Shield, active: 'superadmin.admins.*' },
    { name: 'Analytics', href: route('superadmin.analytics.index'), icon: BarChart, active: 'superadmin.analytics.*' },
    { name: 'All Mikrotiks', href: route('superadmin.allmikrotiks.index'), icon: Network, active: 'superadmin.allmikrotiks.*' },
    { 
        name: 'Notifications', 
        icon: Bell, 
        active: ['superadmin.onboarding-requests.*', 'superadmin.domain-requests.*'],
        children: [
            { name: 'Onboarding', href: route('superadmin.onboarding-requests.index'), active: 'superadmin.onboarding-requests.*', badge: 'pending_onboarding_requests' },
            { name: 'Domain Requests', href: route('superadmin.domain-requests.index'), active: 'superadmin.domain-requests.*', badge: 'pending_domain_requests' },
        ]
    },
];

const openMenus = ref({});

const toggleMenu = (name) => {
    openMenus.value[name] = !openMenus.value[name];
};

const isMenuActive = (item) => {
    if (Array.isArray(item.active)) {
        return item.active.some(a => route().current(a));
    }
    return route().current(item.active);
};

// Initialize open menus based on active route
onMounted(() => {
    navigation.forEach(item => {
        if (item.children && isMenuActive(item)) {
            openMenus.value[item.name] = true;
        }
    });
});

const user = usePage().props.auth.user;

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
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100 dark:border-slate-800">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">
                        S
                    </div>
                    <span 
                        :class="['font-bold text-xl text-gray-900 dark:text-white transition-opacity duration-300', collapsed ? 'lg:opacity-0 lg:hidden' : 'opacity-100']"
                    >
                        SuperAdmin
                    </span>
                </div>
                <!-- Mobile Close Button -->
                <button @click="sidebarOpen = false" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400">
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
                    
                    <!-- Link with Children (Toggle) -->
                    <div v-else-if="item.children" class="space-y-1">
                        <button 
                            @click="toggleMenu(item.name)"
                            :class="[
                                'w-full group flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200',
                                isMenuActive(item)
                                    ? 'bg-blue-50/50 text-blue-700 dark:bg-blue-900/10 dark:text-blue-400' 
                                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                            ]"
                        >
                            <component 
                                :is="item.icon" 
                                :class="[
                                    'flex-shrink-0 w-5 h-5 transition-colors duration-200',
                                    isMenuActive(item) ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300',
                                    collapsed ? 'mx-auto' : 'mr-3'
                                ]" 
                            />
                            <span :class="['transition-all duration-300 whitespace-nowrap', collapsed ? 'lg:hidden' : 'block']">
                                {{ item.name }}
                            </span>
                            <ChevronDown 
                                v-if="!collapsed"
                                :class="['ml-auto w-4 h-4 transition-transform duration-200', openMenus[item.name] ? 'rotate-180' : '']"
                            />
                        </button>

                        <!-- Sub-items -->
                        <div v-if="openMenus[item.name] && !collapsed" class="pl-10 space-y-1">
                            <Link 
                                v-for="child in item.children" 
                                :key="child.name"
                                :href="child.href"
                                :class="[
                                    'flex items-center px-3 py-2 rounded-lg text-xs font-medium transition-all duration-200',
                                    route().current(child.active)
                                        ? 'text-blue-600 dark:text-blue-400 bg-blue-50/30 dark:bg-blue-900/10'
                                        : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-slate-800'
                                ]"
                            >
                                {{ child.name }}
                                <span 
                                    v-if="child.badge && $page.props.superadminCounts[child.badge] > 0"
                                    class="ml-auto inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white"
                                >
                                    {{ $page.props.superadminCounts[child.badge] }}
                                </span>
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Simple Link -->
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
                        
                        <!-- Badge -->
                        <span 
                            v-if="item.badge && $page.props.superadminCounts[item.badge] > 0"
                            :class="[
                                'ml-auto inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold',
                                route().current(item.active) ? 'bg-blue-600 text-white' : 'bg-red-500 text-white',
                                collapsed ? 'absolute top-1 right-1' : ''
                            ]"
                        >
                            {{ $page.props.superadminCounts[item.badge] }}
                        </span>
                    </Link>
                </template>
            </div>

            <!-- Sidebar Footer (Collapse Toggle) -->
            <div class="p-4 border-t border-gray-100 dark:border-slate-800 hidden lg:flex justify-end">
                <button 
                    @click="collapsed = !collapsed"
                    class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-800 dark:hover:text-gray-300 transition-colors"
                >
                    <component :is="collapsed ? ChevronRight : ChevronDown" class="w-5 h-5 transform rotate-90" />
                </button>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            
            <!-- Top Header -->
            <header class="h-16 bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 flex items-center justify-between px-4 sm:px-6 lg:px-8 z-30 sticky top-0">
                
                <!-- Left: Mobile Toggle -->
                <div class="flex items-center gap-4">
                    <button 
                        @click="toggleSidebar" 
                        class="lg:hidden p-2 -ml-2 text-gray-500 hover:bg-gray-100 rounded-lg dark:text-gray-400 dark:hover:bg-slate-800"
                    >
                        <Menu class="w-6 h-6" />
                    </button>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-3 sm:gap-4">
                    
                    <!-- Theme Toggle -->
                    <button 
                        @click="setTheme(theme === 'dark' ? 'light' : 'dark')"
                        class="p-2 text-gray-500 hover:bg-gray-100 rounded-full dark:text-gray-400 dark:hover:bg-slate-800 transition-colors"
                        :title="theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    >
                        <Sun v-if="theme === 'dark'" class="w-5 h-5" />
                        <Moon v-else class="w-5 h-5" />
                    </button>

                    <!-- User Dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-3 pl-3 pr-1 py-1.5 rounded-full hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-slate-700">
                                <div class="text-right hidden sm:block">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white leading-none mb-1">{{ user.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 leading-none">{{ user.email }}</div>
                                </div>
                                <div class="h-9 w-9 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-700 dark:text-blue-300 font-bold text-sm border border-blue-200 dark:border-blue-800">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                                <ChevronDown class="w-4 h-4 text-gray-400" />
                            </button>
                        </template>

                        <template #content>
                            <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 sm:hidden">
                                <div class="font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                            </div>

                            <DropdownLink :href="route('profile.edit')" class="flex items-center gap-2">
                                <FolderEdit class="w-4 h-4" /> Profile
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
