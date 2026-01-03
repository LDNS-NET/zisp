<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { 
    Zap, 
    TrendingUp, 
    CheckCircle2, 
    AlertCircle, 
    Loader2,
    ArrowRight,
    Smartphone,
    CreditCard,
    ArrowUpCircle,
    ArrowDownCircle,
    Users
} from 'lucide-vue-next';

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
const paystackPublicKey = ref(null);
const paystackAccessCode = ref(null);

const totalAmount = computed(() => {
    if (!selectedPackage.value) return 0;
    return form.upgrade_type === 'immediate' ? selectedPackage.value.price_difference : selectedPackage.value.price;
});

const selectPackage = (pkg) => {
    selectedPackage.value = pkg;
    form.package_id = pkg.id;
    paymentError.value = '';
    
    // Scroll to checkout
    setTimeout(() => {
        document.getElementById('checkout-section')?.scrollIntoView({ behavior: 'smooth' });
    }, 100);
};

const submit = async () => {
    if (!form.package_id) {
        paymentError.value = 'Please select a package.';
        return;
    }

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

const initiatePaystackPayment = async () => {
    isProcessing.value = true;
    paymentMessage.value = '';
    paymentError.value = '';

    try {
        const response = await axios.post(route('customer.upgrade.pay'), form);
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
        amount: totalAmount.value * 100, // Convert to kobo
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
        const res = await axios.get(route('customer.upgrade.status', reference));
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

    <CustomerLayout>
        <template #header>Upgrade Your Plan</template>

        <div class="max-w-5xl mx-auto">
            <div v-if="showSuccess" class="bg-white rounded-[2.5rem] shadow-xl shadow-indigo-100 border border-slate-100 p-12 text-center">
                <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-green-50 mb-8 border-4 border-white shadow-lg">
                    <CheckCircle2 class="h-12 w-12 text-green-500" />
                </div>
                <h2 class="text-4xl font-black text-slate-900 mb-4">Upgrade Successful!</h2>
                <p class="text-slate-500 mb-10 text-lg font-medium leading-relaxed">Your plan has been upgraded to <strong>{{ selectedPackage?.name }}</strong>. Enjoy your faster internet speed!</p>
                <Link :href="route('customer.dashboard')" class="inline-flex items-center px-10 py-4 border border-transparent text-lg font-black rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 shadow-xl shadow-indigo-200 transition-all active:scale-95">
                    Return to Dashboard
                </Link>
            </div>

            <div v-else class="space-y-12">
                <!-- Current Plan Summary -->
                <div class="bg-indigo-600 rounded-[2rem] p-8 text-white shadow-xl shadow-indigo-100 flex flex-col md:flex-row justify-between items-center gap-8 relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-indigo-200 text-[10px] uppercase font-black tracking-widest mb-1">Current Active Plan</p>
                        <h3 class="text-3xl font-black">{{ currentPackage?.name || 'No Plan' }}</h3>
                    </div>
                    <div class="flex gap-10 relative z-10">
                        <div class="text-center">
                            <p class="text-indigo-200 text-[10px] uppercase font-black tracking-widest mb-1">Download</p>
                            <p class="text-2xl font-black">{{ currentPackage?.download_speed }} Mbps</p>
                        </div>
                        <div class="text-center">
                            <p class="text-indigo-200 text-[10px] uppercase font-black tracking-widest mb-1">Upload</p>
                            <p class="text-2xl font-black">{{ currentPackage?.upload_speed }} Mbps</p>
                        </div>
                    </div>
                    <!-- Decorative element -->
                    <div class="absolute -left-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                </div>

                <div class="text-center">
                    <h3 class="text-2xl font-black text-slate-900 mb-2">Available Speed Upgrades</h3>
                    <p class="text-slate-500 font-medium">Select a plan that better fits your needs.</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div v-for="pkg in packages" :key="pkg.id" 
                        class="group bg-white rounded-[2.5rem] p-8 cursor-pointer transition-all duration-300 border-2 relative overflow-hidden flex flex-col"
                        :class="selectedPackage?.id === pkg.id ? 'border-indigo-600 shadow-2xl shadow-indigo-100 scale-[1.02]' : 'border-white shadow-sm hover:border-slate-200 hover:shadow-xl'"
                        @click="selectPackage(pkg)"
                    >
                        <div v-if="selectedPackage?.id === pkg.id" class="absolute top-0 right-0 bg-indigo-600 text-white px-6 py-2 rounded-bl-3xl text-[10px] font-black tracking-widest uppercase">
                            Selected
                        </div>
                        
                        <div class="mb-8">
                            <h4 class="text-2xl font-black text-slate-900 mb-2">{{ pkg.name }}</h4>
                            <div class="flex items-baseline gap-1">
                                <span class="text-4xl font-black text-indigo-600">{{ pkg.price }}</span>
                                <span class="text-slate-400 font-bold uppercase text-xs tracking-widest">{{ currency }}</span>
                            </div>
                        </div>
                        
                        <div class="space-y-5 mb-10 flex-1">
                            <div class="flex items-center gap-4 text-slate-700">
                                <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-indigo-600 shadow-sm border border-slate-100">
                                    <ArrowDownCircle class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Download</p>
                                    <p class="font-black">{{ pkg.download_speed }} Mbps</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-slate-700">
                                <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-violet-600 shadow-sm border border-slate-100">
                                    <ArrowUpCircle class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Upload</p>
                                    <p class="font-black">{{ pkg.upload_speed }} Mbps</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-slate-700">
                                <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 shadow-sm border border-slate-100">
                                    <Users class="w-5 h-5" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Limit</p>
                                    <p class="font-black">{{ pkg.device_limit }} Devices</p>
                                </div>
                            </div>
                        </div>

                        <div class="w-full py-4 rounded-2xl text-center font-black transition-all duration-300"
                            :class="selectedPackage?.id === pkg.id ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'bg-slate-50 text-slate-500 group-hover:bg-slate-100 group-hover:text-slate-900'"
                        >
                            {{ selectedPackage?.id === pkg.id ? 'Ready to Upgrade' : 'Select Plan' }}
                        </div>
                    </div>
                </div>

                <!-- Checkout Section -->
                <div v-if="selectedPackage" id="checkout-section" class="bg-white rounded-[2.5rem] shadow-2xl shadow-slate-200 border border-slate-200 overflow-hidden transition-all duration-500">
                    <div class="p-8 lg:p-10 border-b border-slate-100 bg-slate-50/50">
                        <h4 class="text-2xl font-black text-slate-900">Complete Upgrade</h4>
                        <p class="text-slate-500 font-medium">Finalize your selection and pay to activate.</p>
                    </div>
                    <div class="p-8 lg:p-10">
                        <form @submit.prevent="submit" class="space-y-10">
                            <!-- Upgrade Type -->
                            <div>
                                <div class="flex items-center gap-2 mb-6">
                                    <Zap class="w-5 h-5 text-indigo-600" />
                                    <label class="text-sm font-black text-slate-900 uppercase tracking-widest">When to Upgrade?</label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                    <label class="relative flex flex-col p-6 rounded-2xl border-2 cursor-pointer transition-all duration-200"
                                        :class="form.upgrade_type === 'immediate' ? 'border-indigo-600 bg-indigo-50 shadow-lg shadow-indigo-50' : 'border-slate-100 hover:border-slate-200 bg-slate-50/50'"
                                    >
                                        <input type="radio" v-model="form.upgrade_type" value="immediate" class="sr-only">
                                        <span class="font-black text-lg" :class="form.upgrade_type === 'immediate' ? 'text-indigo-600' : 'text-slate-900'">Upgrade Now</span>
                                        <span class="text-xs font-medium text-slate-400 mt-2 leading-relaxed">Pay the price difference and switch to the new speed immediately.</span>
                                        <div v-if="form.upgrade_type === 'immediate'" class="absolute -top-2 -right-2 bg-indigo-600 text-white rounded-full p-1 shadow-lg border-2 border-white">
                                            <CheckCircle2 class="w-3 h-3" />
                                        </div>
                                    </label>
                                    <label class="relative flex flex-col p-6 rounded-2xl border-2 cursor-pointer transition-all duration-200"
                                        :class="form.upgrade_type === 'after_expiry' ? 'border-indigo-600 bg-indigo-50 shadow-lg shadow-indigo-50' : 'border-slate-100 hover:border-slate-200 bg-slate-50/50'"
                                    >
                                        <input type="radio" v-model="form.upgrade_type" value="after_expiry" class="sr-only">
                                        <span class="font-black text-lg" :class="form.upgrade_type === 'after_expiry' ? 'text-indigo-600' : 'text-slate-900'">After Expiry</span>
                                        <span class="text-xs font-medium text-slate-400 mt-2 leading-relaxed">Pay full price now and automatically switch when your current plan ends.</span>
                                        <div v-if="form.upgrade_type === 'after_expiry'" class="absolute -top-2 -right-2 bg-indigo-600 text-white rounded-full p-1 shadow-lg border-2 border-white">
                                            <CheckCircle2 class="w-3 h-3" />
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div v-if="paymentMethods.length > 1">
                                <div class="flex items-center gap-2 mb-6">
                                    <CreditCard class="w-5 h-5 text-indigo-600" />
                                    <label class="text-sm font-black text-slate-900 uppercase tracking-widest">Select Payment Method</label>
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
                            </div>

                            <!-- Summary & Submit -->
                            <div class="space-y-6 pt-4">
                                <div class="bg-slate-900 rounded-3xl p-8 text-white flex justify-between items-center shadow-xl shadow-slate-200">
                                    <div>
                                        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">Total to Pay</p>
                                        <p class="text-4xl font-black text-indigo-100">
                                            {{ totalAmount }} 
                                            <span class="text-lg font-bold text-indigo-400">{{ currency }}</span>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-1">New Speed</p>
                                        <p class="text-xl font-black">{{ selectedPackage.download_speed }} Mbps</p>
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
                                        <ArrowRight class="w-6 h-6" />
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
