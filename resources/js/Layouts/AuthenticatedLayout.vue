<script setup>
import { ref, onMounted, watch, computed } from 'vue';
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
    BarChart3,
    BrainCircuit,
    UserCog,
    Lock,
    ChevronDown,
    Wrench,
    Radio
} from 'lucide-vue-next';

const { theme, setTheme } = useTheme();
const showingNavigationDropdown = ref(false);
const sidebarOpen = ref(false);
const collapsed = ref(false);

// State for open groups in sidebar
const openGroups = ref({});

// Persist collapsed state
onMounted(() => {
    const savedCollapsed = localStorage.getItem('ldns_sidebar_collapsed');
    if (savedCollapsed !== null) {
        collapsed.value = savedCollapsed === 'true';
    }
    
    // Auto-open groups based on current route
    navigation.forEach(group => {
        if (group.children) {
            const hasActiveChild = group.children.some(child => route().current(child.active));
            if (hasActiveChild) {
                openGroups.value[group.name] = true;
            }
        }
    });
});

watch(collapsed, (val) => {
    localStorage.setItem('ldns_sidebar_collapsed', val);
    if (val) {
        // Close all groups when collapsing sidebar for cleaner UI
        openGroups.value = {};
    }
});

const toggleGroup = (groupName) => {
    if (collapsed.value) {
        collapsed.value = false; // Auto expand sidebar if clicking a group
        setTimeout(() => {
            openGroups.value[groupName] = !openGroups.value[groupName];
        }, 150);
    } else {
        openGroups.value[groupName] = !openGroups.value[groupName];
    }
};

const navigation = [
    { name: 'Dashboard', href: route('dashboard'), icon: LayoutDashboard, active: 'dashboard' },
    { name: 'Analytics', icon: BarChart3, children: [
        { name: 'Traffic Analytics', href: route('analytics.traffic'), active: 'analytics.traffic', roles: ['tenant_admin', 'admin', 'network_engineer', 'technical'], permission: 'view_traffic_analytics' },
        { name: 'Network Topology', href: route('analytics.topology'), active: 'analytics.topology', roles: ['tenant_admin', 'network_engineer', 'technical'], permission: 'view_topology' },
        { name: 'Predictive Insights', href: route('analytics.predictions'), active: 'analytics.predictions', roles: ['tenant_admin', 'admin', 'network_engineer'], permission: 'view_predictions' },
        { name: 'Financial Intelligence', href: route('analytics.finance'), active: 'analytics.finance', roles: ['tenant_admin'], permission: 'view_finance' },
        { name: 'Report Builder', href: route('analytics.reports.index'), active: 'analytics.reports.*', roles: ['tenant_admin'], permission: 'view_reports' },
    ]},
    { name: 'User Management', icon: Users, children: [
        { name: 'Online Users', href: route('activeusers.index'), active: 'activeusers.*', countKey: 'online_users', roles: ['tenant_admin', 'admin', 'customer_care', 'technical'], permission: 'view_online_users' },
        { name: 'All Users', href: route('users.index'), active: 'users.*', countKey: 'all_users', roles: ['tenant_admin', 'admin', 'customer_care', 'technical'], permission: 'view_users' },
        { name: 'My Leads', href: route('leads.index'), active: 'leads.*', countKey: 'leads', roles: ['tenant_admin', 'admin', 'marketing'], permission: 'view_leads' },
    ]},
    { name: 'Finance', icon: Banknote, children: [
        { name: 'Packages', href: route('packages.index'), active: 'packages.*', countKey: 'packages', roles: ['tenant_admin', 'admin', 'marketing'], permission: 'view_packages' },
        { name: 'Vouchers', href: route('vouchers.index'), active: 'vouchers.*', countKey: 'vouchers', roles: ['tenant_admin', 'admin', 'marketing', 'customer_care'], permission: 'view_vouchers' },
        { name: 'Payments', href: route('payments.index'), active: 'payments.*', roles: ['tenant_admin'], permission: 'view_payments' },
        { name: 'Invoices', href: route('invoices.index'), active: 'invoices.*', countKey: 'invoices', roles: ['tenant_admin', 'admin', 'customer_care'], permission: 'view_invoices' },
    ]},
    { name: 'Network', icon: Network, children: [
        { name: 'Mikrotiks', href: route('mikrotiks.index'), active: 'mikrotiks.*', countKey: 'mikrotiks', roles: ['tenant_admin', 'network_engineer', 'technical', 'network_admin'], permission: 'view_routers' },
        { name: 'Equipment', href: route('equipment.index'), active: 'equipment.*', roles: ['tenant_admin', 'admin', 'network_engineer', 'technical'], permission: 'view_equipment' },
        { name: 'Content Filter', href: route('settings.content-filter.index'), active: 'settings.content-filter.*', roles: ['tenant_admin', 'network_engineer', 'network_admin'], permission: 'manage_filters' },
    ]},
    { name: 'Field Ops', icon: Wrench, children: [
        { name: 'My Installations', href: route('tenant.installations.my-installations'), active: 'tenant.installations.my-installations', roles: ['technical', 'technician'], permission: 'view_installations' },
        { name: 'Dispatch Board', href: route('tenant.installations.index'), active: 'tenant.installations.index', roles: ['tenant_admin', 'admin', 'network_engineer', 'technical', 'technician'], permission: 'view_installations' },
    ]},
    { name: 'Support', icon: MessageSquare, children: [
        { name: 'SMS', href: route('sms.index'), active: 'sms.*', roles: ['tenant_admin', 'admin', 'marketing', 'customer_care'], permission: 'view_sms' },
        { name: 'Templates', href: route('smstemplates.index'), active: 'smstemplates.*', roles: ['tenant_admin', 'admin', 'marketing', 'customer_care'], permission: 'view_templates' },
        { name: 'Tickets', href: route('tickets.index'), active: 'tickets.*', countKey: 'tickets', roles: ['tenant_admin', 'admin', 'customer_care', 'technical'], permission: 'view_tickets' },
    ]},
    { name: 'System', icon: Settings, children: [
        { name: 'Team', href: route('settings.staff.index'), active: 'settings.staff.*', roles: ['tenant_admin'], permission: 'manage_staff' },
    ]}
];

