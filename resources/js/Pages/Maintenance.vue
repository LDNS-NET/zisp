<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: String,
    support_email: String,
    support_phone: String,
});
</script>

<template>
    <Head title="System Maintenance" />

    <div class="min-h-screen relative overflow-hidden flex items-center justify-center bg-gray-900">
        <!-- Background Effects -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0">
            <div class="absolute -top-[30%] -left-[10%] w-[70%] h-[70%] rounded-full bg-orange-500/20 blur-[120px] animate-pulse"></div>
            <div class="absolute top-[20%] -right-[10%] w-[60%] h-[60%] rounded-full bg-red-500/20 blur-[100px] animate-pulse delay-700"></div>
            <div class="absolute -bottom-[20%] left-[20%] w-[50%] h-[50%] rounded-full bg-amber-500/20 blur-[100px] animate-pulse delay-1000"></div>
        </div>

        <!-- Content Card -->
        <div class="relative z-10 w-full max-w-lg p-8 mx-4">
            <div class="relative overflow-hidden rounded-3xl bg-white/10 backdrop-blur-xl border border-white/20 shadow-2xl">
                <!-- Decorative top bar -->
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-orange-500 via-red-500 to-amber-500"></div>

                <div class="p-8 sm:p-12 text-center">
                    <!-- Animated Icon -->
                    <div class="relative mx-auto w-24 h-24 mb-8 group">
                        <div class="absolute inset-0 bg-orange-500/30 rounded-full blur-xl group-hover:bg-orange-500/50 transition-all duration-500"></div>
                        <div class="relative flex items-center justify-center w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 rounded-full border border-white/10 shadow-inner">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-orange-400 animate-spin-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <!-- Status Dot -->
                        <div class="absolute bottom-1 right-1 w-6 h-6 bg-gray-900 rounded-full flex items-center justify-center">
                            <div class="w-3 h-3 bg-orange-500 rounded-full animate-pulse"></div>
                        </div>
                    </div>

                    <!-- Main Heading -->
                    <h1 class="text-3xl sm:text-4xl font-bold text-white mb-3 tracking-tight">
                        System Operational
                    </h1>
                    
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-500/10 border border-orange-500/20 mb-6">
                        <span class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span>
                        <span class="text-xs font-medium text-orange-400 uppercase tracking-wider">Maintenance in Progress</span>
                    </div>

                    <!-- Message -->
                    <p class="text-lg text-gray-300 leading-relaxed mb-8">
                        We're currently upgrading our systems to serve you better. 
                        <span class="block mt-2 text-white font-medium">
                            Your internet service remains fully active.
                        </span>
                    </p>

                    <!-- Support Section -->
                    <div class="pt-8 border-t border-white/10">
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-widest mb-4">
                            Need Urgent Assistance?
                        </p>
                        
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <a v-if="support_email" :href="'mailto:' + support_email" 
                               class="group flex items-center gap-3 px-5 py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 hover:border-orange-500/30 transition-all duration-300 w-full sm:w-auto justify-center">
                                <div class="p-2 rounded-lg bg-orange-500/10 text-orange-400 group-hover:scale-110 transition-transform">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-gray-300 group-hover:text-white">{{ support_email }}</span>
                            </a>

                            <a v-if="support_phone" :href="'tel:' + support_phone"
                               class="group flex items-center gap-3 px-5 py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 hover:border-orange-500/30 transition-all duration-300 w-full sm:w-auto justify-center">
                                <div class="p-2 rounded-lg bg-orange-500/10 text-orange-400 group-hover:scale-110 transition-transform">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-gray-300 group-hover:text-white">{{ support_phone }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer Text -->
                &copy; {{ new Date().getFullYear() }} Mfire Enterprises. All rights reserved.
        </div>
    </div>
</template>

<style scoped>
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
