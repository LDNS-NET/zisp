<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

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
</script>

<template>
    <Head title="Zyraaf Cloud | ISP Manager" />

    <div
        class="flex min-h-screen flex-col justify-between border-8 bg-slate-200 dark:bg-black"
    >
        <!-- Hero Section -->
        <div
            class="mx-auto flex max-w-7xl flex-col-reverse items-center gap-12 pt-8 lg:flex-row"
        >
            <div class="max-w-2xl text-center lg:text-left">
                <h1
                    class="mb-4 text-4xl font-extrabold leading-tight text-gray-900 sm:text-5xl lg:text-6xl dark:text-white"
                >
                    Empower Your ISP with
                    <span class="text-blue-600 dark:text-blue-400">Zyraaf Cloud</span>
                </h1>
                <p
                    class="mt-6 text-lg leading-relaxed text-gray-700 dark:text-gray-300"
                >
                    An all-in-one billing, bandwidth, and customer experience
                    platform built for African ISPs. Seamless M-Pesa payments,
                    advanced Mikrotik controls, and automated invoicing – all
                    from one dashboard.
                </p>
                <div
                    class="mt-10 flex flex-col justify-center gap-4 sm:flex-row lg:justify-start"
                >
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="transform rounded-lg bg-blue-600 px-8 py-4 font-semibold text-white shadow-lg transition hover:scale-105 hover:bg-blue-700"
                        >Start Free Trial</Link
                    >
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="transform rounded-lg border-2 border-blue-600 bg-white px-8 py-4 font-semibold text-blue-600 shadow-lg transition hover:scale-105 hover:bg-blue-50 dark:border-blue-400 dark:bg-gray-800 dark:text-blue-400 dark:hover:bg-gray-700"
                        >Log In</Link
                    >
                </div>
            </div>
            <div class="max-w-24 flex-shrink-0">
                <img
                    src="/images/zyraisp.png"
                    alt="ZiSP Logo"
                    class="w-full rounded-2xl shadow-xl ring-2 ring-blue-200 dark:ring-blue-700"
                />
            </div>
        </div>

        <!-- Feature Grid -->
        <section
            class="mx-auto grid max-w-7xl gap-8 py-16 text-center sm:grid-cols-2 lg:grid-cols-4"
        >
            <div
                class="transform rounded-xl bg-[#F0F9FF] p-6 shadow-md transition hover:-translate-y-1 hover:shadow-lg dark:bg-gray-800"
            >
                <h3
                    class="mb-2 text-xl font-bold text-blue-700 dark:text-blue-300"
                >
                    Mikrotik API Control
                </h3>
                <p class="text-gray-700 dark:text-gray-400">
                    Suspend, resume, throttle, and monitor Mikrotik users
                    directly via secure RouterOS API.
                </p>
            </div>
            <div
                class="transform rounded-xl bg-[#FFF5F5] p-6 shadow-md transition hover:-translate-y-1 hover:shadow-lg dark:bg-gray-800"
            >
                <h3
                    class="mb-2 text-xl font-bold text-red-700 dark:text-red-300"
                >
                    Payment Reconciliation
                </h3>
                <p class="text-gray-700 dark:text-gray-400">
                    Supports STK Push, C2B, and B2C with automatic updates to
                    user accounts in real-time.
                </p>
            </div>
            <div
                class="transform rounded-xl bg-[#F3FDF3] p-6 shadow-md transition hover:-translate-y-1 hover:shadow-lg dark:bg-gray-800"
            >
                <h3
                    class="mb-2 text-xl font-bold text-green-700 dark:text-green-300"
                >
                    Automated Invoicing
                </h3>
                <p class="text-gray-700 dark:text-gray-400">
                    Send digital invoices, overdue alerts, and SMS/email
                    receipts without lifting a finger.
                </p>
            </div>
            <div
                class="transform rounded-xl bg-[#FEFAF1] p-6 shadow-md transition hover:-translate-y-1 hover:shadow-lg dark:bg-gray-800"
            >
                <h3
                    class="mb-2 text-xl font-bold text-yellow-700 dark:text-yellow-300"
                >
                    Self-Service Access
                </h3>
                <p class="text-gray-700 dark:text-gray-400">
                    Customers manage renewals, top-ups, usage, and support from
                    anywhere, anytime.
                </p>
            </div>
        </section>

        <!-- How It Works -->
        <section class="mx-auto max-w-7xl py-16">
            <h2
                class="mb-12 text-center text-4xl font-extrabold text-gray-900 dark:text-white"
            >
                How Zyraaf Cloud Works
            </h2>
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="step in howItWorksSteps"
                    :key="step.step"
                    class="group relative flex flex-col items-center rounded-xl border border-gray-100 bg-white p-6 text-center shadow-lg dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="absolute -top-6 left-1/2 flex h-12 w-12 -translate-x-1/2 transform items-center justify-center rounded-full border-4 border-white bg-blue-600 text-2xl font-bold text-white dark:border-gray-900"
                    >
                        {{ step.step }}
                    </div>
                    <h3
                        class="mb-2 mt-8 text-xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ step.title }}
                    </h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        {{ step.description }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Advanced Mikrotik Features -->
        <section class="py-10">
            <div class="mx-auto max-w-7xl px-4">
                <h2
                    class="mb-12 text-center text-4xl font-extrabold text-gray-900 dark:text-white"
                >
                    Advanced Mikrotik Features
                </h2>
                <div class="grid gap-12 md:grid-cols-2">
                    <div
                        class="rounded-xl border border-red-500 bg-white p-6 shadow-lg dark:border-x-green-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-2 text-xl font-semibold text-blue-700 dark:text-blue-300"
                        >
                            Real-Time User Control
                        </h3>
                        <p class="text-gray-700 dark:text-gray-300">
                            Suspend and reconnect users instantly. Push updated
                            queue rules or credentials without needing to log in
                            to the router manually.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-green-400 bg-white p-6 shadow-lg dark:border-blue-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-2 text-xl font-semibold text-blue-700 dark:text-blue-300"
                        >
                            Hotspot Voucher Management
                        </h3>
                        <p class="text-gray-700 dark:text-gray-300">
                            Generate, assign, and validate Mikrotik hotspot
                            vouchers. Monitor session time and device count live
                            from the dashboard.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-blue-400 bg-white p-6 shadow-lg dark:border-red-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-2 text-xl font-semibold text-blue-700 dark:text-blue-300"
                        >
                            Usage-Based Throttling
                        </h3>
                        <p class="text-gray-700 dark:text-gray-300">
                            Apply caps and reset limits based on GB used or time
                            online. Automatically throttle users after limits
                            are exceeded.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-cyan-500 bg-white p-6 shadow-lg dark:border-cyan-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-2 text-xl font-semibold text-blue-700 dark:text-blue-300"
                        >
                            Router Monitoring & Alerts
                        </h3>
                        <p class="text-gray-700 dark:text-gray-300">
                            Track CPU load, uptime, and online status. Get
                            notified on disconnects or unusual bandwidth spikes
                            in real time.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why Choose Us -->
        <section class="rounded-xl border border-blue-700 px-6">
            <div class="mx-auto max-w-7xl bg-gradient-to-r px-4 py-10">
                <h2
                    class="mb-12 text-center text-4xl font-extrabold dark:text-blue-500"
                >
                    Why Zyraaf Cloud is the Smart Choice
                </h2>
                <div class="grid gap-12 px-10 py-9 md:grid-cols-2">
                    <div
                        v-for="feature in detailedFeatures"
                        :key="feature.title"
                        class="flex items-start"
                    >
                        <div
                            class="mr-4 flex-shrink-0 text-3xl text-blue-600 dark:text-blue-400"
                        >
                            {{ feature.icon }}
                        </div>
                        <div>
                            <h3
                                class="mb-1 text-xl font-bold text-blue-500 dark:text-blue-900"
                            >
                                {{ feature.title }}
                            </h3>
                            <p
                                class="rounded-xl bg-gray-300 px-4 py-2 text-black dark:bg-gray-800 dark:text-white"
                            >
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section
            class="mx-auto max-w-7xl bg-gradient-to-r px-6 py-8 text-center sm:px-12 md:px-32 lg:px-48 xl:px-64"
        >
            <div
                class="rounded-xl border border-black px-6 py-4 dark:border-blue-500"
            >
                <h2
                    class="mb-6 text-3xl font-extrabold text-gray-900 sm:text-4xl dark:text-white"
                >
                    Ready to Supercharge Your ISP?
                </h2>
                <p
                    class="mb-8 text-lg text-gray-700 sm:text-xl dark:text-gray-300"
                >
                    Let Zyraaf Cloud help you automate billing, optimize networks,
                    and grow with confidence.
                </p>
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="inline-flex items-center justify-center rounded-lg bg-green-500 px-8 py-4 text-lg font-bold text-white shadow-xl transition-transform hover:scale-105 hover:bg-green-600 sm:text-xl"
                >
                    Get Started Free
                    <svg
                        class="ml-3 h-6 w-6"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10.293 15.707a1 1 0 010-1.414L14.586 10l-4.293-4.293a1 1 0 111.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer
            class="dark:text-gray-70 mt-24 p-10 py-4 text-center text-sm font-extrabold text-gray-500"
        >
            LDNS NETWORKS &copy; {{ new Date().getFullYear() }}. All rights
            reserved.
        </footer>
    </div>
</template>

<style scoped>
/* Scoped styles here if needed */
</style>
