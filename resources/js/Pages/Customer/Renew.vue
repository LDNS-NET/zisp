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
        if (attempts > 40) {
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

    <div class="min-h-screen bg-slate-50">
        <nav class="bg-white border-b border-slate-200 sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <Link :href="route('customer.dashboard')" class="inline-flex items-center gap-2 font-bold text-slate-600 hover:text-indigo-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            Back to Dashboard
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="py-10">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="showSuccess" class="bg-white rounded-3xl shadow-sm border border-slate-200 p-12 text-center">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
                        <svg class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-black text-slate-900 mb-2">Renewal Successful!</h2>
                    <p class="text-slate-500 mb-8 text-lg">Your subscription has been extended successfully. Thank you for your payment!</p>
                    <Link :href="route('customer.dashboard')" class="inline-flex items-center px-8 py-3 border border-transparent text-base font-bold rounded-full text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                        Return to Dashboard
                    </Link>
                </div>

                <div v-else class="bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
                    <div class="p-8 border-b border-slate-100 bg-slate-50">
                        <h1 class="text-2xl font-black text-slate-900">Renew Subscription</h1>
                        <p class="text-slate-500">Extend your internet plan in a few clicks.</p>
                    </div>

                    <div class="p-8">
                        <div class="bg-indigo-50 rounded-2xl p-6 mb-8 flex items-center justify-between">
                            <div>
                                <p class="text-indigo-600 text-xs uppercase font-bold tracking-widest mb-1">Current Plan</p>
                                <h3 class="text-xl font-black text-slate-900">{{ package?.name || 'No Plan' }}</h3>
                            </div>
                            <div class="text-right">
                                <p class="text-indigo-600 text-xs uppercase font-bold tracking-widest mb-1">Price</p>
                                <p class="text-xl font-black text-slate-900">{{ package?.price }} {{ currency }} <span class="text-xs font-normal text-slate-500">/ month</span></p>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-8">
                            <div>
                                <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-4">Select Duration</label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <label v-for="opt in [1, 3, 6, 12]" :key="opt" 
                                        class="relative flex flex-col items-center justify-center p-4 rounded-2xl border-2 cursor-pointer transition-all"
                                        :class="form.months === opt ? 'border-indigo-600 bg-indigo-50' : 'border-slate-100 hover:border-slate-200'"
                                    >
                                        <input type="radio" v-model="form.months" :value="opt" class="sr-only">
                                        <span class="text-lg font-black text-slate-900">{{ opt }}</span>
                                        <span class="text-xs text-slate-500 uppercase font-bold">{{ opt === 1 ? 'Month' : (opt === 12 ? 'Year' : 'Months') }}</span>
                                        <div v-if="form.months === opt" class="absolute -top-2 -right-2 bg-indigo-600 text-white rounded-full p-1 shadow-lg">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3"/></svg>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div v-if="paymentMethods.length > 1">
                                <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-4">Payment Method</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <label v-for="method in paymentMethods" :key="method" 
                                        class="relative flex items-center justify-center p-4 rounded-2xl border-2 cursor-pointer transition-all"
                                        :class="form.payment_method === method ? 'border-indigo-600 bg-indigo-50' : 'border-slate-100 hover:border-slate-200'"
                                    >
                                        <input type="radio" v-model="form.payment_method" :value="method" class="sr-only">
                                        <div class="flex flex-col items-center gap-2">
                                            <img v-if="method === 'mpesa'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/15/M-PESA_LOGO-01.svg/1200px-M-PESA_LOGO-01.svg.png" class="h-4" alt="M-Pesa">
                                            <img v-else-if="method === 'momo'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/MTN_Logo.svg/1200px-MTN_Logo.svg.png" class="h-6" alt="MoMo">
                                            <span class="text-xs font-bold uppercase text-slate-900">{{ method === 'mpesa' ? 'M-Pesa' : (method === 'momo' ? 'MoMo' : method) }}</span>
                                        </div>
                                        <div v-if="form.payment_method === method" class="absolute -top-2 -right-2 bg-indigo-600 text-white rounded-full p-1 shadow-lg">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3"/></svg>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-2">Phone Number</label>
                                <input type="text" v-model="form.phone" class="w-full p-4 rounded-2xl border-2 border-slate-100 focus:border-indigo-600 focus:ring-0 transition-all text-lg font-bold" placeholder="e.g. 07..." required>
                                <p class="mt-2 text-xs text-slate-500">Enter the phone number to receive the payment prompt.</p>
                            </div>

                            <div class="bg-slate-900 rounded-2xl p-6 text-white flex justify-between items-center">
                                <div>
                                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Total Amount</p>
                                    <p class="text-3xl font-black">{{ totalPrice }} {{ currency }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Duration</p>
                                    <p class="text-lg font-black">{{ form.months }} {{ form.months === 1 ? 'Month' : 'Months' }}</p>
                                </div>
                            </div>

                            <div v-if="paymentError" class="p-4 rounded-2xl bg-red-50 text-red-600 text-sm font-bold border border-red-100">
                                {{ paymentError }}
                            </div>
                            <div v-if="paymentMessage" class="p-4 rounded-2xl bg-blue-50 text-blue-600 text-sm font-bold border border-blue-100 animate-pulse">
                                {{ paymentMessage }}
                            </div>

                            <button type="submit" :disabled="isProcessing" class="w-full py-4 rounded-2xl text-lg font-black text-white bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-200 transition-all disabled:opacity-50 disabled:shadow-none">
                                <span v-if="isProcessing" class="flex items-center justify-center gap-2">
                                    <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Processing Payment...
                                </span>
                                <span v-else>Confirm & Pay Now</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.bg-slate-900 {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}
</style>
