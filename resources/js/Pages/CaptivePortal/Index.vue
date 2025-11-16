<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const tenant = ref({ business_name: 'Loading...', phone: '' });
const activeTab = ref(null);
const loginForm = ref({ username: '', password: '' });
const voucherCode = ref('');
const packages = ref([]);
const phoneForPayment = ref('');
const selectedPackage = ref(null);
const showPaymentModal = ref(false);
const paymentLoading = ref(false);
const paymentError = ref('');
const paymentSuccess = ref('');
const generatedCredentials = ref(null);

const toggleTab = (tab) => {
    activeTab.value = activeTab.value === tab ? null : tab;
    generatedCredentials.value = null;
};

const fetchTenantDetails = async () => {
    try {
        const { data } = await axios.get('/captive-portal/tenant');
        tenant.value = data;
    } catch (e) {
        console.error('Failed to fetch tenant', e);
    }
};

const fetchPackages = async () => {
    try {
        const { data } = await axios.get('/hotspot/packages');
        packages.value = data.packages;
    } catch (e) {
        console.error('Failed to fetch packages', e);
    }
};

const loginUser = async () => {
    try {
        const { data } = await axios.post(
            '/hotspot/login',
            loginForm.value,
        );
        if (data.success && data.user) {
            generatedCredentials.value = {
                username: data.user.username,
                password: loginForm.value.password,
            };
        } else {
            alert('Invalid credentials');
        }
    } catch {
        alert('Invalid credentials');
    }
};

const submitVoucher = async () => {
    try {
        const { data } = await axios.post('/hotspot/voucher', {
            voucher_code: voucherCode.value,
        });
        if (data.success) {
            generatedCredentials.value = {
                username: data.user.username,
                password: data.user.password,
            };
            alert('You are now connected!');
        } else {
            alert(data.message || 'Invalid voucher');
        }
    } catch {
        alert('Invalid voucher code');
    }
};

const openPaymentModal = (pkg) => {
    selectedPackage.value = pkg;
    paymentError.value = '';
    paymentSuccess.value = '';
    generatedCredentials.value = null;
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    showPaymentModal.value = false;
    phoneForPayment.value = '';
};

const buyPackage = async () => {
    paymentError.value = '';
    paymentSuccess.value = '';
    generatedCredentials.value = null;
    if (!phoneForPayment.value) {
        paymentError.value =
            'Please enter your phone number (07xxxxxxxx or 01xxxxxxxx).';
        return;
    }
    paymentLoading.value = true;
    try {
        const { data } = await axios.post('/hotspot/pay', {
            package_id: selectedPackage.value.id,
            phone: phoneForPayment.value,
        });
        if (data.success) {
            paymentSuccess.value =
                data.message ||
                'STK Push sent. Complete payment on your phone.';
            if (data.credentials) {
                generatedCredentials.value = data.credentials;
            }
        } else {
            paymentError.value = data.message || 'Payment failed.';
        }
    } catch (e) {
        paymentError.value = e.response?.data?.message || 'Payment error.';
    } finally {
        paymentLoading.value = false;
    }
};

const formatPrice = (price) => `Ksh${price}`;

const formatDuration = (pkg) => {
    if (!pkg.duration_value || !pkg.duration_unit) return 'N/A';
    const plural = pkg.duration_value > 1 ? 's' : '';
    return `${pkg.duration_value} ${pkg.duration_unit}${plural}`;
};

