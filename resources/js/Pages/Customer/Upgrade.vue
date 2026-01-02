<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    user: Object,
    currentPackage: Object,
    packages: Array,
});

const form = useForm({
    phone: props.user.phone || '',
    package_id: null,
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
        const response = await axios.post(route('customer.upgrade.momo'), form);
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
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Phone Number (MoMo)</label>
                                        <input type="text" v-model="form.phone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                                    </div>

                                    <div v-if="paymentError" class="text-red-600 text-sm">{{ paymentError }}</div>
                                    <div v-if="paymentMessage" class="text-blue-600 text-sm">{{ paymentMessage }}</div>

                                    <button type="submit" :disabled="isProcessing" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                                        <span v-if="isProcessing">Processing...</span>
                                        <span v-else>Pay & Upgrade</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
