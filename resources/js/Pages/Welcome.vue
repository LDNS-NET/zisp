<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import WelcomeLayout from '@/Layouts/WelcomeLayout.vue';
import Modal from '@/Components/Modal.vue';
import { countries } from '@/Data/countries';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

defineOptions({
    layout: WelcomeLayout,
});

//find selected country and apply prices
const selectedCountryCode = ref('KE'); // Default to Kenya

const selectedCountry = computed(() => {
    return (
        countries.find(c => c.code === selectedCountryCode.value) || {
            name: 'Default',
            pppoePricePerMonth: 500,
            hotspotPricePerMonth: '3%',
        }
    )
})


// Animation state
const hoveredFeature = ref(null);

// Enhanced Features
const detailedFeatures = [
    {
        icon: '🔗',
        title: 'Seamless Payment Integration',
        description:
            'Accept payments via Mpesa, PesaPal, MTN Money with instant reconciliation. Enable auto-receipts and reduce manual financial tasks.',
    },
    {
        icon: '📡',
        title: 'Deep Mikrotik RouterOS Integration',
        description:
            'Directly manage PPPoE, DHCP, and Hotspot users. Suspend/resume users, assign plans, apply queues, and track sessions through Mikrotik API in real-time.',
    },
    {
        icon: '⚡',
        title: 'Advanced Bandwidth Management',
        description:
            'Shape traffic dynamically using Mikrotik queues. Automatically throttle or disconnect users based on payment status or quota usage.',
    },
    {
        icon: '🗓️',
        title: 'Automated Invoicing & Billing',
        description:
            'Generate invoices, send payment reminders, and provide receipts via SMS and email. Handle VAT, discounts, and overdue notices with automation.',
    },
    {
        icon: '👤',
        title: 'Customer Self-Service Portal',
        description:
            'Customers can view their invoices, usage, renew service, and submit tickets. Supports mobile and desktop access.',
    },
    {
        icon: '📈',
        title: 'Real-Time Analytics & Insights',
        description:
            'Monitor payments, usage trends, churn, delinquency, and customer growth with live dashboards and downloadable reports.',
    },
    {
        icon: '🔐',
        title: 'Secure, Scalable & Modular',
        description:
            'Laravel-based with Inertia & Vue 3, protected by SSL, 2FA, and audit logging. Scales with your business.',
    },
];

// How It Works Steps
const howItWorksSteps = [
    {
        step: '1',
        title: 'Connect Mikrotik & Set Plans',
        description:
            'Easily link Mikrotik routers, configure PPPoE/Hotspot access, and create speed packages and pricing tiers.',
    },
    {
        step: '2',
        title: 'Onboard Subscribers',
        description:
            'Create or import subscribers, assign them plans, and automate service activation and credentials provisioning.',
    },
    {
        step: '3',
        title: 'Automate Billing & Payments',
        description:
            'Invoices, reminders, and M-Pesa payment verification happen automatically. Users are notified instantly.',
    },
    {
        step: '4',
        title: 'Monitor, Optimize & Grow',
        description:
            'Track user sessions, enforce data limits, receive router uptime alerts, and make data-driven decisions.',
    },
];

const showDemoModal = ref(false)

const goToDemo = () => {
    window.open('https://demo.zyraaf.cloud/login', '_blank')
}

</script>

