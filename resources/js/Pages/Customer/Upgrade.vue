<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { countries } from '@/Data/countries';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import { Wifi, Smartphone, CreditCard, CheckCircle, AlertCircle, Loader2, ArrowUpCircle } from 'lucide-vue-next';

const props = defineProps({
    user: Object,
    currentPackage: Object,
    packages: Array,
    gateways: Array,
});

const page = usePage();
const tenantCountryCode = computed(() => page.props.tenant?.country_code || 'KE');
const currentCountry = computed(() => countries.find(c => c.code === tenantCountryCode.value) || countries.find(c => c.code === 'KE'));

const availableGateways = computed(() => props.gateways || []);

const form = useForm({
    phone: props.user.phone || '',
    package_id: null,
    provider: availableGateways.value.length > 0 ? availableGateways.value[0].provider : '',
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
    // Scroll to payment form
    setTimeout(() => {
        document.getElementById('payment-section')?.scrollIntoView({ behavior: 'smooth' });
    }, 100);
};

const submit = async () => {
    if (!form.package_id) {
        paymentError.value = 'Please select a package to upgrade to.';
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
        if (attempts > 60) {
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
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900">Upgrade Plan</h1>
                    <p class="mt-1 text-sm text-gray-500">Choose a better plan that suits your needs.</p>
                </div>

                <div v-if="showSuccess" class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100 p-12 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                        <CheckCircle class="h-8 w-8 text-green-600" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Upgrade Successful!</h3>
                    <p class="mt-2 text-gray-500">Your plan has been upgraded successfully.</p>
                    <div class="mt-8">
                        <Link :href="route('customer.dashboard')" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                            Return to Dashboard
                        </Link>
                    </div>
                </div>

                <div v-else class="space-y-8">
                    <!-- Package Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="pkg in packages" :key="pkg.id" 
                            class="relative bg-white rounded-xl border-2 transition-all duration-200 cursor-pointer hover:shadow-lg flex flex-col"
                            :class="selectedPackage?.id === pkg.id ? 'border-indigo-600 ring-2 ring-indigo-100' : 'border-gray-100 hover:border-indigo-200'"
                            @click="selectPackage(pkg)"
                        >
                            <div class="p-6 flex-1">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="p-2 bg-indigo-50 rounded-lg">
                                        <Wifi class="w-6 h-6 text-indigo-600" />
                                    </div>
                                    <span v-if="selectedPackage?.id === pkg.id" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        Selected
                                    </span>
                                </div>
                                
                                <h3 class="text-lg font-bold text-gray-900 mb-2">{{ pkg.name }}</h3>
                                <div class="flex items-baseline mb-6">
                                    <span class="text-3xl font-extrabold text-gray-900">{{ pkg.price }}</span>
                                    <span class="ml-1 text-gray-500">{{ $page.props.tenant?.currency }} / {{ pkg.duration_unit }}</span>
                                </div>

                                <ul class="space-y-3 text-sm text-gray-500">
                                    <li class="flex items-center">
                                        <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                                        {{ pkg.download_speed }} Mbps Download
                                    </li>
                                    <li class="flex items-center">
                                        <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                                        {{ pkg.upload_speed }} Mbps Upload
                                    </li>
                                    <li class="flex items-center">
                                        <CheckCircle class="w-4 h-4 text-green-500 mr-2" />
                                        {{ pkg.device_limit || 'Unlimited' }} Devices
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="p-4 bg-gray-50 rounded-b-xl border-t border-gray-100">
                                <button class="w-full py-2 px-4 rounded-lg text-sm font-medium transition-colors"
                                    :class="selectedPackage?.id === pkg.id ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'">
                                    {{ selectedPackage?.id === pkg.id ? 'Selected' : 'Select Plan' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Section -->
                    <div id="payment-section" v-if="selectedPackage" class="max-w-3xl mx-auto bg-white overflow-hidden shadow-sm rounded-xl border border-gray-100">
                        <div class="p-6 border-b border-gray-100 bg-gray-50">
                            <h3 class="text-lg font-medium text-gray-900">Complete Upgrade</h3>
                            <p class="mt-1 text-sm text-gray-500">You are upgrading to <span class="font-bold text-gray-900">{{ selectedPackage.name }}</span></p>
                        </div>
                        
                        <div class="p-6">
                            <form @submit.prevent="submit" class="space-y-6">
                                <!-- Payment Methods -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-3">Payment Method</label>
                                    
                                    <div v-if="availableGateways.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div v-for="gateway in availableGateways" :key="gateway.provider" 
                                            class="relative flex items-center p-4 border rounded-xl cursor-pointer transition-all hover:bg-gray-50"
                                            :class="{'border-indigo-500 ring-2 ring-indigo-200 bg-indigo-50': form.provider === gateway.provider, 'border-gray-200': form.provider !== gateway.provider}"
                                            @click="form.provider = gateway.provider">
                                            <div class="flex items-center h-5">
                                                <input type="radio" v-model="form.provider" :value="gateway.provider" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                            </div>
                                            <div class="ml-3 flex items-center">
                                                <Smartphone v-if="['mpesa', 'momo', 'airtel'].includes(gateway.provider)" class="w-5 h-5 text-gray-400 mr-2" />
                                                <CreditCard v-else class="w-5 h-5 text-gray-400 mr-2" />
                                                <span class="font-medium text-gray-900">
                                                    {{ gateway.label || gateway.provider }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else class="rounded-lg bg-yellow-50 p-4 border border-yellow-200">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <AlertCircle class="h-5 w-5 text-yellow-400" />
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-yellow-800">No Payment Methods Available</h3>
                                                <div class="mt-2 text-sm text-yellow-700">
                                                    <p>Please contact support to upgrade your plan manually.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Phone Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                    <div class="mt-1 relative rounded-lg shadow-sm">
                                        <div class="absolute inset-y-0 left-0 flex items-center">
                                            <span class="h-full px-3 py-2 border-r border-gray-300 bg-gray-50 text-gray-500 sm:text-sm rounded-l-lg flex items-center font-medium">
                                                +{{ currentCountry.dial_code }}
                                            </span>
                                        </div>
                                        <input type="text" v-model="form.phone" 
                                            class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-16 py-2.5 sm:text-sm border-gray-300 rounded-lg" 
                                            :placeholder="currentCountry.code === 'GH' ? '2XXXXXXXX' : '7XXXXXXXX'" 
                                            required>
                                    </div>
                                </div>

                                <!-- Total & Submit -->
                                <div class="pt-6 border-t border-gray-100">
                                    <div class="flex justify-between items-center mb-6">
                                        <span class="text-gray-600 font-medium">Total Amount</span>
                                        <span class="text-2xl font-bold text-gray-900">{{ selectedPackage.price }} {{ $page.props.tenant?.currency }}</span>
                                    </div>

                                    <div v-if="paymentError" class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-start">
                                        <AlertCircle class="w-5 h-5 mr-2 flex-shrink-0" />
                                        <span>{{ paymentError }}</span>
                                    </div>

                                    <div v-if="paymentMessage" class="mb-4 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 text-sm flex items-start">
                                        <Loader2 class="w-5 h-5 mr-2 flex-shrink-0 animate-spin" />
                                        <span>{{ paymentMessage }}</span>
                                    </div>

                                    <button type="submit" 
                                        :disabled="isProcessing || availableGateways.length === 0" 
                                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                                        <span v-if="isProcessing" class="flex items-center">
                                            <Loader2 class="w-4 h-4 mr-2 animate-spin" />
                                            Processing Payment...
                                        </span>
                                        <span v-else>Pay & Upgrade Now</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
