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
    Radio,
    Router,
    LockIcon,
    SubscriptIcon,
    Package,
    Cog,
    Download,
    Printer,
    BookOpen,
} from 'lucide-vue-next';
import { PageManuals, GlobalHelp } from '@/Constants/PageManuals';

const { theme, setTheme } = useTheme();
const showingNavigationDropdown = ref(false);
const sidebarOpen = ref(false);
const collapsed = ref(false);

// Safe route helper — returns '#' if the named route doesn't exist yet,
// preventing Ziggy from crashing the entire layout when a route is missing.
const r = (name, params = {}) => {
    try {
        return route(name, params);
    } catch (e) {
        return '#';
    }
};

// State for open groups in sidebar
const openGroups = ref({});

// Persist collapsed state
onMounted(() => {
    const savedCollapsed = localStorage.getItem('ldns_sidebar_collapsed');
    if (savedCollapsed !== null) {
        collapsed.value = savedCollapsed === 'true';
    }

    // Auto-open groups based on current route
    navigation.forEach((group) => {
        if (group.children) {
            const hasActiveChild = group.children.some((child) =>
                route().current(child.active),
            );
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
    // Dashboard link with permission check
    {
        name: 'Dashboard',
        href: r('dashboard'),
        icon: LayoutDashboard,
        active: 'dashboard',
    },
    {
        name: 'Online Users',
        href: r('activeusers.index'),
        icon: Activity,
        active: 'activeusers.*',
        countKey: 'online_users',
        roles: ['tenant_admin', 'admin', 'customer_care'],
        permission: 'view_online_users',
    },

    // Customers group with detailed permissions
    {
        name: 'Customers',
        icon: Users,
        children: [
            {
                name: 'Users',
                href: r('users.index'),
                active: 'users.*',
                countKey: 'all_users',
                roles: [
                    'tenant_admin',
                    'admin',
                    'marketing',
                    'customer_care',
                    'technical',
                ],
                permission: 'view_users',
            },
            {
                name: 'My Leads',
                href: r('leads.index'),
                active: 'leads.*',
                countKey: 'leads',
                roles: ['tenant_admin', 'admin', 'marketing'],
                permission: 'view_leads',
            },
            {
                name: 'Compensations',
                href: r('compensations.index'),
                active: 'compensations.*',
                roles: ['tenant_admin', 'admin'],
                permission: 'manage_compensations',
            },
        ],
    },

    // Services group with detailed permissions
    {
        name: 'Packages',
        href: r('packages.index'),
        icon: Package,
        active: 'packages.*',
        countKey: 'packages',
        roles: ['tenant_admin', 'admin', 'marketing'],
        permission: 'view_packages',
    },
    {
        name: 'Vouchers',
        href: r('vouchers.index'),
        icon: SubscriptIcon,
        active: 'vouchers.*',
        countKey: 'vouchers',
        roles: ['tenant_admin', 'admin', 'marketing', 'customer_care'],
        permission: 'view_vouchers',
    },
    // Settings link
    {
        name: 'Team',
        href: r('settings.staff.index'),
        icon: Users,
        active: 'settings.staff.*',
        roles: ['tenant_admin'],
        permission: 'manage_staff',
    },

    {
        name: 'Finance',
        icon: Banknote,
        children: [
            {
                name: 'Payments',
                href: r('payments.index'),
                active: 'payments.*',
                roles: ['tenant_admin', 'Finance'],
                permission: 'view_payments',
            },
            {
                name: 'Invoices',
                href: r('invoices.index'),
                active: 'invoices.*',
                countKey: 'invoices',
                roles: ['tenant_admin', 'admin', 'customer_care', 'Finance'],
                permission: 'view_invoices',
            },
        ],
    },

    // Analytics group with more detailed permissions
    {
        name: 'Analytics',
        icon: BarChart3,
        children: [
            {
                name: 'Traffic Analytics',
                href: r('analytics.traffic'),
                active: 'analytics.traffic',
                roles: [
                    'tenant_admin',
                    'admin',
                    'network_engineer',
                    'technical',
                ],
                permission: 'view_traffic_analytics',
            },
            {
                name: 'Network Topology',
                href: r('analytics.topology'),
                active: 'analytics.topology',
                roles: ['tenant_admin', 'network_engineer', 'technical'],
                permission: 'view_topology',
            },
            {
                name: 'Predictive Insights',
                href: r('analytics.predictions'),
                active: 'analytics.predictions',
                roles: ['tenant_admin', 'admin', 'network_engineer'],
                permission: 'view_predictions',
            },
            {
                name: 'Financial Intelligence',
                href: r('analytics.finance'),
                active: 'analytics.finance',
                roles: ['tenant_admin', 'Finance'],
                permission: 'view_finance',
            },
            {
                name: 'Report Builder',
                href: r('analytics.reports.index'),
                active: 'analytics.reports.*',
                roles: ['tenant_admin', 'Finance'],
                permission: 'view_reports',
            },
        ],
    },

    // Network group with detailed permissions
    {
        name: 'Network',
        icon: Network,
        children: [
            {
                name: 'Mikrotiks',
                href: r('mikrotiks.index'),
                active: 'mikrotiks.*',
                countKey: 'mikrotiks',
                roles: [
                    'tenant_admin',
                    'network_engineer',
                    'technical',
                    'network_admin',
                ],
                permission: 'view_routers',
            },
            //{ name: 'TR-069 Devices', href: route('devices.index'), active: 'devices.*', roles: ['tenant_admin', 'network_engineer', 'technical'], permission: 'view_equipment' },
            {
                name: 'Equipment',
                href: r('equipment.index'),
                active: 'equipment.*',
                roles: [
                    'tenant_admin',
                    'admin',
                    'network_engineer',
                    'technical',
                ],
                permission: 'view_equipment',
            },
            {
                name: 'Inventory',
                href: r('inventory.index'),
                active: 'inventory.*',
                roles: [
                    'tenant_admin',
                    'admin',
                    'network_engineer',
                    'technical',
                ],
                permission: 'view_inventory',
            },
        ],
    },

    // Field Operations group
    {
        name: 'Field Ops',
        icon: Wrench,
        children: [
            {
                name: 'My Installations',
                href: r('tenant.installations.my-installations'),
                active: 'tenant.installations.my-installations',
                roles: ['technical', 'technician'],
                permission: 'view_installations',
            },
            {
                name: 'Dispatch Board',
                href: r('tenant.installations.index'),
                active: 'tenant.installations.index',
                roles: [
                    'tenant_admin',
                    'admin',
                    'network_engineer',
                    'technical',
                    'technician',
                ],
                permission: 'view_installations',
            },
        ],
    },
    // Support group
    {
        name: 'Support',
        icon: MessageSquare,
        children: [
            {
                name: 'SMS',
                href: r('sms.index'),
                active: 'sms.*',
                roles: ['tenant_admin', 'admin', 'marketing', 'customer_care'],
                permission: 'view_sms',
            },
            {
                name: 'Templates',
                href: r('smstemplates.index'),
                active: 'smstemplates.*',
                roles: ['tenant_admin', 'admin', 'marketing', 'customer_care'],
                permission: 'view_templates',
            },
            {
                name: 'Tickets',
                href: r('tickets.index'),
                active: 'tickets.*',
                countKey: 'tickets',
                roles: [
                    'tenant_admin',
                    'admin',
                    'marketing',
                    'customer_care',
                    'technical',
                ],
                permission: 'view_tickets',
            },
        ],
    },

    //security
    {
        name: 'Security',
        icon: LockIcon,
        children: [
            {
                name: 'Content Filter',
                href: r('settings.content-filter.index'),
                active: 'settings.content-filter.*',
                roles: ['tenant_admin', 'network_engineer', 'network_admin'],
                permission: 'manage_filters',
            },
        ],
    },
];

const page = usePage();
const user = page.props.auth.user;
const tenant = page.props.tenant;
const tenantLogo = tenant?.logo;

// Helper to check access for a single item
const hasAccess = (item) => {
    if (!item.roles && !item.permission) return true;
    const hasRole = item.roles
        ? item.roles.some((role) => user.roles.includes(role))
        : false;
    const hasPermission = item.permission
        ? user.permissions.includes(item.permission)
        : false;
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
        const accessibleChildren = item.children.filter((child) =>
            hasAccess(child),
        );
        if (accessibleChildren.length > 0) {
            acc.push({ ...item, children: accessibleChildren });
        }
        return acc;
    }, []);
});

const settingsRoutes = [
    { name: 'settings.general.edit', roles: ['tenant_admin'] },
    {
        name: 'settings.hotspot.edit',
        roles: ['tenant_admin', 'admin', 'network_engineer'],
        permission: 'manage_hotspot',
    },
    {
        name: 'settings.sms.edit',
        roles: ['tenant_admin', 'admin', 'marketing'],
        permission: 'manage_sms',
    },
    {
        name: 'settings.payment.edit',
        roles: ['tenant_admin'],
        permission: 'manage_payments',
    },
    {
        name: 'settings.system.edit',
        roles: ['tenant_admin'],
        permission: 'manage_system',
    },
];

const firstAccessibleSettingsRoute = computed(() => {
    return settingsRoutes.find((route) => {
        const hasRole =
            !route.roles ||
            route.roles.some((role) => user.roles.includes(role));
        const hasPermission =
            route.permission && user.permissions.includes(route.permission);
        return hasRole || hasPermission;
    })?.name;
});

const canAccessDomainSettings = computed(() =>
    user.roles.includes('tenant_admin'),
);

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

// Manual / Documentation Logic
const showManualModal = ref(false);
const currentPageCode = computed(() => {
    // Exact match first, then partial match for generic info
    const currentRoute = route().current();
    if (PageManuals[currentRoute]) return currentRoute;

    // Fallback search for base route patterns
    const baseRoute = currentRoute.split('.')[0];
    return (
        Object.keys(PageManuals).find((key) => key.startsWith(baseRoute)) ||
        null
    );
});

const currentManual = computed(
    () => PageManuals[currentPageCode.value] || GlobalHelp,
);

const printManual = () => {
    window.print();
};
</script>

<template>
    <div
        class="flex h-screen overflow-hidden bg-gray-50 transition-colors duration-300 dark:bg-slate-950"
    >
        <!-- Mobile Sidebar Overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm transition-opacity lg:hidden"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex flex-col overflow-x-hidden border-r border-gray-200 bg-white transition-all duration-300 ease-in-out dark:border-slate-800 dark:bg-slate-900 lg:static',
                collapsed ? 'lg:w-20' : 'lg:w-72',
                sidebarOpen
                    ? 'w-72 translate-x-0'
                    : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <!-- Logo Area -->
            <div
                class="flex h-16 items-center justify-between border-b border-gray-100 px-4 dark:border-slate-800"
            >
                <!-- Tenant Logo -->
                <div
                    v-if="tenantLogo && !collapsed"
                    class="hidden flex-shrink-0 lg:block"
                >
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
                    class="ml-auto hidden flex-shrink-0 rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-800 dark:hover:text-gray-300 lg:flex"
                >
                    <component
                        :is="collapsed ? ChevronRight : ChevronLeft"
                        class="h-5 w-5"
                    />
                </button>

                <!-- Mobile Close Button -->
                <button
                    @click="sidebarOpen = false"
                    class="ml-auto text-gray-500 hover:text-gray-700 dark:text-gray-400 lg:hidden"
                >
                    <X class="h-6 w-6" />
                </button>
            </div>

            <!-- Navigation -->
            <div
                class="custom-scrollbar flex-1 space-y-2 overflow-y-auto px-3 py-4"
            >
                <template
                    v-for="(item, index) in filteredNavigation"
                    :key="index"
                >
                    <!-- Single Link -->
                    <Link
                        v-if="!item.children"
                        :href="item.href"
                        :class="[
                            'group flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200',
                            route().current(item.active)
                                ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-md shadow-orange-500/20'
                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-slate-800 dark:hover:text-white',
                        ]"
                        :title="collapsed ? item.name : ''"
                    >
                        <component
                            :is="item.icon"
                            :class="[
                                'h-5 w-5 flex-shrink-0 transition-colors duration-200',
                                route().current(item.active)
                                    ? 'text-white'
                                    : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300',
                                collapsed ? 'mx-auto' : 'mr-3',
                            ]"
                        />
                        <span
                            :class="[
                                'transition-all duration-300 lg:whitespace-nowrap',
                                collapsed ? 'lg:hidden' : 'block',
                            ]"
                        >
                            {{ item.name }}
                        </span>
                    </Link>

                    <!-- Group Dropdown -->
                    <div v-else>
                        <button
                            @click="toggleGroup(item.name)"
                            :class="[
                                'group flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200',
                                openGroups[item.name]
                                    ? 'bg-gray-50 text-gray-900 dark:bg-slate-800/50 dark:text-white'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-slate-800 dark:hover:text-white',
                            ]"
                            :title="collapsed ? item.name : ''"
                        >
                            <div class="flex items-center">
                                <component
                                    :is="item.icon"
                                    :class="[
                                        'h-5 w-5 flex-shrink-0 transition-colors duration-200',
                                        openGroups[item.name]
                                            ? 'text-orange-600 dark:text-orange-400'
                                            : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300',
                                        collapsed ? 'mx-auto' : 'mr-3',
                                    ]"
                                />
                                <span
                                    :class="[
                                        'transition-all duration-300',
                                        collapsed ? 'lg:hidden' : 'block',
                                    ]"
                                >
                                    {{ item.name }}
                                </span>
                            </div>
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-gray-400 transition-transform duration-200',
                                    openGroups[item.name] ? 'rotate-180' : '',
                                    collapsed ? 'hidden' : 'block',
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
                                    'relative flex items-center rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200',
                                    route().current(child.active)
                                        ? 'bg-orange-50 text-orange-600 dark:bg-orange-900/10 dark:text-orange-400'
                                        : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-slate-800/50 dark:hover:text-gray-200',
                                ]"
                            >
                                <span
                                    v-if="route().current(child.active)"
                                    class="absolute left-0 top-1/2 h-8 w-1 -translate-y-1/2 rounded-r-md bg-orange-600"
                                ></span>
                                <span class="truncate">{{ child.name }}</span>

                                <span
                                    v-if="
                                        child.countKey &&
                                        $page.props.sidebarCounts &&
                                        $page.props.sidebarCounts[
                                            child.countKey
                                        ] > 0
                                    "
                                    class="ml-auto inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-slate-700 dark:text-gray-300"
                                >
                                    {{
                                        $page.props.sidebarCounts[
                                            child.countKey
                                        ]
                                    }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </template>
            </div>

            <!-- User Profile Bottom (Optional Polish) -->
            <div
                class="mt-auto border-t border-gray-100 p-4 dark:border-slate-800"
            >
                <div class="flex items-center gap-3" v-if="!collapsed">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-tr from-orange-500 to-red-500 text-xs font-bold text-white shadow-lg"
                    >
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1 overflow-hidden">
                        <p
                            class="truncate text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{ user.name }}
                        </p>
                        <p
                            class="truncate text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ user.email }}
                        </p>
                    </div>
                </div>
                <div v-else class="flex justify-center">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-tr from-orange-500 to-red-500 text-xs font-bold text-white shadow-lg"
                    >
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top Header -->
            <div
                v-if="$page.props.auth.impersonated_by"
                class="z-50 flex items-center justify-between bg-orange-600 px-4 py-2 text-white"
            >
                <div class="flex items-center gap-2">
                    <Shield class="h-4 w-4" />
                    <span class="text-sm font-medium">
                        You are currently impersonating
                        <strong>{{ user.name }}</strong>
                    </span>
                </div>
                <Link
                    :href="route('impersonate.leave')"
                    method="post"
                    as="button"
                    class="rounded bg-white px-3 py-1 text-xs font-bold uppercase tracking-wider text-orange-600 transition-colors hover:bg-orange-50"
                >
                    Leave Impersonation
                </Link>
            </div>
            <header
                class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white bg-opacity-80 px-4 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900 dark:bg-opacity-80 sm:px-6 lg:px-8"
            >
                <!-- Left: Mobile Toggle & Name -->
                <div class="flex items-center gap-4">
                    <button
                        @click="toggleSidebar"
                        class="-ml-2 rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-800 lg:hidden"
                    >
                        <Menu class="h-6 w-6" />
                    </button>
                    <!-- User/Tenant Name -->
                    <div
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ user.name ?? tenant?.name }}
                    </div>
                </div>

                <!-- Right: Actions/Dropdown -->
                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Manual Button -->
                    <button
                        @click="showManualModal = true"
                        class="hidden items-center gap-2 rounded-xl bg-orange-50 px-4 py-2 text-[0.65rem] font-black uppercase tracking-widest text-orange-600 transition-all duration-300 hover:bg-orange-600 hover:text-white dark:bg-orange-950/30 dark:text-orange-400 sm:flex"
                    >
                        <HelpCircle class="h-4 w-4" />
                        {{ currentManual.title }} Manual
                    </button>

                    <!-- User Dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                class="flex items-center gap-2 rounded-full py-1.5 pl-2 pr-1 transition-colors hover:bg-gray-50 dark:hover:bg-slate-800"
                            >
                                <Cog
                                    class="h-7 w-7 text-gray-700 dark:text-gray-200"
                                />
                            </button>
                        </template>

                        <template #content>
                            <div
                                class="border-b border-gray-100 px-4 py-3 dark:border-slate-700 sm:hidden"
                            >
                                <div
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ user.name }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ user.email }}
                                </div>
                            </div>
                            <button
                                @click="
                                    setTheme(
                                        theme === 'dark' ? 'light' : 'dark',
                                    )
                                "
                                class="rounded-full p-2 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-800"
                                :title="
                                    theme === 'dark'
                                        ? 'Switch to Light Mode'
                                        : 'Switch to Dark Mode'
                                "
                            >
                                <Sun v-if="theme === 'dark'" class="h-5 w-5" />
                                <Moon v-else class="h-5 w-5" />
                            </button>
                            <DropdownLink
                                :href="route('profile.edit')"
                                class="flex items-center gap-2"
                            >
                                <FolderEdit class="h-4 w-4" /> Profile
                            </DropdownLink>
                            <DropdownLink
                                v-if="firstAccessibleSettingsRoute"
                                :href="route(firstAccessibleSettingsRoute)"
                                class="flex items-center gap-2"
                            >
                                <Settings class="h-4 w-4" /> Settings
                            </DropdownLink>
                            <DropdownLink
                                v-if="canAccessDomainSettings"
                                :href="route('domain-requests.index')"
                                class="flex items-center gap-2"
                            >
                                <Globe class="h-4 w-4" />Request Domain
                            </DropdownLink>
                            <div
                                class="my-1 border-t border-gray-100 dark:border-slate-700"
                            ></div>
                            <DropdownLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex items-center gap-2 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                            >
                                <LogOut class="h-4 w-4" /> Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Page Header (Title & Actions) -->
            <div
                v-if="$slots.header"
                class="border-b border-gray-200 bg-white dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="px-4 py-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </div>

            <!-- Page Content -->
            <main
                class="flex-1 overflow-y-auto bg-gray-50 p-4 dark:bg-slate-950 sm:p-6 lg:p-8"
            >
                <div class="mx-auto max-w-7xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>

    <!-- Professional Manual Modal -->
    <div
        v-if="showManualModal"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/90 p-6 backdrop-blur-md print:hidden"
    >
        <div
            class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-[3rem] border border-white/10 bg-white shadow-[0_32px_128px_rgba(0,0,0,0.5)] dark:bg-slate-900"
        >
            <div
                class="absolute left-0 top-0 h-2 w-full bg-gradient-to-r from-orange-600 via-red-600 to-pink-600"
            ></div>

            <div
                class="flex flex-shrink-0 items-center justify-between border-b border-gray-100 p-10 dark:border-slate-800"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-3xl bg-orange-600 text-white shadow-xl shadow-orange-600/30"
                    >
                        <BookOpen class="h-8 w-8" />
                    </div>
                    <div>
                        <h2
                            class="text-3xl font-black uppercase tracking-tighter text-slate-900 dark:text-white"
                        >
                            Operational Manual
                        </h2>
                        <p
                            class="text-sm font-bold uppercase tracking-widest text-slate-400"
                        >
                            {{ currentManual.title }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button
                        @click="printManual"
                        class="flex items-center gap-2 rounded-2xl bg-slate-100 px-6 py-3 text-xs font-black uppercase tracking-widest text-slate-600 transition-all hover:bg-orange-600 hover:text-white dark:bg-slate-800 dark:text-slate-300"
                    >
                        <Download class="h-4 w-4" />
                        Save PDF
                    </button>
                    <button
                        @click="showManualModal = false"
                        class="rounded-3xl bg-slate-50 p-4 text-slate-400 transition-all hover:text-red-500 dark:bg-slate-800"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>
            </div>

            <div
                id="manual-content"
                class="custom-scrollbar flex-1 space-y-12 overflow-y-auto p-12"
            >
                <!-- Overview -->
                <section class="space-y-4">
                    <h3
                        class="text-[0.65rem] font-black uppercase tracking-[0.3em] text-orange-600 dark:text-orange-400"
                    >
                        Section I: Tactical Overview
                    </h3>
                    <p
                        class="text-xl font-bold leading-relaxed text-slate-800 dark:text-slate-200"
                    >
                        {{ currentManual.description }}
                    </p>
                </section>

                <div class="grid gap-12 lg:grid-cols-2">
                    <!-- Workflow -->
                    <section class="space-y-6">
                        <h3
                            class="text-[0.65rem] font-black uppercase tracking-[0.3em] text-orange-600 dark:text-orange-400"
                        >
                            Section II: Operational Workflow
                        </h3>
                        <div class="space-y-4">
                            <div
                                v-for="(step, idx) in currentManual.workflow"
                                :key="idx"
                                class="relative pl-8"
                            >
                                <div
                                    class="absolute left-0 top-1.5 h-3 w-3 rounded-full bg-orange-600"
                                ></div>
                                <div
                                    v-if="
                                        idx < currentManual.workflow.length - 1
                                    "
                                    class="absolute left-[5px] top-4 h-full w-0.5 bg-slate-100 dark:bg-slate-800"
                                ></div>
                                <h4
                                    class="font-black uppercase tracking-tight text-slate-900 dark:text-white"
                                >
                                    {{ step.step }}
                                </h4>
                                <p
                                    class="mt-1 text-sm font-medium text-slate-500"
                                >
                                    {{ step.explanation }}
                                </p>
                                <div
                                    class="mt-2 rounded-xl border border-blue-100 bg-blue-50 p-3 dark:border-blue-900/30 dark:bg-blue-900/20"
                                >
                                    <p
                                        class="text-[0.6rem] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400"
                                    >
                                        Rationale
                                    </p>
                                    <p
                                        class="mt-0.5 text-[0.7rem] font-bold italic text-slate-600 dark:text-slate-400"
                                    >
                                        {{ step.why }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Impacts -->
                    <section class="space-y-6">
                        <h3
                            class="text-[0.65rem] font-black uppercase tracking-[0.3em] text-orange-600 dark:text-orange-400"
                        >
                            Section III: Environmental Impact
                        </h3>
                        <div
                            class="group relative overflow-hidden rounded-[2.5rem] border border-slate-100 bg-slate-50 p-8 dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <div
                                class="absolute right-0 top-0 p-4 opacity-5 transition-opacity group-hover:opacity-10"
                            >
                                <Activity class="h-24 w-24" />
                            </div>
                            <p
                                class="relative z-10 font-bold italic leading-relaxed text-slate-600 dark:text-slate-300"
                            >
                                "{{
                                    currentManual.impacts ||
                                    GlobalHelp.description
                                }}"
                            </p>
                        </div>

                        <div v-if="GlobalHelp.tips" class="space-y-4">
                            <h4
                                class="text-[0.65rem] font-black uppercase tracking-widest text-slate-400"
                            >
                                Mastery Tips
                            </h4>
                            <ul class="space-y-2">
                                <li
                                    v-for="tip in GlobalHelp.tips"
                                    :key="tip"
                                    class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400"
                                >
                                    <div
                                        class="h-1 w-1 rounded-full bg-blue-500"
                                    ></div>
                                    {{ tip }}
                                </li>
                            </ul>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Print View (Hidden ordinarily) -->
            <div
                id="manual-print-section"
                class="hidden min-h-screen w-full bg-white p-12 text-black print:block"
            >
                <div class="mb-10 border-b-4 border-black pb-6">
                    <h1 class="mb-2 text-5xl font-black uppercase">
                        {{ currentManual.title }}
                    </h1>
                    <p
                        class="text-sm font-bold uppercase tracking-widest opacity-60"
                    >
                        Zimus ISP Operational Guide — Version 2.0
                    </p>
                </div>

                <div class="space-y-12">
                    <section>
                        <h2
                            class="mb-4 border-b border-black/10 pb-2 text-xs font-black uppercase tracking-[0.3em]"
                        >
                            I. Overview & Purpose
                        </h2>
                        <p class="text-2xl font-bold leading-tight">
                            {{ currentManual.description }}
                        </p>
                    </section>

                    <section>
                        <h2
                            class="mb-6 border-b border-black/10 pb-2 text-xs font-black uppercase tracking-[0.3em]"
                        >
                            II. Step-by-Step Workflow
                        </h2>
                        <div class="space-y-8">
                            <div
                                v-for="(step, idx) in currentManual.workflow"
                                :key="idx"
                                class="border-l-2 border-black pl-6"
                            >
                                <h3 class="text-xl font-black uppercase">
                                    {{ idx + 1 }}. {{ step.step }}
                                </h3>
                                <p class="mt-2 text-base">
                                    {{ step.explanation }}
                                </p>
                                <p class="mt-3 text-sm font-bold italic">
                                    Why: {{ step.why }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section v-if="currentManual.impacts">
                        <h2
                            class="mb-4 border-b border-black/10 pb-2 text-xs font-black uppercase tracking-[0.3em]"
                        >
                            III. Operational Impact
                        </h2>
                        <p
                            class="rounded-2xl border-2 border-black bg-slate-50 p-6 text-lg font-bold italic"
                        >
                            "{{ currentManual.impacts }}"
                        </p>
                    </section>
                </div>

                <div
                    class="mt-20 flex items-center justify-between border-t border-black/10 pt-10 text-[0.65rem] font-bold uppercase tracking-widest opacity-40"
                >
                    <span
                        >Generated on
                        {{ new Date().toLocaleDateString() }}</span
                    >
                    <span>Confidential Internal Document</span>
                </div>
            </div>

            <div
                class="flex flex-shrink-0 items-center justify-between border-t border-gray-100 bg-slate-50/50 p-10 dark:border-slate-800 dark:bg-slate-800/50"
            >
                <span
                    class="text-[0.6rem] font-black uppercase tracking-widest text-slate-400"
                    >© 2026 Zimaradius Digital Archive</span
                >
                <button
                    @click="showManualModal = false"
                    class="rounded-2xl bg-slate-900 px-10 py-4 text-xs font-black uppercase tracking-widest text-white shadow-xl shadow-slate-900/20 transition-all hover:scale-105 active:scale-95 dark:bg-white dark:text-slate-900"
                >
                    Close Manual
                </button>
            </div>
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

@media print {
    body * {
        visibility: hidden !important;
    }
    #manual-print-section,
    #manual-print-section * {
        visibility: visible !important;
    }
    #manual-print-section {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        display: block !important;
    }
}
</style>