const page = usePage();
const user = page.props.auth.user;
const tenantLogo = page.props.tenant?.logo;

// Helper to check access for a single item
const hasAccess = (item) => {
    if (!item.roles && !item.permission) return true;
    const hasRole = item.roles ? item.roles.some((role) => user.roles.includes(role)) : false;
    const hasPermission = item.permission ? user.permissions.includes(item.permission) : false;
    return hasRole || hasPermission;
};

const filteredNavigation = computed(() => {
    return navigation.reduce((acc, item) => {
        // Single link
        if (!item.children) {
            if (hasAccess(item)) acc.push(item);
            return acc;
        }
        
        // Group: Check if at least one child is accessible
        const accessibleChildren = item.children.filter(child => hasAccess(child));
        if (accessibleChildren.length > 0) {
            acc.push({ ...item, children: accessibleChildren });
        }
        return acc;
    }, []);
});

const settingsRoutes = [
    { name: 'settings.general.edit', roles: ['tenant_admin'] },
    { name: 'settings.hotspot.edit', roles: ['tenant_admin', 'admin', 'network_engineer'], permission: 'manage_hotspot' },
    { name: 'settings.sms.edit', roles: ['tenant_admin', 'admin', 'marketing'], permission: 'manage_sms' },
    { name: 'settings.payment.edit', roles: ['tenant_admin'], permission: 'manage_payments' },
    { name: 'settings.system.edit', roles: ['tenant_admin'], permission: 'manage_system' },
];

const firstAccessibleSettingsRoute = computed(() => {
    return settingsRoutes.find(route => {
        const hasRole = !route.roles || route.roles.some(role => user.roles.includes(role));
        const hasPermission = route.permission && user.permissions.includes(route.permission);
        return hasRole || hasPermission;
    })?.name;
});