onMounted(() => {
    fetchTenantDetails();
    fetchPackages();
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gray-900 p-4 text-gray-100"
    >
        <!-- Business Header -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold">{{ tenant.business_name }}</h1>
            <p class="text-gray-400">Support number: {{ tenant.phone }}</p>
        </div>

        <!-- Login / Voucher Toggle -->
        <div class="mb-6 flex space-x-4">
            <button
                @click="toggleTab('login')"
                :class="[
                    'rounded-lg px-4 py-2 font-medium',
                    activeTab === 'login'
                        ? 'bg-indigo-600'
                        : 'bg-gray-700 hover:bg-gray-600',
                ]"
            >
                Login
            </button>
            <button
                @click="toggleTab('voucher')"
                :class="[
                    'rounded-lg px-4 py-2 font-medium',
                    activeTab === 'voucher'
                        ? 'bg-indigo-600'
                        : 'bg-gray-700 hover:bg-gray-600',
                ]"
            >
                Voucher
            </button>
        </div>

        <!-- Login Form -->
        <div
            v-if="activeTab === 'login'"
            class="mb-6 w-full max-w-md rounded-xl bg-gray-800 p-6 shadow-lg"
        >
            <h2 class="mb-4 text-xl font-semibold">Login</h2>
            <input
                v-model="loginForm.username"
                type="text"
                placeholder="Username"
                class="mb-3 w-full rounded-lg border border-gray-600 bg-gray-700 p-2"
            />
            <input
                v-model="loginForm.password"
                type="password"
                placeholder="Password"
                class="mb-4 w-full rounded-lg border border-gray-600 bg-gray-700 p-2"
            />
            <button
                @click="loginUser"
                class="w-full rounded-lg bg-indigo-600 py-2 font-medium text-white hover:bg-indigo-700"
            >
                Log In
            </button>

            <div
                v-if="generatedCredentials"
                class="mt-4 rounded-lg bg-gray-700 p-3 text-left"
            >
                <p class="font-bold">Your Wi-Fi Access</p>
                <p>Username: {{ generatedCredentials.username }}</p>
                <p>Password: {{ generatedCredentials.password }}</p>
            </div>
        </div>

        <!-- Voucher Form -->
        <div
            v-if="activeTab === 'voucher'"
            class="mb-6 w-full max-w-md rounded-xl bg-gray-800 p-6 shadow-lg"
        >
            <h2 class="mb-4 text-xl font-semibold">Redeem Voucher</h2>
            <input
                v-model="voucherCode"
                type="text"
                placeholder="Enter voucher code"
                class="mb-4 w-full rounded-lg border border-gray-600 bg-gray-700 p-2"
            />
            <button
                @click="submitVoucher"
                class="w-full rounded-lg bg-indigo-600 py-2 font-medium text-white hover:bg-indigo-700"
            >
                Redeem
            </button>

            <div
                v-if="generatedCredentials"
                class="mt-4 rounded-lg bg-gray-700 p-3 text-left"
            >
                <p class="font-bold">Your Wi-Fi Access</p>
                <p>Username: {{ generatedCredentials.username }}</p>
                <p>Password: {{ generatedCredentials.password }}</p>
            </div>
        </div>

        <!-- Packages -->
        <div class="mb-6 w-full max-w-4xl rounded-xl bg-gray-800 p-6 shadow-lg">
            <h3 class="mb-6 text-center text-lg font-semibold text-blue-600">
                Available Packages
            </h3>
            <div
                class="grid grid-cols-1 justify-items-center gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="flex w-full max-w-xs flex-col justify-between rounded-xl bg-gray-700 p-6 text-center shadow-lg"
                >
                    <div>
                        <h3 class="text-lg font-semibold">{{ pkg.name }}</h3>
                        <p class="mb-1 text-sm text-gray-400">
                            {{ formatDuration(pkg) }}
                        </p>
                        <p class="mb-2 text-sm text-gray-400">
                            {{ pkg.download_speed }} Mbps ↓ /
                            {{ pkg.upload_speed }} Mbps ↑
                        </p>
                        <p class="mb-4 text-2xl font-bold">
                            {{ formatPrice(pkg.price) }}
                        </p>
                    </div>
                    <button
                        @click="openPaymentModal(pkg)"
                        class="rounded-lg bg-green-600 py-2 font-medium text-white hover:bg-green-700"
                    >
                        Buy
                    </button>
                </div>
            </div>
        </div>

        <!-- Payment Modal -->
        <div
            v-if="showPaymentModal"
            class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-70"
        >
            <div class="w-full max-w-md rounded-xl bg-gray-800 p-6 shadow-xl">
                <h2 class="mb-4 text-xl font-semibold">Buy Package</h2>
                <p class="mb-2">
                    Selected:
                    <span class="font-bold">{{ selectedPackage?.name }}</span>
                </p>
                <p class="mb-2">
                    Duration:
                    <span class="font-bold">{{
                        formatDuration(selectedPackage)
                    }}</span>
                </p>
                <p class="mb-4">
                    Price:
                    <span class="font-bold">{{
                        formatPrice(selectedPackage?.price)
                    }}</span>
                </p>

                <input
                    v-model="phoneForPayment"
                    type="text"
                    placeholder="Enter phone number (07xxxxxxxx)"
                    class="mb-3 w-full rounded-lg border border-gray-600 bg-gray-700 p-2"
                />

                <div v-if="paymentError" class="mb-2 text-red-400">
                    {{ paymentError }}
                </div>
                <div v-if="paymentSuccess" class="mb-2 text-green-400">
                    {{ paymentSuccess }}
                </div>

                <div
                    v-if="generatedCredentials"
                    class="mt-3 rounded-lg bg-gray-700 p-3 text-left"
                >
                    <p class="font-bold">Your Wi-Fi Access</p>
                    <p>Username: {{ generatedCredentials.username }}</p>
                    <p>Password: {{ generatedCredentials.password }}</p>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="closePaymentModal"
                        class="rounded-lg bg-gray-600 px-4 py-2 hover:bg-gray-500"
                    >
                        Cancel
                    </button>
                    <button
                        @click="buyPackage"
                        class="rounded-lg bg-green-600 px-4 py-2 font-medium hover:bg-green-700"
                        :disabled="paymentLoading"
                    >
                        <span v-if="paymentLoading">Processing...</span>
                        <span v-else>Pay</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
