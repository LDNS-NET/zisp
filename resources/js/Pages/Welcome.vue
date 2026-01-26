<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import WelcomeLayout from '@/Layouts/WelcomeLayout.vue';
import Modal from '@/Components/Modal.vue';
import { CheckCircle2, X } from 'lucide-vue-next';

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
    countries: {
        type: Array,
        default: () => [],
    },
});

defineOptions({
    layout: WelcomeLayout,
});

//find selected country and apply prices
const selectedCountryCode = ref('KE'); // Default to Kenya

const selectedCountry = computed(() => {
    return (
        props.countries.find(c => c.code === selectedCountryCode.value) || {
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
            'Accept payments via M-Pesa, Airtel Money, MTN MoMo, Paystack, and Flutterwave with instant reconciliation. Enable auto-receipts and reduce manual financial tasks.',
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
            'Invoices, reminders, and mobile money payment verification happen automatically. Users are notified instantly.',
    },
    {
        step: '4',
        title: 'Monitor, Optimize & Grow',
        description:
            'Track user sessions, enforce data limits, receive router uptime alerts, and make data-driven decisions.',
    },
];

const showDemoModal = ref(false)
const showOnboardingModal = ref(false)
const showSuccessToast = ref(false)

const goToDemo = () => {
    window.open('https://demo.zispbilling.cloud/login', '_blank')
}

const onboardingForm = useForm({
    name: '',
    email: '',
    isp_name: '',
    country: '',
    message: '',
});

const submitOnboarding = () => {
    onboardingForm.post(route('onboarding-requests.store'), {
        onSuccess: () => {
            showOnboardingModal.value = false;
            onboardingForm.reset();
            showSuccessToast.value = true;
            setTimeout(() => {
                showSuccessToast.value = false;
            }, 5000);
        },
    });
};

onMounted(() => {
    // Attempt to detect user's country via IP
    fetch('https://ipapi.co/json/')
        .then(response => response.json())
        .then(data => {
            if (data.country_code) {
                const countryExists = props.countries.some(c => c.code === data.country_code);
                if (countryExists) {
                    selectedCountryCode.value = data.country_code;
                }
            }
        })
        .catch(error => {
            console.error('Error detecting country:', error);
        });
});

</script>

<template>
    <Head>
        <title>Mfire ISP Manager | Best ISP Management & Billing System</title>
        <meta name="description" content="Mfire Enterprises is the leading ISP management and billing system. Automate M-Pesa payments, manage Mikrotik routers, PPPoE, Hotspot vouchers, and invoices." />
        <meta name="keywords" content="isp billing system, mikrotik hotspot management, pppoe billing, kenya isp software, mpesa integration, radius billing, wifi management software, mfire enterprises, mwalinfire" />
        <meta property="og:title" content="Mfire Enterprises | Automated ISP Billing & Management" />
        <meta property="og:description" content="Manage your ISP entirely from one dashboard. Mikrotik integration, M-Pesa payments, SMS breakdown, and more." />
        <meta property="og:type" content="website" />
        <meta property="text:json-ld">
            {{
                JSON.stringify({
                    "@context": "https://schema.org",
                    "@type": "SoftwareApplication",
                    "name": "Mfire ISP Manager",
                    "applicationCategory": "BusinessApplication",
                    "operatingSystem": "Web",
                    "offers": {
                        "@type": "Offer",
                        "price": "0",
                        "priceCurrency": "KES"
                    },
                    "description": "Comprehensive ISP management software for automated billing, bandwidth control, and payment processing.",
                    "aggregateRating": {
                        "@type": "AggregateRating",
                        "ratingValue": "4.8",
                        "ratingCount": "100"
                    }
                })
            }}
        </meta>
    </Head>

    <!-- Success Toast -->
    <Transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="showSuccessToast" class="fixed top-5 right-5 z-[100] max-w-sm w-full bg-orange-500/90 backdrop-blur-xl border border-orange-400/50 rounded-2xl shadow-2xl p-4 flex items-center gap-4 text-white">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                <CheckCircle2 class="w-6 h-6" />
            </div>
            <div>
                <p class="font-bold">Request Sent!</p>
                <p class="text-sm text-orange-50/90">We've received your request and will contact you soon.</p>
            </div>
            <button @click="showSuccessToast = false" class="ml-auto text-orange-100 hover:text-white">
                <X class="w-5 h-5" />
            </button>
        </div>
    </Transition>

    <div class="min-h-screen relative overflow-hidden">
        <!-- Hero Section -->
        <div class="relative overflow-hidden">
            <!-- Soft background accents (layout handles main bg) -->
            <div class="relative mx-auto flex max-w-7xl w-full flex-col-reverse items-center gap-20 pt-24 lg:flex-row px-4 sm:px-6 lg:px-8">

                <!-- LEFT: Copy -->
                <div class="max-w-2xl text-center lg:text-left flex-1 animate-fade-in">
                    <!-- Badge -->
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full bg-white/5 px-4 py-2 text-sm font-semibold text-orange-400 backdrop-blur border border-white/10 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span>
                        ✨ Top ISP Management Platform in Africa
                    </div>

                    <h1 class="mb-6 text-5xl sm:text-6xl lg:text-7xl font-extrabold leading-tight text-white">
                        Empower Your ISP with
                        <span class="block mt-2 bg-gradient-to-r from-orange-400 via-red-400 to-amber-400 bg-clip-text text-transparent">
                            Mfire ISP Manager
                        </span>
                    </h1>

                    <p class="mt-6 text-xl text-gray-300 max-w-xl">
                        Billing, bandwidth control, payments, and customer management —
                        built specifically for African ISPs using MikroTik.
                        <span class="block mt-2 text-sm text-gray-400">Powered by Mfire Enterprises</span>
                    </p>

                    <!-- CTA -->
                    <div class="mt-10 flex flex-col gap-4 sm:flex-row lg:justify-start justify-center">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-orange-600 to-red-600 px-8 py-4 font-bold text-white shadow-xl hover:scale-105 transition hover:shadow-orange-500/20"
                        >
                            Start Free Trial →
                        </Link>

                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="inline-flex items-center justify-center rounded-xl border border-white/20 px-8 py-4 font-bold text-orange-400 hover:bg-white/5 transition"
                        >
                            Log In
                        </Link>
                    </div>

                    <!-- Trust stats -->
                    <div class="mt-14 grid grid-cols-3 gap-6 max-w-lg mx-auto lg:mx-0">
                        <div class="rounded-xl bg-white/5 p-4 text-center backdrop-blur border border-white/10">
                            <div class="text-2xl font-bold text-orange-400">100+</div>
                            <p class="text-xs text-gray-400">Active ISPs</p>
                        </div>
                        <div class="rounded-xl bg-white/5 p-4 text-center backdrop-blur border border-white/10">
                            <div class="text-2xl font-bold text-red-400">99.9%</div>
                            <p class="text-xs text-gray-400">Uptime</p>
                        </div>
                        <div class="rounded-xl bg-white/5 p-4 text-center backdrop-blur border border-white/10">
                            <div class="text-2xl font-bold text-amber-400">24/7</div>
                            <p class="text-xs text-gray-400">Support</p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Visual feature card -->
                <div class="flex-1 max-w-lg w-full">
                    <div class="relative rounded-3xl bg-white/5 backdrop-blur-xl border border-white/10 shadow-2xl p-8">
                        <!-- Decorative top bar -->
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 via-red-500 to-amber-500 rounded-t-3xl"></div>
                        
                        <h3 class="text-lg font-bold text-white mb-6">
                            Everything your ISP needs
                        </h3>

                        <ul class="space-y-4 text-gray-300">
                            <li class="flex items-center gap-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs">✓</span>
                                PPPoE & Hotspot billing
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs">✓</span>
                                MikroTik router management
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs">✓</span>
                                Payment automation
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs">✓</span>
                                SMS & customer notifications
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs">✓</span>
                                Real-time usage & analytics
                            </li>
                        </ul>

                        <div class="mt-6 rounded-xl bg-orange-500/10 p-4 text-sm text-orange-300 border border-orange-500/20">
                            Designed for scale - from small to large Deployements.
                        </div>
                    </div>
                </div>

            </div>
        </div>


        <!-- Feature Grid -->
        <section id="features" class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl font-bold text-white mb-4 tracking-tight">
                    Core Features
                </h2>
                <p class="text-lg text-gray-400 max-w-2xl mx-auto font-light">
                    Everything you need to run a modern, scalable ISP business
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="(feature, idx) in [
                    { icon: '🔗', title: 'Mikrotik API Control', desc: 'Suspend, resume, throttle, and monitor users directly via RouterOS API.' },
                    { icon: '💳', title: 'Payment Reconciliation', desc: 'Supports M-Pesa, Airtel, MTN, Paystack & more with automatic real-time updates.' },
                    { icon: '📄', title: 'Automated Invoicing', desc: 'Digital invoices, reminders, and receipts via SMS & email.' },
                    { icon: '👥', title: 'Self-Service Portal', desc: 'Customers manage renewals, usage, and support anytime.' },
                ]" :key="idx" class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-xl p-8 shadow-lg border border-white/10 transition-all duration-500 hover:shadow-2xl hover:scale-105 hover:-translate-y-3 cursor-default hover:bg-white/10">
                    <div class="absolute inset-0 bg-gradient-to-br from-orange-500/0 to-red-500/0 group-hover:from-orange-500/10 group-hover:to-red-500/5 transition-all duration-500"></div>
                    <div class="relative">
                        <div class="text-5xl mb-4 transition-transform duration-300 group-hover:scale-125 group-hover:rotate-6">{{ feature.icon }}</div>
                        <h3 class="text-lg font-bold text-white mb-3 tracking-tight">{{ feature.title }}</h3>
                        <p class="text-gray-400 text-sm leading-relaxed group-hover:text-gray-300 transition-colors">{{ feature.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section id="how-it-works" class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl font-bold text-white mb-4 tracking-tight">
                    How Mfire Enterprises Works
                </h2>
                <p class="text-lg text-gray-400 max-w-2xl mx-auto font-light">
                    Get up and running in minutes, not weeks
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                <div v-for="(step, idx) in howItWorksSteps" :key="step.step" class="group relative" :style="{ animationDelay: idx * 100 + 'ms' }">
                    <!-- Connection Line -->
                    <div v-if="step.step !== '4'" class="hidden lg:block absolute top-24 left-full w-1/4 h-1 bg-gradient-to-r from-orange-500/40 via-red-500/40 to-transparent"></div>

                    <div class="relative flex flex-col items-center rounded-2xl bg-white/5 backdrop-blur-xl p-8 shadow-lg border border-white/10 transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 h-full group hover:bg-white/10">
                        <!-- Step Number -->
                        <div class="absolute -top-6 left-1/2 -translate-x-1/2 h-12 w-12 rounded-full bg-gradient-to-br from-orange-600 to-red-600 flex items-center justify-center text-white font-bold text-lg shadow-lg transition-transform duration-300 group-hover:scale-110 border border-white/20">
                            {{ step.step }}
                        </div>

                        <h3 class="mt-8 text-lg font-bold text-white text-center mb-3 tracking-tight">
                            {{ step.title }}
                        </h3>
                        <p class="text-sm text-gray-400 text-center leading-relaxed group-hover:text-gray-300 transition-colors">
                            {{ step.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Advanced Mikrotik Features -->
        <section id="advanced" class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl font-bold text-white mb-4 tracking-tight">
                    Advanced Mikrotik Features
                </h2>
                <p class="text-lg text-gray-400 max-w-2xl mx-auto font-light">
                    Enterprise-grade router management at your fingertips
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2">
                <!-- Real-Time User Control -->
                <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-xl p-8 border border-white/10 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 hover:border-orange-500/50">
                    <div class="absolute inset-0 bg-gradient-to-br from-orange-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-orange-500/10 mb-4 transition-transform duration-300 group-hover:scale-110 border border-orange-500/20">
                            <span class="text-2xl">⚡</span>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 tracking-tight">Real-Time User Control</h3>
                        <p class="text-gray-400 leading-relaxed group-hover:text-gray-300">Suspend and reconnect users instantly. Push updated queue rules or credentials without manual router login.</p>
                    </div>
                </div>

                <!-- Hotspot Voucher Management -->
                <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-xl p-8 border border-white/10 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 hover:border-green-500/50">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-green-500/10 mb-4 transition-transform duration-300 group-hover:scale-110 border border-green-500/20">
                            <span class="text-2xl">🎟️</span>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 tracking-tight">Hotspot Voucher Management</h3>
                        <p class="text-gray-400 leading-relaxed group-hover:text-gray-300">Generate, assign, and validate vouchers. Monitor session time and device count live from dashboard.</p>
                    </div>
                </div>

                <!-- Usage-Based Throttling -->
                <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-xl p-8 border border-white/10 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 hover:border-amber-500/50">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-amber-500/10 mb-4 transition-transform duration-300 group-hover:scale-110 border border-amber-500/20">
                            <span class="text-2xl">📊</span>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 tracking-tight">Usage-Based Throttling</h3>
                        <p class="text-gray-400 leading-relaxed group-hover:text-gray-300">Apply caps and reset limits based on GB used or time online. Automatically throttle after limits exceeded.</p>
                    </div>
                </div>

                <!-- Router Monitoring -->
                <div class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-xl p-8 border border-white/10 shadow-lg transition-all duration-500 hover:shadow-2xl hover:-translate-y-3 hover:border-amber-500/50">
                    <div class="absolute inset-0 bg-gradient-to-br from-amber-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-amber-500/10 mb-4 transition-transform duration-300 group-hover:scale-110 border border-amber-500/20">
                            <span class="text-2xl">📡</span>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-3 tracking-tight">Router Monitoring & Alerts</h3>
                        <p class="text-gray-400 leading-relaxed group-hover:text-gray-300">Track CPU load, uptime, and online status. Get notified on disconnects or unusual bandwidth spikes in real time.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why Choose Us -->
        <section class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center mb-16">
                <h2 class="text-5xl sm:text-6xl font-bold text-white mb-4 tracking-tight">
                    Why Mfire Enterprises is the Smart Choice
                </h2>
                <p class="text-lg text-gray-400 max-w-2xl mx-auto font-light">
                    Trusted by the leading ISPs across Africa
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div v-for="(feature, idx) in detailedFeatures" :key="feature.title" class="group relative overflow-hidden rounded-2xl bg-white/5 backdrop-blur-xl p-8 shadow-lg border border-white/10 transition-all duration-500 hover:shadow-2xl hover:-translate-y-2 hover:bg-white/10" :style="{ transitionDelay: idx * 50 + 'ms' }">
                    <div class="absolute inset-0 bg-gradient-to-br from-orange-500/0 to-red-500/0 group-hover:from-orange-500/5 group-hover:to-red-500/5 transition-all duration-500"></div>
                    
                    <div class="relative flex items-start gap-4">
                        <div class="text-4xl flex-shrink-0 mt-1 transition-transform duration-300 group-hover:scale-125 group-hover:-rotate-6">{{ feature.icon }}</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-white mb-2 tracking-tight">
                                {{ feature.title }}
                            </h3>
                            <p class="text-gray-400 text-sm leading-relaxed group-hover:text-gray-300">
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Global Reach & Payment Diversity -->
        <section class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="grid gap-16 lg:grid-cols-2 items-center">
                <!-- Global Reach -->
                <div class="relative group">
                    <div class="absolute -inset-4 bg-gradient-to-r from-orange-500/10 to-red-500/10 rounded-3xl blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative rounded-3xl bg-white/5 backdrop-blur-xl border border-white/10 p-8 sm:p-12 shadow-2xl">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-orange-500/10 mb-8 border border-orange-500/20">
                            <span class="text-3xl">🌍</span>
                        </div>
                        <h2 class="text-4xl font-bold text-white mb-6 tracking-tight">Global Reach</h2>
                        <p class="text-lg text-gray-300 mb-8 leading-relaxed">
                            Mfire Enterprises is designed for the global market. We currently support multiple countries across Africa and are rapidly expanding.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <div v-for="country in countries.slice(0, 6)" :key="country.code" class="px-4 py-2 rounded-full bg-white/5 border border-white/10 text-sm text-gray-300 flex items-center gap-2">
                                <span class="text-lg">{{ country.flag || '📍' }}</span>
                                {{ country.name }}
                            </div>
                            <button 
                                @click="showOnboardingModal = true"
                                class="px-4 py-2 rounded-full bg-orange-500/20 border border-orange-500/40 text-sm text-orange-400 hover:bg-orange-500/30 transition-colors"
                            >
                                + Request Your Country
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Payment Diversity -->
                <div class="relative group">
                    <div class="absolute -inset-4 bg-gradient-to-r from-amber-500/10 to-orange-500/10 rounded-3xl blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative rounded-3xl bg-white/5 backdrop-blur-xl border border-white/10 p-8 sm:p-12 shadow-2xl">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-500/10 mb-8 border border-amber-500/20">
                            <span class="text-3xl">💳</span>
                        </div>
                        <h2 class="text-4xl font-bold text-white mb-6 tracking-tight">Payment Diversity</h2>
                        <p class="text-lg text-gray-300 mb-8 leading-relaxed">
                            We integrate with the most popular payment gateways in each region, ensuring your customers can pay with ease.
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <div v-for="gateway in ['M-Pesa', 'Airtel Money', 'MTN MoMo', 'Paystack', 'Flutterwave', 'Orange Money']" :key="gateway" class="p-4 rounded-xl bg-white/5 border border-white/10 text-center hover:bg-white/10 transition-colors cursor-default">
                                <span class="text-sm font-semibold text-gray-300">{{ gateway }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Onboarding Request Modal -->
        <Modal :show="showOnboardingModal" @close="showOnboardingModal = false">
            <div class="p-8 bg-gray-900 rounded-2xl border border-white/10">
                <h3 class="text-2xl font-bold text-white mb-2">Request Onboarding</h3>
                <p class="text-sm text-gray-400 mb-8">
                    Don't see your country? Tell us about your ISP, and we'll work on bringing Mfire Enterprises to your region.
                </p>

                <form @submit.prevent="submitOnboarding" class="space-y-6">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Full Name</label>
                            <input 
                                v-model="onboardingForm.name"
                                type="text" 
                                required
                                class="w-full rounded-xl bg-white/5 border-white/10 text-white focus:border-orange-500 focus:ring-orange-500"
                                placeholder="Michael The Dev"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                            <input 
                                v-model="onboardingForm.email"
                                type="email" 
                                required
                                class="w-full rounded-xl bg-white/5 border-white/10 text-white focus:border-orange-500 focus:ring-orange-500"
                                placeholder="mikethedev@gmail.com"
                            />
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">ISP Name</label>
                            <input 
                                v-model="onboardingForm.isp_name"
                                type="text" 
                                required
                                class="w-full rounded-xl bg-white/5 border-white/10 text-white focus:border-orange-500 focus:ring-orange-500"
                                placeholder="Your ISP Name"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Country</label>
                            <input 
                                v-model="onboardingForm.country"
                                type="text" 
                                required
                                class="w-full rounded-xl bg-white/5 border-white/10 text-white focus:border-orange-500 focus:ring-orange-500"
                                placeholder="Your Country"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Message (Optional)</label>
                        <textarea 
                            v-model="onboardingForm.message"
                            rows="4"
                            class="w-full rounded-xl bg-white/5 border-white/10 text-white focus:border-orange-500 focus:ring-orange-500"
                            placeholder="Tell us more about your needs..."
                        ></textarea>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button 
                            type="button"
                            @click="showOnboardingModal = false"
                            class="flex-1 px-6 py-3 rounded-xl border border-white/10 text-gray-300 font-semibold hover:bg-white/5 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            :disabled="onboardingForm.processing"
                            class="flex-1 px-6 py-3 rounded-xl bg-gradient-to-r from-orange-600 to-red-600 text-white font-bold shadow-lg hover:scale-105 transition disabled:opacity-50"
                        >
                            {{ onboardingForm.processing ? 'Submitting...' : 'Submit Request' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- CTA -->
        <section id="demo" class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-24">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-orange-600 via-red-600 to-amber-600 p-12 sm:p-16 lg:p-20 shadow-2xl border border-white/20">
                <div class="relative text-center">
                    <h2 class="text-4xl sm:text-5xl font-bold text-white mb-6">
                        Ready to Supercharge Your ISP?
                    </h2>

                    <p class="text-lg text-orange-100 mb-10 max-w-2xl mx-auto">
                        Experience Mfire Enterprises with a live demo environment.
                    </p>

                    <button
                        @click="showDemoModal = true"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-8 py-4 text-base font-semibold text-orange-700 shadow-lg hover:bg-orange-50 transition"
                    >
                        View Live Demo
                    </button>
                </div>
            </div>

            

            <!-- Demo Modal -->
            <div
                v-if="showDemoModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
            >
                <div class="w-full max-w-md rounded-2xl bg-gray-900 p-8 shadow-2xl border border-white/10">
                    <h3 class="text-2xl font-semibold text-white mb-4">
                        Demo Access
                    </h3>

                    <p class="text-sm text-gray-400 mb-6">
                        Use the credentials below to explore the Mfire Enterprises demo system.
                    </p>

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between rounded-lg bg-gray-800 px-4 py-3 border border-white/5">
                            <span class="text-sm text-gray-500">Username</span>
                            <span class="font-mono font-semibold text-orange-400">demo</span>
                        </div>
                        <div class="flex justify-between rounded-lg bg-gray-800 px-4 py-3 border border-white/5">
                            <span class="text-sm text-gray-500">Password</span>
                            <span class="font-mono font-semibold text-orange-400">password</span>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button
                            @click="showDemoModal = false"
                            class="flex-1 rounded-lg border border-gray-700 px-4 py-3 text-sm font-medium text-gray-300 hover:bg-gray-800"
                        >
                            Close
                        </button>

                        <button
                            @click="goToDemo"
                            class="flex-1 rounded-lg bg-orange-600 px-4 py-3 text-sm font-semibold text-white hover:bg-orange-700"
                        >
                            Login to Demo
                        </button>
                    </div>
                </div>
            </div>
        </section>
        

        <section id="pricing" class="relative mt-20 pb-24">
    <div class="text-center border-t pt-12 border-white/10 px-4">
        <!-- Country selector -->
        <div class="animate-fade-in-up">
            <label
                for="country"
                class="block text-sm font-semibold text-gray-300 mb-3"
            >
                Select your country to view pricing
            </label>

            <select
                id="country"
                v-model="selectedCountryCode"
                class="mx-auto block w-full max-w-xs rounded-lg border-gray-700
                       bg-gray-800 px-4 py-3 text-gray-100
                       shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
            >
                <option
                    v-for="country in countries"
                    :key="country.code"
                    :value="country.code"
                >
                    {{ country.name }}
                </option>
            </select>
        </div>

        <!-- Pricing cards -->
        <div
            class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-6 max-w-5xl mx-auto
                   rounded-2xl border border-white/10
                   bg-white/5 backdrop-blur-md
                   p-4 sm:p-6 shadow-lg animate-fade-in-up delay-150"
        >
            <!-- PPPoE -->
            <div
                class="group p-6 bg-gray-900/50 rounded-2xl
                       border border-white/10
                       shadow-lg transition-all duration-300
                       hover:-translate-y-1 hover:shadow-2xl hover:border-orange-500/30"
            >
                <h3 class="text-lg sm:text-xl font-bold text-white">
                    PPPoE Plan
                    <span class="block text-sm font-medium text-orange-400">
                        {{ selectedCountry.name }}
                    </span>
                </h3>

                <div class="mt-4 rounded-xl bg-gray-800 p-4 text-center sm:text-left border border-white/5">
                    <span class="block text-xs uppercase tracking-wide text-amber-400">
                        Price per active user
                    </span>
                    <div class="mt-1 text-2xl font-mono font-bold text-orange-400">
                        {{ selectedCountry.pppoePricePerMonth }}
                        <span class="text-sm font-medium text-gray-500">
                            {{ selectedCountry.currency || 'KES' }} / month
                        </span>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-400">
                    Best for fiber & wireless ISP subscribers
                </p>

                <ul class="mt-6 space-y-2 text-sm text-gray-300 text-left">
                    <li>✔ Unlimited MikroTik routers</li>
                    <li>✔ Monthly billing & expiry control</li>
                    <li>✔ Always-on internet access</li>
                    <li>✔ ISP & MikroTik ready</li>
                    <li>✔ Multiple payment gateways</li>
                    <li>✔ Multiple SMS gateways</li>
                    <li>✔ Instant connection on payment</li>
                </ul>
            </div>

            <!-- Hotspot -->
            <div
                class="group p-6 bg-gray-900/50 rounded-2xl
                       border border-white/10
                       shadow-lg transition-all duration-300
                       hover:-translate-y-1 hover:shadow-2xl hover:border-orange-500/30"
            >
                <h3 class="text-lg sm:text-xl font-bold text-white">
                    Hotspot Plan
                    <span class="block text-sm font-medium text-orange-400">
                        {{ selectedCountry.name }}
                    </span>
                </h3>

                <div class="mt-4 rounded-xl bg-gray-800 p-4 text-center sm:text-left border border-white/5">
                    <span class="block text-xs uppercase tracking-wide text-amber-400">
                        Revenue based pricing
                    </span>
                    <div class="mt-1 text-2xl font-mono font-bold text-orange-400">
                        {{ selectedCountry.hotspotPricePerMonth }}
                        <span class="text-sm font-medium text-gray-500">
                            per transaction
                        </span>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-400">
                    Best for public Wi-Fi & pay-as-you-go access
                </p>

                <ul class="mt-6 space-y-2 text-sm text-gray-300 text-left">
                    <li>✔ Unlimited MikroTik routers</li>
                    <li>✔ Pay-as-you-go access</li>
                    <li>✔ STK Push & voucher login</li>
                    <li>✔ Session-based billing</li>
                    <li>✔ Instant connection on payment</li>
                    <li>✔ Customer self-care portal</li>
                    <li>✔ Multiple payment & SMS gateways</li>
                    <li>✔ No revenue limits</li>
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
.delay-150 {
    animation-delay: 150ms;
}

.animate-spin-slow {
    animation: spin 8s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
