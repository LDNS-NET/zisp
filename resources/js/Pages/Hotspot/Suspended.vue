import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { ShieldAlert, Phone, Mail, MessageSquare } from 'lucide-vue-next';

const props = defineProps({
    tenant: {
        type: Object,
        required: true
    },
    settings: {
        type: Object,
        default: () => ({})
    }
});

const currentTemplate = computed(() => props.settings?.portal_template || 'default');

const theme = computed(() => {
    const t = currentTemplate.value;
    if (t === 'modern-dark') {
        return {
            bg: 'bg-slate-950',
            card: 'bg-slate-900 border-slate-800 shadow-2xl',
            text: 'text-white',
            subtext: 'text-slate-400',
            accent: 'text-blue-400',
            accentBg: 'bg-blue-500/10',
            button: 'bg-blue-600',
            icon: 'text-blue-400',
            header: 'bg-slate-900/50'
        };
    }
    if (t === 'vibrant-gradient') {
        return {
            bg: 'bg-gradient-to-br from-indigo-600 to-pink-500',
            card: 'bg-white/95 border-transparent shadow-2xl',
            text: 'text-slate-900',
            subtext: 'text-slate-500',
            accent: 'text-indigo-600',
            accentBg: 'bg-indigo-50',
            button: 'bg-indigo-600',
            icon: 'text-indigo-600',
            header: 'bg-slate-900'
        };
    }
    if (t === 'glassmorphism') {
        return {
            bg: 'bg-[#0f172a] bg-[radial-gradient(ellipse_at_top_left,_var(--tw-gradient-stops))] from-blue-900 via-slate-900 to-black',
            card: 'backdrop-blur-xl bg-white/10 border border-white/10 shadow-2xl',
            text: 'text-white',
            subtext: 'text-blue-100/70',
            accent: 'text-blue-300',
            accentBg: 'bg-white/5',
            button: 'bg-white/20',
            icon: 'text-blue-300',
            header: 'bg-black/20'
        };
    }
    if (t === 'minimalist-clean') {
        return {
            bg: 'bg-white',
            card: 'bg-white border-slate-100 shadow-none',
            text: 'text-black',
            subtext: 'text-slate-500',
            accent: 'text-black',
            accentBg: 'bg-slate-50',
            button: 'bg-black',
            icon: 'text-black',
            header: 'bg-slate-50'
        };
    }
    return {
        bg: 'bg-slate-50',
        card: 'bg-white border-slate-100 shadow-xl',
        text: 'text-slate-900',
        subtext: 'text-slate-500',
        accent: 'text-blue-600',
        accentBg: 'bg-slate-50',
        button: 'bg-blue-600',
        icon: 'text-slate-600',
        header: 'bg-slate-900'
    };
});

<template>
    <Head title="Service Suspended" />

    <div :class="['min-h-screen flex flex-col items-center justify-center p-6 font-sans transition-all duration-700', theme.bg, theme.text]">
        <div :class="['max-w-md w-full rounded-3xl overflow-hidden border transition-all duration-700', theme.card]">
            <!-- Header/Logo Area -->
            <div :class="['p-8 flex flex-col items-center text-center transition-all duration-700', theme.header]">
                <div v-if="tenant.logo" class="mb-6">
                    <img :src="tenant.logo" :alt="tenant.name" :class="['h-16 w-auto object-contain', currentTemplate === 'default' ? 'filter brightness-0 invert' : '']" />
                </div>
                <div v-else :class="['w-16 h-16 rounded-2xl flex items-center justify-center mb-6 shadow-lg', currentTemplate === 'default' ? 'bg-amber-500 shadow-amber-500/20' : theme.accentBg]">
                    <ShieldAlert :class="['w-10 h-10', currentTemplate === 'default' ? 'text-white' : theme.accent]" />
                </div>
                
                <h1 :class="['text-2xl font-bold mb-2', theme.text]">Service Temporarily Unavailable</h1>
                <p :class="['text-sm', theme.subtext]">Account: {{ tenant.name }}</p>
            </div>

            <!-- Content Area -->
            <div class="p-8">
                <div :class="['rounded-2xl p-5 mb-8 border', currentTemplate === 'default' ? 'bg-amber-50 border-amber-100' : 'bg-white/5 border-white/10']">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0">
                            <ShieldAlert :class="['w-6 h-6', currentTemplate === 'default' ? 'text-amber-600' : theme.accent]" />
                        </div>
                        <div>
                            <h3 :class="['font-semibold mb-1', currentTemplate === 'default' ? 'text-amber-900' : theme.text]">Subscription Expired</h3>
                            <p :class="['text-sm leading-relaxed', currentTemplate === 'default' ? 'text-amber-800/80' : theme.subtext]">
                                The internet service for this location has been suspended due to an expired subscription. Please contact the system administrator to restore access.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h4 :class="['font-bold text-sm uppercase tracking-wider mb-4', theme.text]">Contact Administrator</h4>
                    
                    <a v-if="tenant.support_phone" :href="'tel:' + tenant.support_phone" 
                       :class="['flex items-center p-4 rounded-2xl transition-all group border border-transparent', currentTemplate === 'default' ? 'bg-slate-50 hover:bg-slate-100 hover:border-slate-200' : 'bg-white/5 hover:bg-white/10 border-white/5 hover:border-white/20']">
                        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shadow-sm mr-4 group-hover:scale-110 transition-transform', theme.accentBg]">
                            <Phone :class="['w-5 h-5', theme.accent]" />
                        </div>
                        <div class="flex-1">
                            <p :class="['text-xs font-medium', theme.subtext]">Call Support</p>
                            <p :class="['font-bold', theme.text]">{{ tenant.support_phone }}</p>
                        </div>
                    </a>

                    <a v-if="tenant.support_email" :href="'mailto:' + tenant.support_email"
                       :class="['flex items-center p-4 rounded-2xl transition-all group border border-transparent', currentTemplate === 'default' ? 'bg-slate-50 hover:bg-slate-100 hover:border-slate-200' : 'bg-white/5 hover:bg-white/10 border-white/5 hover:border-white/20']">
                        <div :class="['w-10 h-10 rounded-xl flex items-center justify-center shadow-sm mr-4 group-hover:scale-110 transition-transform', theme.accentBg]">
                            <Mail :class="['w-5 h-5', theme.accent]" />
                        </div>
                        <div class="flex-1">
                            <p :class="['text-xs font-medium', theme.subtext]">Email Support</p>
                            <p :class="['font-bold truncate', theme.text]">{{ tenant.support_email }}</p>
                        </div>
                    </a>

                    <div v-if="!tenant.support_phone && !tenant.support_email" class="text-center py-4">
                        <p :class="['text-sm italic', theme.subtext]">Please contact your local network provider for assistance.</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div :class="['p-6 border-t text-center', currentTemplate === 'default' ? 'bg-slate-50 border-slate-100' : 'bg-black/20 border-white/5']">
                <p :class="['text-xs font-medium', theme.subtext]">Powered by ZISP Management System</p>
            </div>
        </div>
        
        <p :class="['mt-8 text-sm', theme.subtext]">
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
