<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Layout from './Layout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    connected: Boolean,
    settings: Object,
});

const disconnectForm = useForm({});

const disconnect = () => {
    if (confirm('Are you sure you want to disconnect QuickBooks? This will stop data synchronization.')) {
        disconnectForm.post(route('quickbooks.disconnect'));
    }
};
</script>

<template>
    <Layout>
        <Head title="QuickBooks Integration" />

        <section class="rounded-xl border border-blue-400 bg-gray-200 p-6 shadow-sm dark:bg-gray-900">
            <header>
                <h2 class="font-extrabold text-blue-700 dark:text-blue-400">
                    QuickBooks Online Integration
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Connect your account to synchronize invoices, payments, and expenses automatically.
                </p>
            </header>

            <div class="mt-8 space-y-6">
                <!-- Status Card -->
                <div :class="[
                    'rounded-lg p-4 flex items-center justify-between',
                    connected ? 'bg-green-50 border border-green-200 dark:bg-green-900/20 dark:border-green-800' : 'bg-gray-50 border border-gray-200 dark:bg-gray-800 dark:border-gray-700'
                ]">
                    <div class="flex items-center gap-3">
                        <div :class="[
                            'h-3 w-3 rounded-full',
                            connected ? 'bg-green-500 animate-pulse' : 'bg-gray-400'
                        ]"></div>
                        <div>
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-100">
                                Status: {{ connected ? 'Connected' : 'Not Connected' }}
                            </p>
                            <p v-if="connected" class="text-xs text-gray-600 dark:text-gray-400">
                                Connected on: {{ settings.connected_at }}
                            </p>
                        </div>
                    </div>

                    <div v-if="connected">
                        <DangerButton @click="disconnect" :disabled="disconnectForm.processing">
                            Disconnect
                        </DangerButton>
                    </div>
                </div>

                <!-- Connect Button -->
                <div v-if="!connected" class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="mb-4 rounded-full bg-blue-100 p-6 dark:bg-blue-900/30">
                        <svg class="h-12 w-12 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-gray-800 dark:text-gray-100">Sync Your Financial Data</h3>
                    <p class="mb-6 max-w-sm text-sm text-gray-600 dark:text-gray-400">
                        Link your QuickBooks Online account to automate your accounting. We'll sync your invoices, payments, and expenses every 30 minutes.
                    </p>
                    <a :href="route('quickbooks.connect')" class="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition-all hover:bg-blue-700 hover:scale-105 active:scale-95">
                         Connect to QuickBooks
                    </a>
                </div>

                <!-- Features List -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <h4 class="mb-1 text-sm font-bold text-gray-800 dark:text-gray-100">📋 Automated Invoicing</h4>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Every invoice generated in Zisp will be automatically created in your QuickBooks.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <h4 class="mb-1 text-sm font-bold text-gray-800 dark:text-gray-100">💰 Payment Recording</h4>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Payments made via M-Pesa or other gateways are synced and linked to their invoices.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <h4 class="mb-1 text-sm font-bold text-gray-800 dark:text-gray-100">📊 Expense Tracking</h4>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Sync your daily business expenses to keep your P&L up to date.</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <h4 class="mb-1 text-sm font-bold text-gray-800 dark:text-gray-100">🔧 Asset Management</h4>
                        <p class="text-xs text-gray-600 dark:text-gray-400">New equipment added to inventory is synced as Non-Inventory items in QBO.</p>
                    </div>
                </div>
            </div>
        </section>
    </Layout>
</template>
