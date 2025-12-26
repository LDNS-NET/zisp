<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const showModal = ref(false);
const selectedHotspot = ref(null);
const phoneNumber = ref('');
const isProcessing = ref(false);
const paymentMessage = ref('');
const paymentError = ref('');
const userCredentials = ref(null);
const isCheckingPayment = ref(false);
const pollingInterval = ref(null);
const paymentAttempts = ref(0);
const maxPollingAttempts = 20;

const page = usePage();
const hotspots = computed(() => page.props.value.packages || []);

function openModal(hotspot) {
    selectedHotspot.value = hotspot;
    phoneNumber.value = '';
    paymentMessage.value = '';
    paymentError.value = '';
    userCredentials.value = null;
    showModal.value = true;
}

function closeModal() {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
    showModal.value = false;
    selectedHotspot.value = null;
    phoneNumber.value = '';
    paymentMessage.value = '';
    paymentError.value = '';
    userCredentials.value = null;
    isCheckingPayment.value = false;
    paymentAttempts.value = 0;
}

async function checkPaymentStatus() {
    if (!selectedHotspot.value || !phoneNumber.value) return;

    isCheckingPayment.value = true;

    try {
        const response = await fetch('/hotspot/callback', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                phone: phoneNumber.value,
                package_id: selectedHotspot.value.id
            })
        });

        const data = await response.json();

        if (data.success && data.user) {
            userCredentials.value = data.user;
            paymentMessage.value = data.message || 'Payment confirmed!';
            paymentError.value = '';
            if (pollingInterval.value) {
                clearInterval(pollingInterval.value);
                pollingInterval.value = null;
            }
            setTimeout(() => closeModal(), 10000);
        } else {
            paymentError.value = data.message || 'Payment not confirmed yet.';
        }
    } catch (error) {
        paymentError.value = 'Failed to check payment status. Try again.';
        console.error(error);
    } finally {
        isCheckingPayment.value = false;
    }
}

function startPaymentPolling() {
    paymentAttempts.value = 0;

    if (pollingInterval.value) clearInterval(pollingInterval.value);

    pollingInterval.value = setInterval(async () => {
        paymentAttempts.value++;
        if (paymentAttempts.value >= maxPollingAttempts) {
            clearInterval(pollingInterval.value);
            pollingInterval.value = null;
            paymentError.value = 'Payment confirmation timeout. Click "Check Payment Status".';
            return;
        }
        await checkPaymentStatus();
    }, 30000);
}

async function processPayment() {
    if (!phoneNumber.value.match(/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/)) {
        paymentError.value = 'Enter a valid Safaricom number';
        return;
    }

    isProcessing.value = true;
    paymentError.value = '';
    paymentMessage.value = '';

    try {
        const response = await fetch('/hotspot/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                package_id: selectedHotspot.value.id,
                phone: phoneNumber.value,
                email: 'customer@example.com'
            })
        });

        const data = await response.json();

        if (data.success) {
            paymentMessage.value = data.message;
            startPaymentPolling();
            showToast('STK Push sent! Complete payment on your phone.', 'success');
        } else {
            paymentError.value = data.message;
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error(error);
        paymentError.value = 'Payment failed. Try again.';
        showToast('Payment failed. Try again.', 'error');
    } finally {
        isProcessing.value = false;
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white z-50 transition-all duration-300 transform translate-x-full ${
        type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 100);
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => document.body.removeChild(toast), 300);
    }, 5000);
}

function formatPhoneNumber(event) {
    let value = event.target.value.replace(/\D/g, '');
    if (value.startsWith('7') && value.length >= 9) value = '07' + value;
    phoneNumber.value = value;
}
</script>