<template>
    <Head title="Zyraaf Cloud | ISP Manager" />

    <div class="min-h-screen relative overflow-hidden">
        <!-- Hero Section -->
        <div class="relative flex min-h-screen flex-col justify-between">
            <div class="mx-auto flex max-w-7xl w-full flex-col-reverse items-center gap-16 pt-20 lg:flex-row px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl text-center lg:text-left flex-1 animate-fade-in">
                    <div class="mb-6 inline-block rounded-full bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/30 dark:to-teal-900/30 px-4 py-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300 backdrop-blur-md border border-emerald-200/60 dark:border-emerald-700/60 shadow-sm transition-all duration-300 hover:shadow-md hover:border-emerald-300/80 dark:hover:border-emerald-600/80">
                        <span class="inline-block mr-2">✨</span>Top ISP Management Platform In Africa
                    </div>
                    
                    <h1 class="mb-6 text-5xl sm:text-6xl lg:text-7xl font-extrabold leading-tight text-gray-900 dark:text-white tracking-tight">
                        Empower Your ISP with
                        <span class="block mt-2 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 dark:from-emerald-300 dark:via-teal-300 dark:to-cyan-300 bg-clip-text text-transparent bg-size-200 animate-gradient">Zyraaf Cloud</span>
                    </h1>
                    
                    <p class="mt-8 text-xl leading-relaxed text-gray-700 dark:text-gray-300 max-w-xl font-light">
                        The all-in-one platform for billing, bandwidth management, and customer experience. Seamless M-Pesa payments, advanced Mikrotik controls, and automated invoicing – all from one powerful dashboard.
                    </p>

                    <div class="mt-12 flex flex-col justify-center gap-4 sm:flex-row lg:justify-start">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="group inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-8 py-4 font-bold text-white shadow-xl transition-all duration-300 hover:shadow-2xl hover:scale-105 hover:-translate-y-1 dark:from-emerald-500 dark:to-teal-500 active:scale-95"
                        >
                            <span class="relative z-10">Start Free Trial</span>
                            <svg class="ml-2 h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </Link>
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="group inline-flex items-center justify-center rounded-xl border-2 border-emerald-600 dark:border-emerald-400 bg-white/80 dark:bg-slate-800/80 px-8 py-4 font-bold text-emerald-600 dark:text-emerald-400 shadow-lg backdrop-blur-sm transition-all duration-300 hover:scale-105 hover:bg-emerald-50 dark:hover:bg-slate-700/80 hover:shadow-xl active:scale-95"
                        >
                            <span class="relative z-10">Log In</span>
                            <svg class="ml-2 h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h2m0-16V9a2 2 0 012 2m0 0h2a2 2 0 012-2m0 0V5a2 2 0 00-2-2m0 16V9a2 2 0 012 2m0 0h2a2 2 0 00-2 2v2" />
                            </svg>
                        </Link>
                    </div>

                    <!-- Stats -->
                    <div class="mt-16 grid grid-cols-3 gap-8">
                        <div class="text-center group">
                            <div class="text-3xl font-bold bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-400 dark:to-teal-400 bg-clip-text text-transparent transition-all duration-300 group-hover:scale-110">100+</div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 font-medium">Active ISP's</p>
                        </div>
                        <div class="text-center group">
                            <div class="text-3xl font-bold bg-gradient-to-r from-teal-600 to-cyan-600 dark:from-teal-400 dark:to-cyan-400 bg-clip-text text-transparent transition-all duration-300 group-hover:scale-110">99.9%</div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 font-medium">Uptime</p>
                        </div>
                        <div class="text-center group">
                            <div class="text-3xl font-bold bg-gradient-to-r from-cyan-600 to-sky-600 dark:from-cyan-400 dark:to-sky-400 bg-clip-text text-transparent transition-all duration-300 group-hover:scale-110">24/7</div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 font-medium">Support</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Grid -->
        <section id="features" class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl font-bold text-gray-900 dark:text-white mb-4 tracking-tight">
                    Core Features
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto font-light">
                    Everything you need to run a modern, scalable ISP business
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="(feature, idx) in [
                    { icon: '🔗', title: 'Mikrotik API Control', desc: 'Suspend, resume, throttle, and monitor users directly via RouterOS API.' },
                    { icon: '💳', title: 'Payment Reconciliation', desc: 'Supports M-Pesa, PesaPal with automatic real-time updates.' },
                    { icon: '📄', title: 'Automated Invoicing', desc: 'Digital invoices, reminders, and receipts via SMS & email.' },
                    { icon: '👥', title: 'Self-Service Portal', desc: 'Customers manage renewals, usage, and support anytime.' },
                ]" :key="idx" class="group relative overflow-hidden rounded-2xl bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl p-8 shadow-lg border border-white/50 dark:border-slate-700/50 transition-all duration-500 hover:shadow-2xl hover:scale-105 hover:-translate-y-3 cursor-default">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/0 to-teal-500/0 group-hover:from-emerald-500/8 group-hover:to-teal-500/5 transition-all duration-500"></div>
                    <div class="absolute -inset-1 bg-gradient-to-r from-emerald-600/0 via-teal-600/0 to-cyan-600/0 group-hover:from-emerald-600/20 group-hover:via-teal-600/20 group-hover:to-cyan-600/20 rounded-2xl blur-lg opacity-0 group-hover:opacity-50 transition-opacity duration-500 -z-10"></div>
                    <div class="relative">
                        <div class="text-5xl mb-4 transition-transform duration-300 group-hover:scale-125 group-hover:rotate-6">{{ feature.icon }}</div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3 tracking-tight">{{ feature.title }}</h3>
                        <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">{{ feature.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section id="how-it-works" class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl font-bold text-gray-900 dark:text-white mb-4 tracking-tight">
                    How Zyraaf Cloud Works
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto font-light">
                    Get up and running in minutes, not weeks
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                <div v-for="(step, idx) in howItWorksSteps" :key="step.step" class="group relative" :style="{ animationDelay: idx * 100 + 'ms' }">
                    <!-- Connection Line -->
                    <div v-if="step.step !== '4'" class="hidden lg:block absolute top-24 left-full w-1/4 h-1 bg-gradient-to-r from-emerald-400 via-teal-400 to-transparent opacity-40"></div>

                    <div class="relative flex flex-col items-center rounded-2xl bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl p-8 shadow-lg border border-white/50 dark:border-slate-700/50 transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 h-full group">
                        <!-- Step Number -->
                        <div class="absolute -top-6 left-1/2 -translate-x-1/2 h-12 w-12 rounded-full bg-gradient-to-br from-emerald-600 to-teal-600 flex items-center justify-center text-white font-bold text-lg shadow-lg transition-transform duration-300 group-hover:scale-110 dark:from-emerald-500 dark:to-teal-500">
                            {{ step.step }}
                        </div>

                        <h3 class="mt-8 text-lg font-bold text-gray-900 dark:text-white text-center mb-3 tracking-tight">
                            {{ step.title }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300 text-center leading-relaxed">
                            {{ step.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Advanced Mikrotik Features -->
        <section id="advanced" class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl font-bold text-gray-900 dark:text-white mb-4 tracking-tight">
                    Advanced Mikrotik Features
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto font-light">
                    Enterprise-grade router management at your fingertips
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2">
                <!-- Real-Time User Control -->
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500/8 to-emerald-600/4 dark:from-emerald-900/20 dark:to-emerald-800/10 backdrop-blur-xl p-8 border border-emerald-200/60 dark:border-emerald-700/50 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 hover:border-emerald-200/100 dark:hover:border-emerald-700/80">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-emerald-100 to-emerald-50 dark:from-emerald-900/30 dark:to-emerald-900/20 mb-4 transition-transform duration-300 group-hover:scale-110">
                            <span class="text-2xl">⚡</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 tracking-tight">Real-Time User Control</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">Suspend and reconnect users instantly. Push updated queue rules or credentials without manual router login.</p>
                    </div>
                </div>

                <!-- Hotspot Voucher Management -->
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-green-500/8 to-green-600/4 dark:from-green-900/20 dark:to-green-800/10 backdrop-blur-xl p-8 border border-green-200/60 dark:border-green-700/50 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 hover:border-green-200/100 dark:hover:border-green-700/80">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-green-100 to-green-50 dark:from-green-900/30 dark:to-green-900/20 mb-4 transition-transform duration-300 group-hover:scale-110">
                            <span class="text-2xl">🎟️</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 tracking-tight">Hotspot Voucher Management</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">Generate, assign, and validate vouchers. Monitor session time and device count live from dashboard.</p>
                    </div>
                </div>

                <!-- Usage-Based Throttling -->
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500/8 to-amber-600/4 dark:from-amber-900/20 dark:to-amber-800/10 backdrop-blur-xl p-8 border border-amber-200/60 dark:border-amber-700/50 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 hover:border-amber-200/100 dark:hover:border-amber-700/80">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-amber-100 to-amber-50 dark:from-amber-900/30 dark:to-amber-900/20 mb-4 transition-transform duration-300 group-hover:scale-110">
                            <span class="text-2xl">📊</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 tracking-tight">Usage-Based Throttling</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">Apply caps and reset limits based on GB used or time online. Automatically throttle after limits exceeded.</p>
                    </div>
                </div>

                <!-- Router Monitoring -->
                <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-cyan-500/8 to-cyan-600/4 dark:from-cyan-900/20 dark:to-cyan-800/10 backdrop-blur-xl p-8 border border-cyan-200/60 dark:border-cyan-700/50 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 hover:border-cyan-200/100 dark:hover:border-cyan-700/80">
                    <div class="absolute inset-0 bg-gradient-to-br from-cyan-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-cyan-100 to-cyan-50 dark:from-cyan-900/30 dark:to-cyan-900/20 mb-4 transition-transform duration-300 group-hover:scale-110">
                            <span class="text-2xl">📡</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 tracking-tight">Router Monitoring & Alerts</h3>
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">Track CPU load, uptime, and online status. Get notified on disconnects or unusual bandwidth spikes in real time.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why Choose Us -->
        <section class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl font-bold text-gray-900 dark:text-white mb-4 tracking-tight">
                    Why Zyraaf Cloud is the Smart Choice
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto font-light">
                    Trusted by the leading ISPs across Africa
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div v-for="(feature, idx) in detailedFeatures" :key="feature.title" class="group relative overflow-hidden rounded-2xl bg-white/80 dark:bg-slate-800/80 backdrop-blur-xl p-8 shadow-lg border border-white/50 dark:border-slate-700/50 transition-all duration-500 hover:shadow-2xl hover:-translate-y-2 hover:border-emerald-200/60 dark:hover:border-emerald-700/60" :style="{ transitionDelay: idx * 50 + 'ms' }">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/0 to-teal-500/0 group-hover:from-emerald-500/6 group-hover:to-teal-500/4 transition-all duration-500"></div>
                    
                    <div class="relative flex items-start gap-4">
                        <div class="text-4xl flex-shrink-0 mt-1 transition-transform duration-300 group-hover:scale-125 group-hover:-rotate-6">{{ feature.icon }}</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 tracking-tight">
                                {{ feature.title }}
                            </h3>
                            <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section id="demo" class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-600 p-12 sm:p-16 lg:p-20 shadow-2xl border border-white/20">
                <div class="relative text-center">
                    <h2 class="text-4xl sm:text-5xl font-bold text-white mb-6">
                        Ready to Supercharge Your ISP?
                    </h2>

                    <p class="text-lg text-emerald-100 mb-10 max-w-2xl mx-auto">
                        Experience Zyraaf Cloud with a live demo environment.
                    </p>

                    <button
                        @click="showDemoModal = true"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-8 py-4 text-base font-semibold text-emerald-700 shadow-lg hover:bg-emerald-50 transition"
                    >
                        View Live Demo
                    </button>
                </div>
            </div>

            

            <!-- Demo Modal -->
            <div
                v-if="showDemoModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
            >
                <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 p-8 shadow-2xl">
                    <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">
                        Demo Access
                    </h3>

                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        Use the credentials below to explore the Zyraaf Cloud demo system.
                    </p>

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-3">
                            <span class="text-sm text-gray-500">Username</span>
                            <span class="font-mono font-semibold text-emerald-600">demo</span>
                        </div>
                        <div class="flex justify-between rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-3">
                            <span class="text-sm text-gray-500">Password</span>
                            <span class="font-mono font-semibold text-emerald-600">password</span>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="showDemoModal = false"
                            class="flex-1 rounded-lg border border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100"
                        >
                            Close
                        </button>

                        <button
                            @click="goToDemo"
                            class="flex-1 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700"
                        >
                            Login to Demo
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section id="pricing">
            <!--Pricing section for each country-->
            <div class="mt-10 text-center border-t pt-8 border-gray-300 dark:border-gray-700">
                <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Your Country:</label>
                <select
                    id="country"
                    v-model="selectedCountryCode"
                    class="mx-auto block w-40 rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm"
                >
                    <option v-for="country in countries" :key="country.code" :value="country.code">
                        {{ country.name }}
                    </option>
                </select>


                <!-- Toggle benefits of PPPoE and Hotspot -->
                <div class="flex gap-10 mt-8 max-w-4xl mx-auto border border-blue-200/50 dark:border-blue-700/50 rounded-2xl p-6 bg-blue-50/30 dark:bg-blue-900/30 backdrop-blur-md shadow-lg">
                    
                    <!-- PPPoE -->
                    <div class="flex-1 p-6 bg-white/80 dark:bg-gray-800/80 backdrop-blur-md rounded-2xl border border-white/50 dark:border-gray-700/50 shadow-lg">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1">
                            PPPoE Plan for <span class="text-blue-500">{{ selectedCountry.name }}</span>
                        </h3>

                        <div class="bg-blue-100 dark:bg-gray-700 mb-4 pb-4 rounded-xl">
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="text-xs uppercase text-cyan-500">Price per active user</span><br>
                                <span class="font-mono font-semibold text-emerald-600">
                                    {{ selectedCountry.pppoePricePerMonth }}
                                </span>
                                {{ selectedCountry.currency || 'KES' }} / Month
                            </p>
                        </div>

                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Best for Fiber and Wireless subscriber management
                        </p>

                        <ul class="space-y-2 text-md text-gray-700 dark:text-gray-300 mb-4 text-left px-6 hover:">
                            <li>• Unlimited Mikrtik Routers</li>
                            <li>• Monthly billing & expiry control</li>
                            <li>• Always-on internet access</li>
                            <li>• MikroTik & ISP-ready</li>
                            <li>• Multiple Payment Gateways</li>
                            <li>• Multiple SMS Gateways</li>
                            <li>• Instant connection on payments</li>
                        </ul>
                    </div>

                    <!-- Hotspot -->
                    <div class="flex-1 p-6 bg-white/80 dark:bg-gray-800/80 backdrop-blur-md rounded-2xl border border-white/50 dark:border-gray-700/50 shadow-lg">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1">
                            Hotspot Plan for <span class="text-blue-500">{{ selectedCountry.name }}</span>
                        </h3>

                        <div class="bg-blue-100 dark:bg-gray-700 mb-4 pb-4 rounded-xl">
                            <p class="text-gray-800 dark:text-gray-200">
                                <span class="text-xs uppercase text-gray-500">Price per active user</span><br>
                                <span class="font-mono font-semibold text-emerald-600">
                                    {{ selectedCountry.hotspotPricePerMonth }}
                                </span>
                                {{ selectedCountry.currency || 'USD' }} / transaction
                            </p>
                        </div>

                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Best for public Wi-Fi & pay-as-you-go access
                        </p>

                        <ul class="px-6 space-y-2 text-md text-gray-700 dark:text-gray-300 mb-4 text-left">
                            <li>• Unlimited MikroTik routers</li>
                            <li>• Pay-as-you-go access</li>
                            <li>• STK Push & voucher login</li>
                            <li>• Session-based billing</li>
                            <li>• Instant connection on payments</li>
                            <li>• Self-care customer portal</li>
                            <li>• Multiple payment gateways</li>
                            <li>• Multiple SMS gateways</li>
                            <li>• No limits on revenue</li>
                        </ul>

                    </div>

                </div>


            </div>
        </section>
    </div>
</template>

<style scoped>
/* Smooth fade-in animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes gradient {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

.animate-fade-in {
    animation: fadeIn 0.8s ease-out forwards;
    opacity: 0;
}

.animate-fade-in-up {
    animation: fadeInUp 0.8s ease-out forwards;
    opacity: 0;
}

.animate-gradient {
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}
</style>
