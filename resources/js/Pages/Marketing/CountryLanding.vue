<script setup>
import { Head, Link } from '@inertiajs/vue3';
import WelcomeLayout from '@/Layouts/WelcomeLayout.vue';
import { MapPin, Globe, CreditCard, ArrowRight } from 'lucide-vue-next';

defineProps({
    seo: Object,
});

defineOptions({
    layout: WelcomeLayout,
});
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta name="description" :content="seo.description" />
        <meta name="keywords" :content="seo.keywords" />

        <!-- Canonical -->
        <link rel="canonical" :href="seo.canonical" />

        <!-- Open Graph -->
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="seo.title" />
        <meta property="og:description" :content="seo.description" />
        <meta property="og:url" :content="seo.canonical" />

        <!-- Twitter -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="seo.title" />
        <meta name="twitter:description" :content="seo.description" />
    </Head>

    <div class="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-24">
        <!-- Background Flag Effect (Subtle) -->
        <div class="absolute top-0 right-0 -mt-20 -mr-20 opacity-10 text-[20rem] pointer-events-none select-none blur-xl">
            {{ seo.country.flag }}
        </div>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center animate-fade-in-up">
            
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 mb-8">
                <Globe class="w-4 h-4" />
                <span>#1 ISP Software in {{ seo.country.name }}</span>
            </div>

            <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight mb-8">
                {{ seo.hero_title }}
            </h1>
            <p class="text-xl text-gray-300 max-w-3xl mx-auto mb-12">
                {{ seo.hero_subtitle }}
            </p>

            <div class="flex flex-col sm:flex-row justify-center gap-4 mb-20">
                <Link :href="route('register')" class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-8 py-4 text-lg font-bold text-white shadow-xl hover:scale-105 transition hover:shadow-emerald-500/20">
                    Start {{ seo.country.name }} Trial
                    <ArrowRight class="ml-2 w-5 h-5" />
                </Link>
                <Link :href="route('login')" class="inline-flex items-center justify-center rounded-xl border border-white/20 px-8 py-4 text-lg font-bold text-emerald-400 hover:bg-white/5 transition">
                    Explore Demo
                </Link>
            </div>

            <!-- Country Specific Features -->
            <div class="grid gap-6 md:grid-cols-3 text-left">
                <!-- Local Payments -->
                <div class="p-8 rounded-2xl bg-white/5 border border-white/10 backdrop-blur hover:bg-white/10 transition group">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-400 mb-6 group-hover:scale-110 transition">
                        <CreditCard class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Local Payments</h3>
                    <p class="text-gray-400 text-sm">Accept payments directly via <span class="text-white font-semibold">{{ seo.country.payment_methods }}</span>. No complex setups needed.</p>
                </div>

                <!-- Currency Support -->
                <div class="p-8 rounded-2xl bg-white/5 border border-white/10 backdrop-blur hover:bg-white/10 transition group">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center text-blue-400 mb-6 group-hover:scale-110 transition">
                        <span class="text-xl font-bold">{{ seo.country.currency }}</span>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">{{ seo.country.currency }} Billing</h3>
                    <p class="text-gray-400 text-sm">Invoice and receipt your customers in {{ seo.country.currency }}. Tax compliant for {{ seo.country.name }} businesses.</p>
                </div>

                <!-- Regional Servers -->
                <div class="p-8 rounded-2xl bg-white/5 border border-white/10 backdrop-blur hover:bg-white/10 transition group">
                    <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center text-purple-400 mb-6 group-hover:scale-110 transition">
                        <MapPin class="w-6 h-6" />
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Optimized Latency</h3>
                    <p class="text-gray-400 text-sm">Fast Cloud servers optimized for ISP traffic in the East & West African regions.</p>
                </div>
            </div>

            <div class="mt-24 border-t border-white/10 pt-16">
                <h2 class="text-3xl font-bold text-white mb-8">Trusted by ISPs in {{ seo.country.name }}</h2>
                <div class="flex flex-wrap justify-center gap-8 opacity-50 grayscale hover:grayscale-0 transition-all duration-500">
                    <div class="text-2xl font-bold text-white">MikroTik</div>
                    <div class="text-2xl font-bold text-white">Ubiquiti</div>
                    <div class="text-2xl font-bold text-white">Cambium</div>
                    <div class="text-2xl font-bold text-white">TP-Link</div>
                </div>
            </div>
        </div>
    </div>
</template>
