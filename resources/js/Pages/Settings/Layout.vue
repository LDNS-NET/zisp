<script setup>
import { usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Settings,
    Wifi,
    Smartphone,
    CreditCard,
    MessageCircle,
    Bell,
    Wallet,
    Cog,
    Server,
} from 'lucide-vue-next';

const page = usePage();

// All valid settings sections
const settingsTabs = [
    {
        key: 'general',
        label: 'General',
        icon: Settings,
        route: 'settings.general.edit',
    },
    {
        key: 'hotspot',
        label: 'Hotspot',
        icon: Wifi,
        route: 'settings.hotspot.edit',
    },
    {
        key: 'sms',
        label: 'SMS Gateway',
        icon: Smartphone,
        route: 'settings.sms.edit',
    },
    {
        key: 'payment',
        label: 'Payment Gateway',
        icon: CreditCard,
        route: 'settings.payment.edit',
    },
    /*{
        key: 'whatsapp',
        label: 'WhatsApp Gateway',
        icon: MessageCircle,
        route: 'settings.whatsapp_gateway.edit',
    },
    {
        key: 'payout',
        label: 'Payouts',
        icon: Wallet,
        route: 'settings.payout.edit',
    },
    {
        key: 'notifications',
        label: 'Notifications',
        icon: Bell,
        route: 'settings.notifications.edit',
    },
    {
        key: 'system',
        label: 'System Settings',
        icon: Cog,
        route: 'settings.index',
    },
    {
        key: 'mikrotik',
        label: 'Mikrotik',
        icon: Server,
        route: 'mikrotiks.index',
    },*/
];
</script>

<template>
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl p-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <!-- Sidebar -->
                <aside
                    class="flex flex-col gap-2 rounded-xl bg-gray-200 p-4 shadow dark:bg-gray-800"
                >
                    <h2
                        class="mb-3 text-lg font-bold text-gray-800 dark:text-gray-100"
                    >
                        ⚙️ Settings
                    </h2>

                    <nav class="flex flex-col gap-2">
                        <Link
                            v-for="tab in settingsTabs"
                            :key="tab.key"
                            :href="route(tab.route)"
                            :class="[ 
                                'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                route().current(tab.route)
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-white'
                                    : 'text-gray-700 hover:bg-blue-50 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                        >
                            <component :is="tab.icon" class="h-5 w-5" />
                            <span>{{ tab.label }}</span>
                        </Link>
                    </nav>
                </aside>

                <!-- Main content slot -->
                <main
                    class="rounded-xl bg-white p-6 shadow md:col-span-3 dark:bg-gray-900 dark:text-gray-100"
                >
                    <slot />
                </main>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
nav a {
    transition:
        background 0.2s,
        color 0.2s;
}
</style>