const canAccessDomainSettings = computed(() => user.roles.includes('tenant_admin'));

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
                'fixed lg:static inset-y-0 left-0 z-50 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 transition-all duration-300 ease-in-out flex flex-col overflow-x-hidden',
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
                        class="h-12 w-auto max-w-[150px] object-contain transition-all duration-300"
                    />
                </div>

                <!-- Mobile Logo -->
                <div v-if="tenantLogo" class="flex-shrink-0 lg:hidden">
                    <img 
                        :src="tenantLogo" 
                        alt="Tenant Logo" 
                        class="h-10 w-auto object-contain transition-all duration-300"
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
            <div class="flex-1 overflow-y-auto py-4 px-3 space-y-2 custom-scrollbar">
                <template v-for="(item, index) in filteredNavigation" :key="index">
                    
                    <!-- Single Link -->
                    <Link 
                        v-if="!item.children"
                        :href="item.href"
                        :class="[
                            'group flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200',
                            route().current(item.active) 
                                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' 
                                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                        ]"
                        :title="collapsed ? item.name : ''"
                    >
                        <component 
                            :is="item.icon" 
                            :class="[
                                'flex-shrink-0 w-5 h-5 transition-colors duration-200',
                                route().current(item.active) ? 'text-white' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300',
                                collapsed ? 'mx-auto' : 'mr-3'
                            ]" 
                        />
                        <span :class="['transition-all duration-300 lg:whitespace-nowrap', collapsed ? 'lg:hidden' : 'block']">
                            {{ item.name }}
                        </span>
                    </Link>

                    <!-- Group Dropdown -->
                    <div v-else>
                        <button 
                            @click="toggleGroup(item.name)"
                            :class="[
                                'w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 group',
                                openGroups[item.name] 
                                    ? 'text-gray-900 dark:text-white bg-gray-50 dark:bg-slate-800/50' 
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'
                            ]"
                            :title="collapsed ? item.name : ''"
                        >
                            <div class="flex items-center">
                                <component 
                                    :is="item.icon" 
                                    :class="[
                                        'flex-shrink-0 w-5 h-5 transition-colors duration-200',
                                        openGroups[item.name] ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300',
                                        collapsed ? 'mx-auto' : 'mr-3'
                                    ]" 
                                />
                                <span :class="['transition-all duration-300', collapsed ? 'lg:hidden' : 'block']">
                                    {{ item.name }}
                                </span>
                            </div>
                            <ChevronDown 
                                :class="[
                                    'w-4 h-4 text-gray-400 transition-transform duration-200',
                                    openGroups[item.name] ? 'rotate-180' : '',
                                    collapsed ? 'hidden' : 'block'
                                ]" 
                            />
                        </button>
                        
                        <!-- Children -->
                        <div 
                            v-show="openGroups[item.name] && !collapsed" 
                            class="mt-1 space-y-1 pl-10 pr-2 transition-all duration-300"
                        >
                            <Link 
                                v-for="child in item.children" 
                                :key="child.name"
                                :href="child.href"
                                :class="[
                                    'flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 relative',
                                    route().current(child.active)
                                        ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/10'
                                        : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-800/50'
                                ]"
                            >
                                <span v-if="route().current(child.active)" class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-blue-600 rounded-r-md"></span>
                                <span class="truncate">{{ child.name }}</span>
                                
                                <span 
                                    v-if="child.countKey && $page.props.sidebarCounts && $page.props.sidebarCounts[child.countKey] > 0" 
                                    class="ml-auto inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300"
                                >
                                    {{ $page.props.sidebarCounts[child.countKey] }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </template>
            </div>
            
            <!-- User Profile Bottom (Optional Polish) -->
            <div class="mt-auto border-t border-gray-100 dark:border-slate-800 p-4">
                 <div class="flex items-center gap-3" v-if="!collapsed">
                    <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-blue-500 to-purple-500 flex items-center justify-center text-white font-bold text-xs shadow-lg">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0 overflow-hidden">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                    </div>
                 </div>
                 <div v-else class="flex justify-center">
                    <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-blue-500 to-purple-500 flex items-center justify-center text-white font-bold text-xs shadow-lg">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                 </div>
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
            <header class="h-16 bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 flex items-center justify-between px-4 sm:px-6 lg:px-8 z-30 sticky top-0 backdrop-blur-md bg-opacity-80 dark:bg-opacity-80">
                
                <!-- Left: Mobile Toggle & Title -->
                <div class="flex items-center gap-4">
                    <button 
                        @click="toggleSidebar" 
                        class="lg:hidden p-2 -ml-2 text-gray-500 hover:bg-gray-100 rounded-lg dark:text-gray-400 dark:hover:bg-slate-800"
                    >
                        <Menu class="w-6 h-6" />
                    </button>
                    <!-- Breadcrumbs or Title could go here -->
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-2 sm:gap-4">
                     <!-- Theme Toggle -->
                    <button 
                        @click="setTheme(theme === 'dark' ? 'light' : 'dark')"
                        class="p-2 text-gray-500 hover:bg-gray-100 rounded-full dark:text-gray-400 dark:hover:bg-slate-800 transition-colors"
                        :title="theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    >
                        <Sun v-if="theme === 'dark'" class="w-5 h-5" />
                        <Moon v-else class="w-5 h-5" />
                    </button>
                    
                    <!-- Notifications -->
                    <Link 
                        v-if="canAccessDomainSettings"
                        :href="route('domain-requests.index')" 
                        class="relative p-2 text-gray-500 hover:bg-gray-100 rounded-full dark:text-gray-400 dark:hover:bg-slate-800 transition-colors"
                        title="Notifications"
                    >
                        <Bell class="w-5 h-5" />
                        <span 
                            v-if="user.unread_notifications_count > 0"
                            class="absolute top-1.5 right-1.5 flex h-2 w-2 items-center justify-center rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"
                        >
                        </span>
                    </Link>

                    <div class="h-6 w-px bg-gray-200 dark:bg-slate-700 hidden sm:block"></div>

                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-2 pl-2 pr-1 py-1.5 rounded-full hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">
                                <span class="hidden sm:block text-sm font-medium text-gray-700 dark:text-gray-200">{{ user.name }}</span>
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
                            <DropdownLink v-if="firstAccessibleSettingsRoute" :href="route(firstAccessibleSettingsRoute)" class="flex items-center gap-2">
                                <Settings class="w-4 h-4" /> Settings
                            </DropdownLink>
                            <DropdownLink v-if="canAccessDomainSettings" :href="route('domain-requests.index')" class="flex items-center gap-2">
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
