<script setup>
import { Head } from '@inertiajs/vue3';
import { ShieldAlert, Phone, Mail, MessageSquare } from 'lucide-vue-next';

defineProps({
    tenant: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <Head title="Service Suspended" />

    <div class="min-h-screen bg-slate-50 flex flex-col items-center justify-center p-6 font-sans">
        <div class="max-w-md w-full bg-white rounded-3xl shadow-xl overflow-hidden border border-slate-100">
            <!-- Header/Logo Area -->
            <div class="bg-slate-900 p-8 flex flex-col items-center text-center">
                <div v-if="tenant.logo" class="mb-6">
                    <img :src="tenant.logo" :alt="tenant.name" class="h-16 w-auto object-contain filter brightness-0 invert" />
                </div>
                <div v-else class="w-16 h-16 bg-amber-500 rounded-2xl flex items-center justify-center mb-6 shadow-lg shadow-amber-500/20">
                    <ShieldAlert class="w-10 h-10 text-white" />
                </div>
                
                <h1 class="text-2xl font-bold text-white mb-2">Service Temporarily Unavailable</h1>
                <p class="text-slate-400 text-sm">Account: {{ tenant.name }}</p>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <div class="bg-amber-50 border border-amber-100 rounded-2xl p-5 mb-8">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <ShieldAlert class="w-6 h-6 text-amber-600" />
                        </div>
                        <div>
                            <h3 class="text-amber-900 font-semibold mb-1">Subscription Expired</h3>
                            <p class="text-amber-800/80 text-sm leading-relaxed">
                                The internet service for this location has been suspended due to an expired subscription. Please contact the system administrator to restore access.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h4 class="text-slate-900 font-bold text-sm uppercase tracking-wider mb-4">Contact Administrator</h4>
                    
                    <a v-if="tenant.support_phone" :href="'tel:' + tenant.support_phone" 
                       class="flex items-center p-4 bg-slate-50 hover:bg-slate-100 rounded-2xl transition-all group border border-transparent hover:border-slate-200">
                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm mr-4 group-hover:scale-110 transition-transform">
                            <Phone class="w-5 h-5 text-slate-600" />
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-slate-500 font-medium">Call Support</p>
                            <p class="text-slate-900 font-bold">{{ tenant.support_phone }}</p>
                        </div>
                    </a>

                    <a v-if="tenant.support_email" :href="'mailto:' + tenant.support_email"
                       class="flex items-center p-4 bg-slate-50 hover:bg-slate-100 rounded-2xl transition-all group border border-transparent hover:border-slate-200">
                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm mr-4 group-hover:scale-110 transition-transform">
                            <Mail class="w-5 h-5 text-slate-600" />
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-slate-500 font-medium">Email Support</p>
                            <p class="text-slate-900 font-bold truncate">{{ tenant.support_email }}</p>
                        </div>
                    </a>

                    <div v-if="!tenant.support_phone && !tenant.support_email" class="text-center py-4">
                        <p class="text-slate-500 text-sm italic">Please contact your local network provider for assistance.</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-slate-50 p-6 border-t border-slate-100 text-center">
                <p class="text-slate-400 text-xs font-medium">Powered by ZISP Management System</p>
            </div>
        </div>
        
        <p class="mt-8 text-slate-400 text-sm">
            &copy; {{ new Date().getFullYear() }} {{ tenant.name }}. All rights reserved.
        </p>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');

.font-sans {
    font-family: 'Outfit', sans-serif;
}
</style>
