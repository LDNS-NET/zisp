<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { useTheme } from '@/composables/useTheme';
import { Moon, Sun } from 'lucide-vue-next';



const { theme, setTheme } = useTheme();
const page = usePage();
const tenantLogo = page.props.tenantLogo;
const mobileMenuOpen = ref(false);
const sidebarOpen = ref(false);
const scrolled = ref(false);

// Navigation items
const navItems = [
    { label: 'Features', href: '#features', id: 'features' },
    { label: 'How It Works', href: '#how-it-works', id: 'how-it-works' },
    { label: 'Advanced', href: '#advanced', id: 'advanced' },
    { label: 'Demo', href: '#demo', id: 'demo' },
    { label: 'Pricing', href: '#pricing', id: 'pricing' },
];

// Footer sections for sidebar
const sidebarSections = [
    {
        title: 'Product',
        links: [
            { label: 'Features', href: '#' },
            { label: 'Pricing', href: '#' },
            { label: 'Security', href: '#' },
        ],
    },
    {
        title: 'Company',
        links: [
            { label: 'About', href: '#' },
            { label: 'Blog', href: '#' },
            { label: 'Careers', href: '#' },
        ],
    },
    {
        title: 'Resources',
        links: [
            { label: 'Docs', href: '#' },
            { label: 'Support', href: '#' },
            { label: 'API', href: '#' },
        ],
    },
    {
        title: 'Legal',
        links: [
            { label: 'Privacy', href: '#' },
            { label: 'Terms', href: '#' },
            { label: 'Contact', href: '#' },
        ],
    },
];

// Handle scroll for navbar background
if (typeof window !== 'undefined') {
    window.addEventListener('scroll', () => {
        scrolled.value = window.scrollY > 20;
    });
}

// Smooth scroll handler
const handleNavClick = (e, href) => {
    e.preventDefault();
    const id = href.replace('#', '');
    const element = document.getElementById(id);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
        mobileMenuOpen.value = false;
    }
};

const openX = () => {
    window.open('https://x.com/ATG_Officia', '_blank');
};

const openTikTok = () => {
    window.open(
        'https://www.tiktok.com/@zyraaf_cloud',
        '_blank',
        'noopener,noreferrer'
    );
};

