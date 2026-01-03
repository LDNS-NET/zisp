<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { 
    Calendar, 
    CreditCard, 
    Smartphone, 
    CheckCircle2, 
    AlertCircle,
    Loader2,
    ChevronRight
} from 'lucide-vue-next';

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
const paystackPublicKey = ref(null);
const paystackAccessCode = ref(null);

const totalPrice = computed(() => {
    return (props.package?.price || 0) * form.months;
});

const submit = async () => {
    if (form.payment_method === 'paystack') {
        await initiatePaystackPayment();
    } else {
        await initiateStandardPayment();
    }
};

const initiateStandardPayment = async () => {
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

const initiatePaystackPayment = async () => {
    isProcessing.value = true;
    paymentMessage.value = '';
    paymentError.value = '';

    try {
        const response = await axios.post(route('customer.renew.pay'), form);
        if (response.data.success) {
            paystackPublicKey.value = response.data.public_key;
            paystackAccessCode.value = response.data.access_code;
            openPaystackPopup(response.data.reference_id);
        } else {
            paymentError.value = response.data.message;
            isProcessing.value = false;
        }
    } catch (error) {
        paymentError.value = error.response?.data?.message || 'Payment initiation failed.';
        isProcessing.value = false;
    }
};

const openPaystackPopup = (reference) => {
    const handler = window.PaystackPop.setup({
        key: paystackPublicKey.value,
        email: props.user.email || form.phone + '@customer.local',
        amount: totalPrice.value * 100, // Convert to kobo
        ref: reference,
        onClose: function() {
            isProcessing.value = false;
            paymentError.value = 'Payment cancelled';
        },
        callback: function(response) {
            verifyPaystackPayment(response.reference);
        }
    });
    handler.openIframe();
};

const verifyPaystackPayment = async (reference) => {
    try {
        const res = await axios.get(route('customer.renew.status', reference));
        if (res.data.status === 'paid') {
            isProcessing.value = false;
            showSuccess.value = true;
        } else {
            paymentError.value = 'Payment verification failed.';
            isProcessing.value = false;
        }
    } catch (error) {
        paymentError.value = 'Payment verification error.';
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

    <CustomerLayout>
        <template #header>Renew Subscription</template>

        <div class="max-w-3xl mx-auto">
            <div v-if="showSuccess" class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100 border border-slate-100 p-12 text-center">
                <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-green-50 mb-8 border-4 border-white shadow-lg">
                    <CheckCircle2 class="h-12 w-12 text-green-500" />
                </div>
                <h2 class="text-4xl font-black text-slate-900 mb-4">Payment Successful!</h2>
                <p class="text-slate-500 mb-10 text-lg font-medium leading-relaxed">Your subscription has been extended successfully. Your internet access is now active.</p>
                <Link :href="route('customer.dashboard')" class="inline-flex items-center px-10 py-4 border border-transparent text-lg font-black rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-200 transition-all active:scale-95">
                    Return to Dashboard
                </Link>
            </div>

            <div v-else class="space-y-8">
                <!-- Plan Summary Card -->
                <div class="bg-indigo-600 rounded-[2rem] p-8 text-white shadow-xl shadow-indigo-100 flex flex-col sm:flex-row justify-between items-center gap-6 relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-indigo-200 text-[10px] uppercase font-black tracking-widest mb-1">Renewing Plan</p>
                        <h3 class="text-3xl font-black">{{ package?.name || 'No Plan' }}</h3>
                    </div>
                    <div class="text-center sm:text-right relative z-10">
                        <p class="text-indigo-200 text-[10px] uppercase font-black tracking-widest mb-1">Monthly Rate</p>
                        <p class="text-3xl font-black">{{ package?.price }} {{ currency }}</p>
                    </div>
                    <!-- Decorative element -->
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-8 lg:p-10">
                        <form @submit.prevent="submit" class="space-y-10">
                            <!-- Duration Selection -->
                            <div>
                                <div class="flex items-center gap-2 mb-6">
                                    <Calendar class="w-5 h-5 text-indigo-600" />
                                    <label class="text-sm font-black text-slate-900 uppercase tracking-widest">Select Duration</label>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <label v-for="opt in [1, 3, 6, 12]" :key="opt" 
                                        class="relative flex flex-col items-center justify-center p-6 rounded-2xl border-2 cursor-pointer transition-all duration-200"
                                        :class="form.months === opt ? 'border-indigo-600 bg-indigo-50 shadow-lg shadow-indigo-50' : 'border-slate-100 hover:border-slate-200 bg-slate-50/50'"
                                    >
                                        <input type="radio" v-model="form.months" :value="opt" class="sr-only">
                                        <span class="text-2xl font-black" :class="form.months === opt ? 'text-indigo-600' : 'text-slate-900'">{{ opt }}</span>
                                        <span class="text-[10px] font-black uppercase tracking-widest" :class="form.months === opt ? 'text-indigo-400' : 'text-slate-400'">
                                            {{ opt === 1 ? 'Month' : (opt === 12 ? 'Year' : 'Months') }}
                                        </span>
                                        <div v-if="form.months === opt" class="absolute -top-2 -right-2 bg-indigo-600 text-white rounded-full p-1 shadow-lg border-2 border-white">
                                            <CheckCircle2 class="w-3 h-3" />
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div v-if="paymentMethods.length > 1">
                                <div class="flex items-center gap-2 mb-6">
                                    <CreditCard class="w-5 h-5 text-indigo-600" />
                                    <label class="text-sm font-black text-slate-900 uppercase tracking-widest">Payment Method</label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label v-for="method in paymentMethods" :key="method" 
                                        class="relative flex items-center gap-4 p-5 rounded-2xl border-2 cursor-pointer transition-all duration-200"
                                        :class="form.payment_method === method ? 'border-indigo-600 bg-indigo-50 shadow-lg shadow-indigo-50' : 'border-slate-100 hover:border-slate-200 bg-slate-50/50'"
                                    >
                                        <input type="radio" v-model="form.payment_method" :value="method" class="sr-only">
                                        <div class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shadow-sm border border-slate-100">
                                            <img v-if="method === 'mpesa'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/15/M-PESA_LOGO-01.svg/1200px-M-PESA_LOGO-01.svg.png" class="h-4" alt="M-Pesa">
                                            <img v-else-if="method === 'momo'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/9/93/MTN_Logo.svg/1200px-MTN_Logo.svg.png" class="h-7" alt="MoMo">
                                            <img v-else-if="method === 'paystack'" src="https://upload.wikimedia.org/wikipedia/commons/thumb/0/0b/Paystack_Logo.png/320px-Paystack_Logo.png" class="h-5" alt="Paystack">
                                            <span v-else class="text-xs font-black uppercase">{{ method }}</span>
                                        </div>
                                        <div>
                                            <span class="font-black text-slate-900 capitalize block">{{ method === 'mpesa' ? 'M-Pesa' : (method === 'momo' ? 'MTN MoMo' : (method === 'paystack' ? 'Paystack' : method)) }}</span>
                                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ method === 'paystack' ? 'Card, Bank, USSD' : 'Instant STK Push' }}</span>
                                        </div>
                                        <div v-if="form.payment_method === method" class="absolute -top-2 -right-2 bg-indigo-600 text-white rounded-full p-1 shadow-lg border-2 border-white">
                                            <CheckCircle2 class="w-3 h-3" />
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Phone Number -->
                            <div>
                                <div class="flex items-center gap-2 mb-4">
                                    <Smartphone class="w-5 h-5 text-indigo-600" />
                                    <label class="text-sm font-black text-slate-900 uppercase tracking-widest">Phone Number</label>
                                </div>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        v-model="form.phone" 
                                        class="w-full p-5 rounded-2xl border-2 border-slate-100 focus:border-indigo-600 focus:ring-0 transition-all text-xl font-black bg-slate-50/50 placeholder:text-slate-300" 
                                        placeholder="e.g. 0712345678" 
                                        required
                                    >
                                    <div class="absolute right-5 top-1/2 -translate-y-1/2 text-slate-400">
                                        <Smartphone class="w-6 h-6" />
                                    </div>
                                </div>
                                <p class="mt-3 text-xs font-bold text-slate-400 flex items-center gap-1">
                                    <AlertCircle class="w-3 h-3" />
                                    Enter the number that will receive the payment prompt.
                                </p>
                            </div>

                            <!-- Summary & Submit -->
                            <div class="space-y-6 pt-4">
                                <div class="bg-slate-900 rounded-3xl p-8 text-white flex justify-between items-center shadow-xl shadow-slate-200">
                                    <div>
                                        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total to Pay</p>
                                        <p class="text-4xl font-black text-indigo-100">{{ totalPrice }} <span class="text-lg font-bold text-indigo-400">{{ currency }}</span></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Duration</p>
                                        <p class="text-xl font-black">{{ form.months }} {{ form.months === 1 ? 'Month' : 'Months' }}</p>
                                    </div>
                                </div>

                                <div v-if="paymentError" class="p-5 rounded-2xl bg-red-50 text-red-600 text-sm font-black border border-red-100 flex items-center gap-3">
                                    <AlertCircle class="w-5 h-5 shrink-0" />
                                    {{ paymentError }}
                                </div>
                                
                                <div v-if="paymentMessage" class="p-5 rounded-2xl bg-indigo-50 text-indigo-600 text-sm font-black border border-indigo-100 flex items-center gap-3 animate-pulse">
                                    <Loader2 class="w-5 h-5 shrink-0 animate-spin" />
                                    {{ paymentMessage }}
                                </div>

                                <button 
                                    type="submit" 
                                    :disabled="isProcessing" 
                                    class="w-full py-5 rounded-[1.5rem] text-xl font-black text-white bg-indigo-600 hover:bg-indigo-700 shadow-2xl shadow-indigo-200 transition-all active:scale-[0.98] disabled:opacity-50 disabled:shadow-none disabled:scale-100"
                                >
                                    <span v-if="isProcessing" class="flex items-center justify-center gap-3">
                                        <Loader2 class="animate-spin h-6 w-6" />
                                        Processing...
                                    </span>
                                    <span v-else class="flex items-center justify-center gap-2">
                                        Confirm & Pay Now
                                        <ChevronRight class="w-6 h-6" />
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>

<style scoped>
.bg-slate-900 {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}
</style>

<style scoped>
.bg-slate-900 {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}
</style>
