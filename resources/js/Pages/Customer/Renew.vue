<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    user: Object,
    package: Object,
    paymentMethods: Array,
    country: String,
    currency: String,
});

const form = useForm({
    phone: props.user.phone || '',
    months: 1,
    payment_method: props.paymentMethods.length > 0 ? props.paymentMethods[0] : '',
});

const isProcessing = ref(false);
const paymentMessage = ref('');
const paymentError = ref('');
const showSuccess = ref(false);

const totalPrice = computed(() => {
    return (props.package?.price || 0) * form.months;
});

const submit = async () => {
    isProcessing.value = true;
    paymentMessage.value = '';
    paymentError.value = '';

    try {
        const response = await axios.post(route('customer.renew.pay'), form);
        if (response.data.success) {
            paymentMessage.value = response.data.message;
            startPolling(response.data.reference_id);
        } else {
            paymentError.value = response.data.message;
            isProcessing.value = false;
        }
    } catch (error) {
        paymentError.value = error.response?.data?.message || 'Payment initiation failed.';
        isProcessing.value = false;
    }
};

const startPolling = (referenceId) => {
    let attempts = 0;
    const interval = setInterval(async () => {
        attempts++;
        if (attempts > 30) {
            clearInterval(interval);
            isProcessing.value = false;
            paymentError.value = 'Payment timed out. Please check your phone.';
            return;
        }

        try {
            const res = await axios.get(route('customer.renew.status', referenceId));
            if (res.data.status === 'paid') {
                clearInterval(interval);
                isProcessing.value = false;
                showSuccess.value = true;
            } else if (res.data.status === 'failed') {
                clearInterval(interval);
                isProcessing.value = false;
                paymentError.value = 'Payment failed.';
            }
        } catch (e) {
            console.error(e);
        }
    }, 3000);
};
</script>

<template>
    <Head title="Renew Subscription" />

    <div class="min-h-screen bg-gray-100">
        <nav class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <Link :href="route('customer.dashboard')" class="font-bold text-xl text-indigo-600">
                            &larr; Back to Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="showSuccess" class="text-center py-12">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-lg font-medium text-gray-900">Renewal Successful!</h3>
                            <p class="mt-1 text-sm text-gray-500">Your subscription has been extended.</p>
                            <div class="mt-6">
                                <Link :href="route('customer.dashboard')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Go to Dashboard
                                </Link>
                            </div>
                        </div>

                        <div v-else>
                            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Renew Subscription</h3>

                            <div class="bg-blue-50 p-4 rounded-lg mb-6">
                                <p class="text-sm text-blue-700">
                                    Renewing: <strong>{{ package?.name }}</strong>
                                </p>
                                <p class="text-sm text-blue-700">
                                    Price: <strong>{{ package?.price }} {{ currency }}</strong> / month
                                </p>
                            </div>

                            <form @submit.prevent="submit" class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Duration</label>
                                    <select v-model="form.months" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        <option :value="1">1 Month</option>
                                        <option :value="3">3 Months</option>
                                        <option :value="6">6 Months</option>
                                        <option :value="12">1 Year</option>
                                    </select>
                                </div>

                                <div v-if="paymentMethods.length > 1">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                    <div class="flex space-x-4">
                                        <label v-for="method in paymentMethods" :key="method" class="inline-flex items-center cursor-pointer">
                                            <input type="radio" v-model="form.payment_method" :value="method" class="form-radio text-indigo-600 h-4 w-4">
                                            <span class="ml-2 capitalize text-gray-700">{{ method === 'mpesa' ? 'M-Pesa' : (method === 'momo' ? 'MTN MoMo' : method) }}</span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                                    <input type="text" v-model="form.phone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="e.g. 07..." required>
                                </div>

                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-700 font-medium">Total to Pay:</span>
                                        <span class="text-2xl font-bold text-gray-900">{{ totalPrice }} {{ currency }}</span>
                                    </div>
                                </div>

                                <div v-if="paymentError" class="text-red-600 text-sm">{{ paymentError }}</div>
                                <div v-if="paymentMessage" class="text-blue-600 text-sm">{{ paymentMessage }}</div>

                                <button type="submit" :disabled="isProcessing" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                                    <span v-if="isProcessing">Processing...</span>
                                    <span v-else>Pay with {{ form.payment_method === 'mpesa' ? 'M-Pesa' : (form.payment_method === 'momo' ? 'MoMo' : 'Selected Method') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