const openTube = () => {
    window.open(
        'https://www.youtube.com/@zyraaf_cloud',
        '_blank',
        'noopener,noreferrer'
    );
};
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-cyan-200 via-indigo-200 to-emerald-200 dark:from-slate-950 dark:via-blue-950/20 dark:to-slate-900 relative overflow-x-hidden">
        <!-- Premium Gradient Overlays (Background) -->
        <div class="fixed inset-0 -z-10">
            <!-- Main background gradient -->
            <div class="absolute inset-0 bg-gradient-to-br from-white/80 via-blue-300 to-emerald-200 dark:from-slate-950/80 dark:via-blue-900/5 dark:to-emerald-900/10"></div>
            
            <!-- Subtle radial gradient from top-right (sun effect) -->
            <div class="absolute -top-96 -right-96 w-[800px] h-[800px] bg-gradient-to-br from-amber-200/20 via-orange-200/10 to-transparent dark:from-amber-900/15 dark:via-orange-900/5 dark:to-transparent rounded-full blur-3xl"></div>
            
            <!-- Soft glow traveling to bottom-left -->
            <div class="absolute -bottom-80 -left-80 w-[600px] h-[600px] bg-gradient-to-tr from-emerald-200/20 via-teal-200/10 to-transparent dark:from-emerald-900/15 dark:via-teal-900/5 dark:to-transparent rounded-full blur-3xl"></div>
            
            <!-- Center ambient glow -->
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-gradient-to-r from-blue-200/10 via-sky-200/5 to-transparent dark:from-blue-900/8 dark:via-sky-900/3 dark:to-transparent rounded-full blur-3xl"></div>
        </div>

        <!-- Subtle Grid Pattern -->
        <div class="fixed inset-0 -z-10 bg-[linear-gradient(to_right,rgba(0,0,0,0.015)_1px,transparent_1px),linear-gradient(to_bottom,rgba(0,0,0,0.015)_1px,transparent_1px)] dark:bg-[linear-gradient(to_right,rgba(255,255,255,0.025)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.025)_1px,transparent_1px)] bg-[size:80px_80px] pointer-events-none opacity-40"></div>

        <!-- Decorative accent circles (non-intrusive) -->
        <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-gradient-to-bl from-yellow-300/8 to-transparent dark:from-yellow-600/5 dark:to-transparent blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 rounded-full bg-gradient-to-tr from-teal-300/8 to-transparent dark:from-teal-600/5 dark:to-transparent blur-3xl"></div>
        </div>

        <!-- Navigation Bar -->
        <nav
            class="fixed top-0 left-0 right-0 z-50 transition-all duration-500"
            :class="[
                scrolled
                    ? 'bg-white/80 dark:bg-slate-950/80 backdrop-blur-xl border-b border-gray-200/50 dark:border-gray-800/50 shadow-lg'
                    : 'bg-transparent'
            ]"
        >
            <div class="mx-auto max-w-9xl px-4 sm:px-6 lg:px-6">
                <div class="flex h-20 items-center justify-between">
                    <!-- Logo -->
                    <Link
                        href="/"
                        class="group relative z-10 flex items-center gap-2 transition-all duration-300 hover:scale-105"
                    >
                        <img
                            v-if="tenantLogo"
                            :src="tenantLogo"
                            alt="Logo"
                            class="h-10 w-10 object-contain"
                        />
                        <ApplicationLogo
                            v-else
                            class="h-10 w-10 fill-current text-emerald-600 dark:text-emerald-400"
                        />
                        <span class="hidden sm:inline font-bold text-lg text-gray-900 dark:text-white tracking-tight">
                            Zyraaf Cloud
                        </span>
                    </Link>
                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex items-center gap-8">
                        <a
                            v-for="item in navItems"
                            :key="item.id"
                            :href="item.href"
                            @click="handleNavClick($event, item.href)"
                            class="text-gray-700 dark:text-gray-300 font-medium transition-all duration-300 hover:text-emerald-600 dark:hover:text-emerald-400 relative group"
                        >
                            {{ item.label }}
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-gradient-to-r from-emerald-600 to-teal-600 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                    </div>

                    <!-- CTA Buttons (Desktop) -->
                    <div class="hidden md:flex items-center gap-4">
                        <Link
                            v-if="page.props.canLogin"
                            :href="route('login')"
                            class="px-6 py-2.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors duration-300"
                        >
                            Sign In
                        </Link>
                        <Link
                            v-if="page.props.canRegister"
                            :href="route('register')"
                            class="group inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-semibold shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 dark:from-emerald-500 dark:to-teal-500 active:scale-95"
                        >
                            Get Started
                            <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                        <button 
                            @click="setTheme(theme === 'dark' ? 'light' : 'dark')"
                            class="p-2 text-gray-500 hover:bg-black rounded-full dark:text-gray-400 dark:hover:bg-white transition-colors"
                            :title="theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                        >
                            <Sun v-if="theme === 'dark'" class="w-5 h-5 text-yellow-500 rounded-xl" />
                            <Moon v-else class="w-6 h-6 text-extrabold" />
                        </button>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="md:hidden relative z-50 inline-flex items-center justify-center rounded-lg p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-300"
                    >
                        <span class="sr-only">Toggle menu</span>
                        <svg
                            v-if="!mobileMenuOpen"
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg
                            v-else
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Mobile Menu -->
                <transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div
                        v-if="mobileMenuOpen"
                        class="md:hidden absolute top-20 left-0 right-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border-b border-gray-200/50 dark:border-gray-800/50 shadow-xl"
                    >
                        <div class="px-4 py-6 space-y-4">
                            <a
                                v-for="item in navItems"
                                :key="item.id"
                                :href="item.href"
                                @click="handleNavClick($event, item.href)"
                                class="block px-4 py-2 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600 dark:hover:text-emerald-400 transition-all duration-300"
                            >
                                {{ item.label }}
                            </a>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-3">
                                <Link
                                    v-if="page.props.canLogin"
                                    :href="route('login')"
                                    class="block w-full px-4 py-2 text-center text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition-colors duration-300"
                                >
                                    Sign In
                                </Link>
                                <Link
                                    v-if="page.props.canRegister"
                                    :href="route('register')"
                                    class="block w-full px-4 py-2 text-center text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-lg hover:shadow-lg transition-all duration-300 dark:from-emerald-500 dark:to-teal-500"
                                >
                                    Get Started
                                </Link>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </nav>

        <!-- Main Content Wrapper with Sidebar -->
        <div class="flex relative min-h-screen">
            <!-- Left Sidebar -->
            <aside
                class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:w-64 lg:flex lg:flex-col lg:bg-white/80 lg:dark:bg-slate-900/80 lg:border-r lg:border-gray-200/50 lg:dark:border-gray-800/50 lg:backdrop-blur-xl lg:overflow-y-auto"
            >
                <div class="flex flex-col h-full">
                    <!-- Logo Area in Sidebar -->
                    <div class="flex items-center justify-between px-4 py-6 border-b border-gray-200/50 dark:border-gray-800/50">
                    </div>

                    <!-- Sidebar Content -->
                    <nav class="flex-1 overflow-y-auto px-4 py-8 space-y-8">
                        <div v-for="section in sidebarSections" :key="section.title" class="space-y-3">
                            <h3 class="px-3 text-xs font-bold text-gray-900 dark:text-white tracking-widest uppercase">
                                {{ section.title }}
                            </h3>
                            <ul class="space-y-2">
                                <li v-for="link in section.links" :key="link.label">
                                    <a
                                        :href="link.href"
                                        class="group block px-3 py-2 text-sm text-gray-600 dark:text-gray-400 rounded-md transition-all duration-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:text-emerald-600 dark:hover:text-emerald-400"
                                    >
                                        {{ link.label }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </nav>

                    <!-- Copyright -->
                    <div class="border-t border-gray-200/50 dark:border-gray-800/50 px-4 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-500 text-center">
                            ZYRAAF NETWORKS &copy; {{ new Date().getFullYear() }}
                        </p>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 lg:ml-64">
                <div class="relative pt-20">
                    <slot />
                </div>
            </main>
        </div>

        <!-- Footer -->
        <footer class="relative border-t border-gray-200 dark:border-gray-800 bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm lg:ml-64">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-light">
                        Built with <span class="text-emerald-600 dark:text-emerald-400">❤</span> by skilled developers
                    </p>
                    <div class="flex gap-6 mt-6 md:mt-0">
                        <button @click="openX" class="text-gray-600 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-all duration-300 hover:scale-110">
                            <span class="sr-only">Twitter</span>
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8.29 20c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-7.678 3.743A11.65 11.65 0 012.909 5.114a4.106 4.106 0 001.27 5.478A4.072 4.072 0 012.8 10.77v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" />
                            </svg>
                        </button>
                        <button @click="openTikTok" class="text-gray-600 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-all duration-300 hover:scale-110">
                            <span class="sr-only">Tiktok</span>
                            <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            class="h-6 w-6"
                            aria-hidden="true"
                        >
                            <path d="M16.5 1.5c.7 1.9 2.1 3.3 4 4v3.2c-1.8-.1-3.5-.7-5-1.8v7.1c0 4.1-3.4 7.5-7.5 7.5S.5 18.1.5 14s3.4-7.5 7.5-7.5c.5 0 1 .1 1.5.2v3.3c-.5-.3-1-.4-1.5-.4-2.1 0-3.9 1.7-3.9 3.9s1.7 3.9 3.9 3.9 3.9-1.7 3.9-3.9V1.5h4.1z"/>
                        </svg>

                        </button>
                        <button @click="openTube" class="text-gray-600 dark:text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-all duration-300 hover:scale-110">
                            <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            class="h-6 w-6"
                            aria-hidden="true"
                        >
                            <!-- Soft red background -->
                            <rect
                                x="2"
                                y="5"
                                width="20"
                                height="14"
                                rx="4"
                                fill="currentColor"
                                class="text-red-600"
                            />

                            <!-- Play icon -->
                            <polygon
                                points="10,9 16,12 10,15"
                                fill="white"
                            />
                        </svg>


                        </button>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* Smooth scroll behavior */
html {
    scroll-behavior: smooth;
}
</style>
