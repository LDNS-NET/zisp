<script setup>
import { Head, Link } from '@inertiajs/vue3';
import WelcomeLayout from '@/Layouts/WelcomeLayout.vue';
import { CheckCircle2, ArrowRight } from 'lucide-vue-next';

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
        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center animate-fade-in-up">
            <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight mb-8">
                {{ seo.hero_title }}
            </h1>
            <p class="text-xl text-gray-300 max-w-3xl mx-auto mb-12">
                {{ seo.hero_subtitle }}
            </p>

            <div class="flex flex-col sm:flex-row justify-center gap-4 mb-20">
                <Link :href="route('register')" class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-8 py-4 text-lg font-bold text-white shadow-xl hover:scale-105 transition hover:shadow-emerald-500/20">
                    Get Started Free
                    <ArrowRight class="ml-2 w-5 h-5" />
                </Link>
                <Link :href="route('login')" class="inline-flex items-center justify-center rounded-xl border border-white/20 px-8 py-4 text-lg font-bold text-emerald-400 hover:bg-white/5 transition">
                    View Demo
                </Link>
            </div>

            <!-- Features Grid -->
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3 text-left">
                <div v-for="(feature, index) in seo.features" :key="index" class="p-8 rounded-2xl bg-white/5 border border-white/10 backdrop-blur hover:bg-white/10 transition">
                    <CheckCircle2 class="text-emerald-400 w-10 h-10 mb-4" />
                    <h3 class="text-xl font-bold text-white mb-2">{{ feature }}</h3>
                    <p class="text-gray-400 text-sm">Automate your ISP operations with our specialized tools designed for efficiency.</p>
                </div>
            </div>
            
            <div class="mt-20 p-8 rounded-3xl bg-gradient-to-br from-gray-900 to-gray-800 border border-white/10">
                <h2 class="text-3xl font-bold text-white mb-6">Why ZISP Billing?</h2>
                <p class="text-gray-300 max-w-4xl mx-auto">
                    We understand the unique challenges of African ISPs. From reliable offline-first architecture to seamless mobile money integrations, ZISP is built to help you scale.
                </p>
            </div>
        </div>
    </div>
</template>
