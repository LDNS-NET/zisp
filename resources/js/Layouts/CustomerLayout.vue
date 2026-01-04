<script setup>
import { ref, onMounted } from 'vue';
import { Link, usePage, useForm, Head } from '@inertiajs/vue3';
import Toast from '@/Components/Toast.vue';
import { 
    LayoutDashboard, 
    RefreshCw, 
    TrendingUp, 
    LogOut, 
    Menu, 
    X, 
    User,
    ChevronRight,
    Wifi,
    CreditCard
} from 'lucide-vue-next';

const page = usePage();
const user = page.props.user;
const isMobileMenuOpen = ref(false);

const navigation = [
    { name: 'Dashboard', href: route('customer.dashboard'), icon: LayoutDashboard, active: 'customer.dashboard' },
    { name: 'Renew Plan', href: route('customer.renew'), icon: RefreshCw, active: 'customer.renew' },
    { name: 'Upgrade Speed', href: route('customer.upgrade'), icon: TrendingUp, active: 'customer.upgrade' },
];

const logout = () => {
    useForm({}).post(route('customer.logout'));
};

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col lg:flex-row">
        <Head />
        <!-- Mobile Header -->
        <header class="lg:hidden bg-white border-b border-slate-200 px-4 h-16 flex items-center justify-between sticky top-0 z-50">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                    <Wifi class="w-5 h-5 text-white" />
                </div>
                <span class="font-black text-xl tracking-tight text-slate-900">ZISP</span>
            </div>
            <button @click="toggleMobileMenu" class="p-2 text-slate-500 hover:bg-slate-100 rounded-xl transition-colors">
                <Menu v-if="!isMobileMenuOpen" class="w-6 h-6" />
                <X v-else class="w-6 h-6" />
            </button>
        </header>

        <!-- Sidebar (Desktop & Mobile Overlay) -->
        <aside 
            :class="[
                'fixed inset-y-0 left-0 z-40 w-72 bg-white border-r border-slate-200 transform transition-transform duration-300 ease-in-out lg:relative lg:translate-x-0',
                isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <div class="h-full flex flex-col">
                <!-- Sidebar Header -->
                <div class="p-6 hidden lg:flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-200">
                        <Wifi class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h1 class="font-black text-xl tracking-tight text-slate-900">ZISP Portal</h1>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Customer Access</p>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 px-4 py-6 space-y-2">
                    <Link 
                        v-for="item in navigation" 
                        :key="item.name" 
                        :href="item.href"
                        @click="isMobileMenuOpen = false"
                        :class="[
                            'flex items-center gap-3 px-4 py-3 rounded-2xl font-bold transition-all duration-200 group',
                            route().current(item.active) 
                                ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' 
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
                        ]"
                    >
                        <component :is="item.icon" class="w-5 h-5" />
                        <span>{{ item.name }}</span>
                        <ChevronRight v-if="!route().current(item.active)" class="w-4 h-4 ml-auto opacity-0 group-hover:opacity-100 transition-opacity" />
                    </Link>
                </nav>

                <!-- User Profile Section -->
                <div class="p-4 border-t border-slate-100">
                    <div class="bg-slate-50 rounded-2xl p-4 mb-4">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-indigo-600 font-black">
                                {{ user.username.charAt(0).toUpperCase() }}
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-sm font-black text-slate-900 truncate">{{ user.username }}</p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest truncate">{{ user.account_number }}</p>
                            </div>
                        </div>
                        <button @click="logout" class="w-full flex items-center justify-center gap-2 py-2 rounded-xl bg-white border border-slate-200 text-xs font-black text-slate-600 hover:bg-red-50 hover:text-red-600 hover:border-red-100 transition-all">
                            <LogOut class="w-4 h-4" />
                            Sign Out
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile Overlay -->
        <div 
            v-if="isMobileMenuOpen" 
            @click="isMobileMenuOpen = false"
            class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-30 lg:hidden transition-opacity"
        ></div>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Desktop Header -->
            <header class="hidden lg:flex bg-white border-b border-slate-200 h-16 items-center justify-between px-8 sticky top-0 z-20">
                <div class="flex items-center gap-4">
                    <h2 class="font-black text-slate-900 text-lg">
                        <slot name="header"></slot>
                    </h2>
                </div>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-50 rounded-full border border-slate-100">
                        <div class="w-2 h-2 rounded-full" :class="user.online ? 'bg-green-500 animate-pulse' : 'bg-slate-300'"></div>
                        <span class="text-xs font-bold text-slate-600">{{ user.online ? 'Connected' : 'Offline' }}</span>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-4 lg:p-8">
                <div class="max-w-5xl mx-auto">
                    <transition
                        name="page"
                        mode="out-in"
                        appear
                    >
                        <div :key="$page.url">
                            <slot />
                        </div>
                    </transition>
                </div>
            </div>
        </main>
    </div>
    <Toast />
</template>

<style>
.page-enter-active,
.page-leave-active {
    transition: all 0.3s ease;
}

.page-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.page-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