<template>
    <Head title="Hotspot" />
    <div class="min-h-screen bg-gradient-to-br from-purple-600 via-blue-600 to-cyan-600 flex items-center justify-center p-4">
        <!-- Smartphone-sized Container -->
        <div class="w-full max-w-sm">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 backdrop-blur-lg rounded-full mb-4 overflow-hidden">
                    <img v-if="$page.props.tenant?.logo" :src="$page.props.tenant.logo" alt="Logo" class="w-full h-full object-cover" />
                    <svg v-else class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">{{ $page.props.tenant?.name || 'Hotspot Packages' }}</h1>
                <p class="text-white/80">Choose your perfect internet plan</p>
                
                <!-- Contact Info -->
                <div v-if="$page.props.tenant?.support_phone || $page.props.tenant?.support_email" class="mt-4 flex flex-col items-center gap-1 text-sm text-white/70">
                    <div v-if="$page.props.tenant?.support_phone" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        {{ $page.props.tenant.support_phone }}
                    </div>
                    <div v-if="$page.props.tenant?.support_email" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        {{ $page.props.tenant.support_email }}
                    </div>
                </div>
            </div>

            <!-- Main Content Area - Smartphone Screen Size -->
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden" style="aspect-ratio: 9/19.5;">
                <!-- Header Bar -->
                <div class="bg-gradient-to-r from-purple-600 to-blue-600 px-4 py-4 text-center">
                    <h2 class="text-white font-semibold text-lg">Available Packages</h2>
                </div>

                <!-- Scrollable Content Area -->
                <div class="h-full overflow-y-auto bg-gradient-to-b from-gray-50 to-white">
                    <div class="p-4 pb-6">
                        <!-- Package Cards -->
                        <div v-if="hotspots.length === 0" class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <p class="text-gray-500 text-sm">No hotspot packages available</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div 
                                v-for="hotspot in hotspots" 
                                :key="hotspot.id" 
                                class="group relative bg-white rounded-xl border border-gray-200 shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden"
                            >
                                <!-- Gradient Border Effect -->
                                <div class="absolute inset-0 bg-gradient-to-r from-purple-600 via-blue-600 to-cyan-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-xl"></div>
                                
                                <!-- Card Content -->
                                <div class="relative bg-white rounded-xl p-4">
                                    <!-- Header -->
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-bold text-gray-900 mb-1">{{ hotspot.name }}</h3>
                                            <div class="flex items-center text-xs text-gray-600">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                {{ hotspot.duration_value }} {{ hotspot.duration_unit }}
                                            </div>
                                        </div>
                                        <div class="text-right ml-3">
                                            <div class="text-xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-blue-600">
                                                KES {{ hotspot.price }}
                                            </div>
                                            <div class="text-xs text-gray-500">one-time</div>
                                        </div>
                                    </div>

                                    <!-- Features -->
                                    <div class="grid grid-cols-2 gap-2 mb-4">
                                        <div class="flex items-center text-xs text-gray-600">
                                            <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ hotspot.device_limit }} Devices
                                        </div>
                                        <div class="flex items-center text-xs text-gray-600">
                                            <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ hotspot.download_speed }}
                                        </div>
                                        <div class="flex items-center text-xs text-gray-600">
                                            <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ hotspot.upload_speed }}
                                        </div>
                                        <div class="flex items-center text-xs text-gray-600">
                                            <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ hotspot.burst_limit }}
                                        </div>
                                    </div>

                                    <!-- Action Button -->
                                    <button 
                                        @click="openModal(hotspot)"
                                        class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold py-2.5 px-4 rounded-lg hover:from-purple-700 hover:to-blue-700 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] shadow-md text-sm"
                                    >
                                        Get Started
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Hint -->
            <div class="text-center mt-6">
                <p class="text-white/60 text-xs">Scroll for more packages</p>
            </div>
        </div>

        <!-- Checkout Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="bg-white rounded-2xl overflow-hidden max-w-md w-full mx-4">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-purple-600 to-blue-600 p-6 text-white">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold">Complete Purchase</h3>
                        <button @click="closeModal" class="text-white/80 hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="text-white/90">Secure payment with M-Pesa</p>
                </div>

                <div class="p-6">
                    <div v-if="selectedHotspot" class="space-y-6">
                        <!-- Package Details Card -->
                        <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl p-6 border border-purple-100">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 mb-2">{{ selectedHotspot.name }}</h4>
                                    <div class="flex items-center text-sm text-gray-600 mb-3">
                                        <svg class="w-4 h-4 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        {{ selectedHotspot.duration_value }} {{ selectedHotspot.duration_unit }}
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            {{ selectedHotspot.device_limit }} Devices
                                        </span>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ selectedHotspot.download_speed }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-blue-600">
                                        KES {{ selectedHotspot.price }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Phone Number Input -->
                        <div>
                            <label for="phone" class="block text-sm font-semibold text-gray-900 mb-3">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    M-Pesa Phone Number
                                </div>
                            </label>
                            <div class="relative">
                                <input
                                    id="phone"
                                    v-model="phoneNumber"
                                    @input="formatPhoneNumber"
                                    type="tel"
                                    placeholder="01XXXXXXXX, 07XXXXXXXX, 254XXXXXXXX, 2541XXXXXXXX, or 2547XXXXXXXX"
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-lg transition-all duration-200"
                                    :disabled="isProcessing"
                                />
                                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                    <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                    </svg>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Enter Safaricom number: 01XXXXXXXX, 07XXXXXXXX, 254XXXXXXXX, or 2547XXXXXXXX</p>
                            <p class="text-xs text-blue-500 mt-1 font-mono">"{{ phoneNumber }}" - Valid: {{ !!phoneNumber.match(/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/) }}</p>
                        </div>

                        <!-- Payment Messages -->
                        <div v-if="paymentMessage" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div class="flex-1">
                                {{ paymentMessage }}
                                <div v-if="paymentAttempts > 0 && !userCredentials" class="text-xs text-green-600 mt-1">
                                    Checking payment status... ({{ paymentAttempts }}/{{ maxPollingAttempts }})
                                </div>
                            </div>
                            <div v-if="paymentAttempts > 0 && !userCredentials" class="ml-2">
                                <svg class="animate-spin h-4 w-4 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- User Credentials Display -->
                        <div v-if="userCredentials" class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl">
                            <h4 class="font-semibold text-blue-900 mb-2">Your Hotspot Account</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="font-medium">Username:</span>
                                    <span class="font-mono bg-blue-100 px-2 py-1 rounded">{{ userCredentials.username }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Password:</span>
                                    <span class="font-mono bg-blue-100 px-2 py-1 rounded">{{ userCredentials.password }}</span>
                                </div>
                                <div v-if="userCredentials.expires_at" class="flex justify-between">
                                    <span class="font-medium">Expires:</span>
                                    <span>{{ new Date(userCredentials.expires_at).toLocaleString() }}</span>
                                </div>
                                <div v-if="userCredentials.package_name" class="flex justify-between">
                                    <span class="font-medium">Package:</span>
                                    <span>{{ userCredentials.package_name }}</span>
                                </div>
                            </div>
                            <p class="text-xs text-blue-600 mt-2">Save these credentials! This window will close in 10 seconds.</p>
                        </div>

                        <div v-if="paymentError" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            {{ paymentError }}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-8 flex gap-3">
                        <button 
                            @click="closeModal" 
                            :disabled="isProcessing || isCheckingPayment"
                            class="flex-1 px-6 py-3 border-2 border-gray-200 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Cancel
                        </button>
                        
                        <!-- Check Payment Status Button -->
                        <button 
                            v-if="paymentMessage && !userCredentials"
                            @click="checkPaymentStatus" 
                            :disabled="isCheckingPayment"
                            class="flex-1 bg-blue-600 text-white font-semibold py-3 px-6 rounded-xl hover:bg-blue-700 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                        >
                            <span v-if="isCheckingPayment" class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Checking...
                            </span>
                            <span v-else class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Check Payment Status
                            </span>
                        </button>
                        
                        <!-- Pay Now Button -->
                        <button 
                            v-if="!paymentMessage"
                            @click="processPayment" 
                            :disabled="isProcessing || !phoneNumber.match(/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/)"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-blue-600 text-white font-semibold py-3 px-6 rounded-xl hover:from-purple-700 hover:to-blue-700 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none shadow-lg"
                        >
                            <span v-if="isProcessing" class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                            <span v-else class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                Pay Now with IntaSend STK Push
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
