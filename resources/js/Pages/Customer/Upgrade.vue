<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { countries } from '@/Data/countries';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';

const props = defineProps({
    user: Object,
    currentPackage: Object,
    packages: Array,
    gateways: Array,
});

const page = usePage();
const tenantCountryCode = computed(() => page.props.tenant?.country_code || 'KE');
const currentCountry = computed(() => countries.find(c => c.code === tenantCountryCode.value) || countries.find(c => c.code === 'KE'));

const availableMethods = computed(() => {
    return props.gateways ? props.gateways.map(g => g.provider) : [];
});

const form = useForm({
    phone: props.user.phone || '',
    package_id: null,
    provider: availableMethods.value.length > 0 ? availableMethods.value[0] : '',
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
        if (attempts > 30) {
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
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Upgrade Plan</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="showSuccess" class="text-center py-12">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-lg font-medium text-gray-900">Upgrade Successful!</h3>
                            <p class="mt-1 text-sm text-gray-500">Your plan has been upgraded.</p>
                            <div class="mt-6">
                                <Link :href="route('customer.dashboard')" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Go to Dashboard
                                </Link>
                            </div>
                        </div>

                        <div v-else>
                            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">Available Packages</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                                <div v-for="pkg in packages" :key="pkg.id" 
                                    class="border rounded-xl p-6 cursor-pointer transition-all hover:shadow-lg"
                                    :class="{'border-indigo-500 ring-2 ring-indigo-200': selectedPackage?.id === pkg.id, 'border-gray-200': selectedPackage?.id !== pkg.id}"
                                    @click="selectPackage(pkg)"
                                >
                                    <h4 class="text-lg font-bold text-gray-900">{{ pkg.name }}</h4>
                                    <div class="mt-2 text-3xl font-bold text-indigo-600">
                                        {{ pkg.price }} {{ $page.props.tenant?.currency }}
                                    </div>
                                    <div class="mt-4 space-y-2 text-sm text-gray-600">
                                        <p>{{ pkg.download_speed }} Mbps Download</p>
                                        <p>{{ pkg.upload_speed }} Mbps Upload</p>
                                        <p>{{ pkg.device_limit }} Devices</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="selectedPackage" class="bg-gray-50 p-6 rounded-xl border border-gray-200 max-w-md mx-auto">
                                <h4 class="text-lg font-medium text-gray-900 mb-4">Confirm Upgrade</h4>
                                <p class="text-sm text-gray-600 mb-4">
                                    You are upgrading to <strong>{{ selectedPackage.name }}</strong>.
                                    The new price is <strong>{{ selectedPackage.price }} {{ $page.props.tenant?.currency }}</strong>.
                                </p>

                                <form @submit.prevent="submit" class="space-y-4">
                                    <div v-if="gateways.length > 0">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div v-for="gateway in gateways" :key="gateway.provider" 
                                                class="relative flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50 focus:outline-none"
                                                :class="{'border-indigo-500 ring-2 ring-indigo-200': form.provider === gateway.provider, 'border-gray-300': form.provider !== gateway.provider}"
                                                @click="form.provider = gateway.provider">
                                                <div class="flex items-center h-5">
                                                    <input type="radio" v-model="form.provider" :value="gateway.provider" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <span class="font-medium text-gray-900">
                                                        {{ gateway.label || (gateway.provider === 'momo' ? 'MTN MoMo' : (gateway.provider === 'mpesa' ? 'M-Pesa' : gateway.provider)) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="p-4 bg-yellow-50 border-l-4 border-yellow-400">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700">
                                                    No payment methods available. Please contact support.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 flex items-center">
                                                <span class="h-full px-3 py-2 border-r border-gray-300 bg-gray-50 text-gray-500 sm:text-sm rounded-l-md flex items-center">
                                                    +{{ currentCountry.dial_code }}
                                                </span>
                                            </div>
                                            <input type="text" v-model="form.phone" 
                                                class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-16 sm:text-sm border-gray-300 rounded-md" 
                                                :placeholder="currentCountry.code === 'GH' ? '2XXXXXXXX' : '7XXXXXXXX'" 
                                                required>
                                        </div>
                                    </div>

                                    <div v-if="paymentError" class="text-red-600 text-sm">{{ paymentError }}</div>
                                    <div v-if="paymentMessage" class="text-blue-600 text-sm">{{ paymentMessage }}</div>

                                    <button type="submit" :disabled="isProcessing || gateways.length === 0" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span v-if="isProcessing">Processing...</span>
                                        <span v-else>Pay Now</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
