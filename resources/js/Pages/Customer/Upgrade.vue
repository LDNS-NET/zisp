<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    user: Object,
    currentPackage: Object,
    packages: Array,
    paymentMethods: Array,
    country: String,
    currency: String,
});

const form = useForm({
    phone: props.user.phone || '',
    package_id: null,
    payment_method: props.paymentMethods.length > 0 ? props.paymentMethods[0] : '',
    upgrade_type: 'immediate',
});

const isProcessing = ref(false);
const paymentMessage = ref('');
const paymentError = ref('');
const showSuccess = ref(false);
const selectedPackage = ref(null);

const selectPackage = (pkg) => {
    selectedPackage.value = pkg;
    form.package_id = pkg.id;
    paymentError.value = '';
};

const submit = async () => {
    if (!form.package_id) {
        paymentError.value = 'Please select a package.';
        return;
    }

    isProcessing.value = true;
    paymentMessage.value = '';
    paymentError.value = '';

    try {
        const response = await axios.post(route('customer.upgrade.pay'), form);
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
            const res = await axios.get(route('customer.upgrade.status', referenceId));
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
    <Head title="Upgrade Plan" />

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
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="showSuccess" class="max-w-2xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 p-12 text-center">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6">
                        <svg class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-black text-slate-900 mb-2">Upgrade Successful!</h2>
                    <p class="text-slate-500 mb-8 text-lg">Your plan has been upgraded to <strong>{{ selectedPackage?.name }}</strong>. Enjoy your faster internet!</p>
                    <Link :href="route('customer.dashboard')" class="inline-flex items-center px-8 py-3 border border-transparent text-base font-bold rounded-full text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                        Return to Dashboard
                    </Link>
                </div>

                <div v-else>
                    <div class="mb-10 text-center">
                        <h1 class="text-4xl font-black text-slate-900 mb-2">Upgrade Your Speed</h1>
                        <p class="text-slate-500 text-lg">Choose a faster plan that fits your needs.</p>
                    </div>

                    <!-- Current Plan Summary -->
                    <div class="max-w-4xl mx-auto mb-12 bg-indigo-600 rounded-3xl p-8 text-white shadow-xl flex flex-col md:flex-row justify-between items-center gap-6">
                        <div>
                            <p class="text-indigo-200 text-xs uppercase font-bold tracking-widest mb-1">Current Plan</p>
                            <h3 class="text-2xl font-black">{{ currentPackage?.name || 'No Plan' }}</h3>
                        </div>
                        <div class="flex gap-8">
                            <div class="text-center">
                                <p class="text-indigo-200 text-xs uppercase font-bold tracking-widest mb-1">Download</p>
                                <p class="text-xl font-black">{{ currentPackage?.download_speed }} Mbps</p>
                            </div>
                            <div class="text-center">
                                <p class="text-indigo-200 text-xs uppercase font-bold tracking-widest mb-1">Upload</p>
                                <p class="text-xl font-black">{{ currentPackage?.upload_speed }} Mbps</p>
                            </div>
                        </div>
                    </div>

                    <h3 class="text-xl font-black text-slate-900 mb-6 text-center">Available Upgrades</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
                        <div v-for="pkg in packages" :key="pkg.id" 
                            class="group bg-white rounded-3xl p-8 cursor-pointer transition-all border-2 relative overflow-hidden"
                            :class="selectedPackage?.id === pkg.id ? 'border-indigo-600 shadow-xl scale-[1.02]' : 'border-white shadow-sm hover:border-slate-200'"
                            @click="selectPackage(pkg)"
                        >
                            <div v-if="selectedPackage?.id === pkg.id" class="absolute top-0 right-0 bg-indigo-600 text-white px-4 py-1 rounded-bl-2xl text-xs font-bold">
                                SELECTED
                            </div>
                            <h4 class="text-xl font-black text-slate-900 mb-2">{{ pkg.name }}</h4>
                            <div class="flex items-baseline gap-1 mb-6">
                                <span class="text-4xl font-black text-indigo-600">{{ pkg.price }}</span>
                                <span class="text-slate-500 font-bold">{{ currency }}</span>
                            </div>
                            
                            <div class="space-y-4 mb-8">
                                <div class="flex items-center gap-3 text-slate-700">
                                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 16V4m0 0L3 8m4-4l4 4" stroke-width="2"/></svg>
                                    </div>
                                    <span class="font-bold">{{ pkg.download_speed }} Mbps Download</span>
                                </div>
                                <div class="flex items-center gap-3 text-slate-700">
                                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 8v12m0 0l4-4m-4 4l-4-4" stroke-width="2"/></svg>
                                    </div>
                                    <span class="font-bold">{{ pkg.upload_speed }} Mbps Upload</span>
                                </div>
                                <div class="flex items-center gap-3 text-slate-700">
                                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197" stroke-width="2"/></svg>
                                    </div>
                                    <span>{{ pkg.device_limit }} Device Limit</span>
                                </div>
                            </div>

                            <div class="w-full py-3 rounded-2xl text-center font-bold transition-all"
                                :class="selectedPackage?.id === pkg.id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 group-hover:bg-slate-200'"
                            >
                                {{ selectedPackage?.id === pkg.id ? 'Ready to Upgrade' : 'Select Plan' }}
                            </div>
                        </div>
                    </div>

                    <!-- Checkout Section -->
                    <div v-if="selectedPackage" class="max-w-2xl mx-auto bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
                        <div class="p-8 border-b border-slate-100 bg-slate-50">
                            <h4 class="text-xl font-black text-slate-900">Complete Your Upgrade</h4>
                            <p class="text-slate-500">Confirm details and pay to activate your new speed.</p>
                        </div>
                        <div class="p-8">
                            <form @submit.prevent="submit" class="space-y-8">
                                <!-- Upgrade Type Selection -->
                                <div>
                                    <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-4">When to Upgrade?</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <label class="relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all"
                                            :class="form.upgrade_type === 'immediate' ? 'border-indigo-600 bg-indigo-50' : 'border-slate-100 hover:border-slate-200'"
                                        >
                                            <input type="radio" v-model="form.upgrade_type" value="immediate" class="sr-only">
                                            <span class="font-black text-slate-900">Upgrade Now</span>
                                            <span class="text-xs text-slate-500 mt-1">Pay the difference and switch immediately.</span>
                                            <div v-if="form.upgrade_type === 'immediate'" class="absolute -top-2 -right-2 bg-indigo-600 text-white rounded-full p-1 shadow-lg">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3"/></svg>
                                            </div>
                                        </label>
                                        <label class="relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all"
                                            :class="form.upgrade_type === 'after_expiry' ? 'border-indigo-600 bg-indigo-50' : 'border-slate-100 hover:border-slate-200'"
                                        >
                                            <input type="radio" v-model="form.upgrade_type" value="after_expiry" class="sr-only">
                                            <span class="font-black text-slate-900">After Expiry</span>
                                            <span class="text-xs text-slate-500 mt-1">Pay full price and switch after current plan ends.</span>
                                            <div v-if="form.upgrade_type === 'after_expiry'" class="absolute -top-2 -right-2 bg-indigo-600 text-white rounded-full p-1 shadow-lg">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3"/></svg>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Payment Method Selection -->
                                <div v-if="paymentMethods.length > 1">
                                    <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-4">Select Payment Method</label>
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
                                        <p class="text-2xl font-black">
                                            {{ form.upgrade_type === 'immediate' ? selectedPackage.price_difference : selectedPackage.price }} {{ currency }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">New Speed</p>
                                        <p class="text-lg font-black">{{ selectedPackage.download_speed }} Mbps</p>
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
            </div>
        </main>
    </div>
</template>

<style scoped>
.bg-slate-900 {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}
</style>
