<script setup>
import { ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CreditCard, Users, Wifi, AlertCircle, CheckCircle, ArrowRight, Loader2 } from 'lucide-vue-next';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const props = defineProps({
    bill: Object,
    subscription: Object,
    tenant: Object,
});

const toast = useToast();
const loading = ref(false);

const initializePayment = async () => {
    loading.ref = true;
    try {
        const response = await axios.post(route('subscription.initialize-payment'));
        if (response.data.success && response.data.authorization_url) {
            window.location.href = response.data.authorization_url;
        } else {
            toast.error(response.data.message || 'Failed to initialize payment.');
        }
    } catch (error) {
        console.error('Payment initialization error:', error);
        toast.error('An error occurred while initializing payment. Please try again.');
    } finally {
        loading.value = false;
    }
};

const formatCurrency = (amount, currency) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'KES',
    }).format(amount);
};
</script>

<template>
    <Head title="System Renewal" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">System Renewal</h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-8">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">Monthly Usage Summary</h3>
                            <p class="text-gray-600">Your bill is calculated based on active PPPoE users and hotspot income.</p>
                        </div>
                        <div class="bg-blue-50 p-3 rounded-full">
                            <CreditCard class="w-8 h-8 text-blue-600" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <!-- PPPoE Users -->
                        <div class="border border-gray-100 rounded-2xl p-6 bg-gray-50/50">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="bg-indigo-100 p-2 rounded-lg">
                                    <Users class="w-5 h-5 text-indigo-600" />
                                </div>
                                <h4 class="font-semibold text-gray-700">PPPoE Users</h4>
                            </div>
                            <div class="flex justify-between items-end">
                                <div>
                                    <span class="text-3xl font-bold text-gray-900">{{ bill.pppoe_users }}</span>
                                    <span class="text-gray-500 ml-2">Active Users</span>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500">Rate: {{ formatCurrency(bill.pppoe_rate, bill.currency) }}/user</p>
                                    <p class="font-semibold text-gray-900">{{ formatCurrency(bill.pppoe_amount, bill.currency) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Hotspot Income -->
                        <div class="border border-gray-100 rounded-2xl p-6 bg-gray-50/50">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="bg-orange-100 p-2 rounded-lg">
                                    <Wifi class="w-5 h-5 text-orange-600" />
                                </div>
                                <h4 class="font-semibold text-gray-700">Hotspot Income</h4>
                            </div>
                            <div class="flex justify-between items-end">
                                <div>
                                    <span class="text-3xl font-bold text-gray-900">{{ formatCurrency(bill.hotspot_income, bill.currency) }}</span>
                                    <span class="text-gray-500 ml-2">Total Income</span>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500">Rate: {{ (bill.hotspot_rate * 100).toFixed(0) }}%</p>
                                    <p class="font-semibold text-gray-900">{{ formatCurrency(bill.hotspot_amount, bill.currency) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Calculation -->
                    <div class="bg-gray-900 rounded-2xl p-8 text-white mb-8">
                        <div class="flex flex-col gap-4">
                            <div class="flex justify-between items-center text-gray-400">
                                <span>Calculated Total</span>
                                <span>{{ formatCurrency(bill.total_calculated, bill.currency) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-400">
                                <span>Minimum Monthly Payment</span>
                                <span>{{ formatCurrency(bill.minimum_pay, bill.currency) }}</span>
                            </div>
                            <div class="h-px bg-gray-800 my-2"></div>
                            <div class="flex justify-between items-center">
                                <span class="text-xl font-semibold">Total Amount Due</span>
                                <span class="text-3xl font-bold text-blue-400">{{ formatCurrency(bill.final_amount, bill.currency) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="flex flex-col items-center gap-4">
                        <button 
                            @click="initializePayment"
                            :disabled="loading"
                            class="w-full md:w-auto px-12 py-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-xl font-bold text-lg transition-all flex items-center justify-center gap-3 shadow-lg shadow-blue-200"
                        >
                            <Loader2 v-if="loading" class="w-6 h-6 animate-spin" />
                            <span v-else>Pay with Paystack</span>
                            <ArrowRight v-if="!loading" class="w-6 h-6" />
                        </button>
                        <p class="text-sm text-gray-500 flex items-center gap-2">
                            <CheckCircle class="w-4 h-4 text-green-500" />
                            Secure payment processed by Paystack
                        </p>
                    </div>

                    <!-- Status Banner -->
                    <div v-if="subscription && subscription.status === 'expired'" class="mt-8 bg-red-50 border border-red-100 rounded-xl p-4 flex items-start gap-4">
                        <AlertCircle class="w-6 h-6 text-red-600 shrink-0" />
                        <div>
                            <h5 class="font-bold text-red-900 text-sm uppercase tracking-wider">Subscription Expired</h5>
                            <p class="text-red-700 text-sm">Your system access is currently limited. Please renew to restore full functionality.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
