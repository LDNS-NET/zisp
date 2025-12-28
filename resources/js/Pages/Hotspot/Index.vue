<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
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
const maxPollingAttempts = 20; // Check for up to 10 minutes (20 * 30 seconds)

// Voucher authentication
const voucherCode = ref('');
const isAuthenticatingVoucher = ref(false);
const voucherMessage = ref('');
const voucherError = ref('');
const voucherCredentials = ref(null);

// Packages received from Inertia
const page = usePage();
const hotspots = computed(() => {
    const packages = page.props?.packages || [];
    console.log('Loaded packages:', packages);
    return packages;
});

function openModal(hotspot) {
    console.log('Opening modal for hotspot:', hotspot);
    selectedHotspot.value = hotspot;
    phoneNumber.value = '';
    paymentMessage.value = '';
    paymentError.value = '';
    showModal.value = true;
    console.log('showModal set to:', showModal.value);
}

function closeModal() {
    // Clear any active polling
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
        console.log(`Checking payment status for phone: ${phoneNumber.value}, attempt: ${paymentAttempts.value + 1}`);
        
        const response = await fetch('/hotspot/callback', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                phone: phoneNumber.value,
                hotspot_package_id: selectedHotspot.value.id
            })
        });
        
        const data = await response.json();
        console.log('Payment status response:', data);
        
        if (data.success && data.user) {
            userCredentials.value = data.user;
            paymentMessage.value = data.message;
            paymentError.value = '';
            
            // Stop polling on success
            if (pollingInterval.value) {
                clearInterval(pollingInterval.value);
                pollingInterval.value = null;
            }
            
            // Auto-close modal after showing credentials
            setTimeout(() => {
                closeModal();
            }, 10000);
        } else {
            paymentError.value = data.message || 'Payment not confirmed yet. Please try again in a moment.';
            console.log('Payment not confirmed yet:', data.message);
        }
    } catch (error) {
        console.error('Error checking payment status:', error);
        paymentError.value = 'Failed to check payment status. Please try again.';
    } finally {
        isCheckingPayment.value = false;
    }
}

function startPaymentPolling() {
    console.log('Starting payment polling...');
    paymentAttempts.value = 0;
    
    // Clear any existing polling
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
    }
    
    // Start polling every 30 seconds
    pollingInterval.value = setInterval(async () => {
        paymentAttempts.value++;
        console.log(`Polling attempt ${paymentAttempts.value} of ${maxPollingAttempts}`);
        
        // Check if we've exceeded max attempts
        if (paymentAttempts.value >= maxPollingAttempts) {
            console.log('Max polling attempts reached, stopping polling');
            if (pollingInterval.value) {
                clearInterval(pollingInterval.value);
                pollingInterval.value = null;
            }
            
            paymentError.value = 'Payment confirmation timeout. Please click "Check Payment Status" to try again.';
            return;
        }
        
        // Check payment status
        try {
            const response = await fetch('/hotspot/callback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    phone: phoneNumber.value,
                    hotspot_package_id: selectedHotspot.value.id
                })
            });
            
            const data = await response.json();
            console.log(`Polling response (attempt ${paymentAttempts.value}):`, data);
            
            if (data.success && data.user) {
                console.log('Payment confirmed! Stopping polling and showing credentials');
                userCredentials.value = data.user;
                paymentMessage.value = data.message;
                paymentError.value = '';
                
                // Stop polling on success
                if (pollingInterval.value) {
                    clearInterval(pollingInterval.value);
                    pollingInterval.value = null;
                }
                
                // Auto-close modal after showing credentials
                setTimeout(() => {
                    closeModal();
                }, 10000);
            } else {
                console.log(`Payment still pending (attempt ${paymentAttempts.value}): ${data.message}`);
                // Update message to show we're still checking
                if (!userCredentials.value) {
                    paymentMessage.value = `STK Push sent! Checking payment status... (${paymentAttempts.value}/${maxPollingAttempts})`;
                }
            }
        } catch (error) {
            console.error(`Polling error (attempt ${paymentAttempts.value}):`, error);
            // Continue polling on error, but log it
        }
    }, 30000); // Check every 30 seconds
}

async function processPayment() {
    console.log('Process payment called');
    console.log('Phone number:', phoneNumber.value);
    console.log('Selected hotspot:', selectedHotspot.value);
    
    // Updated validation to accept multiple formats
    if (!phoneNumber.value.match(/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/)) {
        console.log('Phone validation failed');
        paymentError.value = 'Please enter a valid Safaricom number (01XXXXXXXX, 07XXXXXXXX, 254XXXXXXXX, 2541XXXXXXXX, or 2547XXXXXXXX)';
        return;
    }

    console.log('Phone validation passed, starting payment...');
    isProcessing.value = true;
    paymentError.value = '';
    paymentMessage.value = '';

    try {
        const payload = {
            hotspot_package_id: selectedHotspot.value.id,
            phone: phoneNumber.value,
            email: 'customer@example.com' // Optional email
        };
        console.log('Sending payload:', payload);

        const response = await fetch('/hotspot/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        });

        console.log('Response status:', response.status);
        if (response.ok) {
            const data = await response.json();
            console.log('Payment response:', data);
            
            if (data.success) {
                paymentMessage.value = data.message;
                paymentError.value = '';
                
                // Start polling for payment confirmation
                startPaymentPolling();
                
                showToast('STK Push sent successfully! Please complete the payment on your phone.', 'success');
            } else {
                paymentError.value = data.message;
                showToast(data.message, 'error');
            }
        } else {
            const errorData = await response.json();
            console.error('Payment error:', errorData);
            paymentError.value = errorData.message || 'Payment failed';
            showToast(errorData.message || 'Payment failed', 'error');
        }
    } catch (error) {
        console.error('Payment error:', error);
        paymentError.value = 'Payment failed. Please try again.';
        showToast('Payment failed. Please try again.', 'error');
    } finally {
        isProcessing.value = false;
    }
}

// Toast notification function
function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white font-medium z-50 transition-all duration-300 transform translate-x-full`;
    
    // Set color based on type
    if (type === 'success') {
        toast.classList.add('bg-green-500');
    } else if (type === 'error') {
        toast.classList.add('bg-red-500');
    } else {
        toast.classList.add('bg-blue-500');
    }
    
    toast.textContent = message;
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 100);
    
    // Remove after 5 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 5000);
}

function formatPhoneNumber(event) {
    let value = event.target.value.replace(/\D/g, '');
    
    // Only format for display, but keep the original value
    // Let the backend handle the conversion to 2547 format
    if (value.startsWith('01') && value.length >= 10) {
        value = value;
    } else if (value.startsWith('07') && value.length >= 10) {
        value = value;
    } else if (value.startsWith('1') && value.length >= 9) {
        value = '01' + value.substring(1);
    } else if (value.startsWith('7') && value.length >= 9) {
        value = '07' + value;
    } else if (value.startsWith('2547') && value.length >= 12) {
        value = value;
    } else if (value.startsWith('2541') && value.length >= 12) {
        value = value;
    } else if (value.startsWith('254') && value.length >= 12) {
        value = value;
    }
    
    phoneNumber.value = value;
}

async function authenticateVoucher() {
    if (!voucherCode.value || voucherCode.value.trim().length < 6) {
        voucherError.value = 'Please enter a valid voucher code (minimum 6 characters)';
        return;
    }

    isAuthenticatingVoucher.value = true;
    voucherError.value = '';
    voucherMessage.value = '';

    try {
        const response = await fetch('/hotspot/voucher-auth', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                code: voucherCode.value.trim().toUpperCase()
            })
        });

        const data = await response.json();

        if (data.success && data.user) {
            voucherCredentials.value = data.user;
            voucherMessage.value = data.message;
            voucherError.value = '';
            
            // Check for login URL in query params
            const urlParams = new URLSearchParams(window.location.search);
            const loginLink = urlParams.get('login_url') || urlParams.get('link-login') || urlParams.get('link-login-only');
            
            if (loginLink) {
                // Auto-login to MikroTik using GET request to avoid Mixed Content form blocking
                showToast('Authenticating with network...', 'info');
                
                // Construct URL with parameters
                const targetUrl = new URL(loginLink);
                targetUrl.searchParams.append('username', data.user.username);
                targetUrl.searchParams.append('password', data.user.password);
                
                // Add dst if orig link exists
                const origLink = urlParams.get('orig') || urlParams.get('link-orig');
                if (origLink) {
                    targetUrl.searchParams.append('dst', origLink);
                }
                
                // Perform redirect
                window.location.href = targetUrl.toString();
                return; // Stop execution to allow redirect
            }

            showToast('Voucher authenticated! You can now connect to the hotspot.', 'success');
            
            // Auto-clear after 15 seconds
            setTimeout(() => {
                voucherCode.value = '';
                voucherCredentials.value = null;
                voucherMessage.value = '';
            }, 15000);
        } else {
            voucherError.value = data.message || 'Failed to authenticate voucher';
            showToast(data.message || 'Failed to authenticate voucher', 'error');
        }
    } catch (error) {
        console.error('Voucher authentication error:', error);
        voucherError.value = 'An error occurred. Please try again.';
        showToast('An error occurred. Please try again.', 'error');
    } finally {
        isAuthenticatingVoucher.value = false;
    }
}
</script>

<template>
    <Head title="Hotspot" />
    <div class="min-h-screen bg-gradient-to-br from-purple-600 via-blue-600 to-cyan-600 p-4 md:p-8">
        <div class="max-w-7xl mx-auto h-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Left Column: Branding / Info (Desktop) -->
            <div class="lg:col-span-4 lg:sticky lg:top-8 text-white space-y-6">
                <!-- Logo & Brand -->
                <div class="bg-white/10 backdrop-blur-xl rounded-2xl p-8 border border-white/20 shadow-xl">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-full mb-6 overflow-hidden ring-4 ring-white/10">
                        <img v-if="$page.props.tenant?.logo" :src="$page.props.tenant.logo" alt="Logo" class="w-full h-full object-cover" />
                        <svg v-else class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold mb-2">{{ $page.props.tenant?.name || 'Hotspot Access' }}</h1>
                    <p class="text-white/80 leading-relaxed max-w-sm">
                        Welcome to our high-speed network. Choose a package or use a voucher to get started instantly.
                    </p>
                    
                    <!-- Contact Info -->
                    <div v-if="$page.props.tenant?.support_phone || $page.props.tenant?.support_email" class="mt-8 pt-6 border-t border-white/10 space-y-3">
                        <div v-if="$page.props.tenant?.support_phone" class="flex items-center gap-3 text-white/90">
                            <div class="p-2 bg-white/10 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <span class="font-medium">{{ $page.props.tenant.support_phone }}</span>
                        </div>
                        <div v-if="$page.props.tenant?.support_email" class="flex items-center gap-3 text-white/90">
                            <div class="p-2 bg-white/10 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="font-medium">{{ $page.props.tenant.support_email }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Voucher Section Mobile/Desktop Split or integrated? -->
                <!-- Let's put voucher auth here on desktop for easy access -->
                <div class="bg-white rounded-2xl p-6 shadow-xl">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-green-100 text-green-600 rounded-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Have a Voucher?</h3>
                    </div>
                    
                    <div class="space-y-3">
                        <input
                            v-model="voucherCode"
                            type="text"
                            placeholder="Enter Code (e.g. AB123)"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-lg uppercase font-mono tracking-widest text-center"
                            :disabled="isAuthenticatingVoucher"
                            @keyup.enter="authenticateVoucher"
                        />
                        
                        <button
                            @click="authenticateVoucher"
                            :disabled="isAuthenticatingVoucher || !voucherCode"
                            class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold py-3.5 px-4 rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed shadow-lg flex items-center justify-center gap-2"
                        >
                            <svg v-if="isAuthenticatingVoucher" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span v-if="isAuthenticatingVoucher">Connecting...</span>
                            <span v-else>Connect with Voucher</span>
                        </button>
                    </div>

                    <!-- Voucher Success Message -->
                    <div v-if="voucherMessage && voucherCredentials" class="mt-4 bg-green-50 border border-green-200 rounded-xl p-4 animate-fade-in-up">
                        <div class="flex items-center gap-2 mb-2 text-green-800 font-semibold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            {{ voucherMessage }}
                        </div>
                        <div class="space-y-1 text-sm bg-white/50 rounded-lg p-2">
                            <div class="flex justify-between font-medium"><span>User:</span> <span class="font-mono">{{ voucherCredentials.username }}</span></div>
                            <div class="flex justify-between font-medium"><span>Pass:</span> <span class="font-mono">{{ voucherCredentials.password }}</span></div>
                        </div>
                    </div>

                    <!-- Voucher Error Message -->
                    <div v-if="voucherError" class="mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2 animate-shake">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        {{ voucherError }}
                    </div>
                </div>
            </div>

            <!-- Right Column: Packages (Desktop) -->
            <div class="lg:col-span-8">
                <div class="bg-white/90 backdrop-blur-xl rounded-3xl p-6 md:p-8 shadow-2xl">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">Available Packages</h2>
                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold uppercase tracking-wider">
                            {{ hotspots.length }} Plans
                        </span>
                    </div>

                    <!-- Empty State -->
                    <div v-if="hotspots.length === 0" class="text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">No hotspot packages available</h3>
                        <p class="text-gray-500 mt-1">Please check back later</p>
                    </div>

                    <!-- Responsive Grid -->
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 gap-6">
                        <div 
                            v-for="hotspot in hotspots" 
                            :key="hotspot.id" 
                            class="group relative bg-white rounded-2xl border border-gray-100 shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden flex flex-col"
                        >
                            <!-- Gradient Top Border -->
                            <div class="h-2 w-full bg-gradient-to-r from-purple-500 to-blue-500"></div>
                            
                            <div class="p-6 flex-1 flex flex-col">
                                <!-- Tag -->
                                <div class="mb-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-600 uppercase tracking-wider">
                                        {{ hotspot.duration_value }} {{ hotspot.duration_unit }} Access
                                    </span>
                                </div>
                                
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-blue-600 transition-colors">{{ hotspot.name }}</h3>
                                    
                                    <div class="flex items-baseline mb-6">
                                        <span class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-blue-600">KES {{ hotspot.price }}</span>
                                    </div>

                                    <!-- Features List -->
                                    <ul class="space-y-3 mb-6">
                                        <li class="flex items-center text-sm text-gray-600">
                                            <svg class="w-5 h-5 mr-3 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span class="font-medium text-gray-900">{{ hotspot.device_limit }}</span> &nbsp;Devices allowed
                                        </li>
                                        <li class="flex items-center text-sm text-gray-600">
                                            <svg class="w-5 h-5 mr-3 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            <span class="font-medium text-gray-900">{{ hotspot.download_speed }}</span> &nbsp;Download Speed
                                        </li>
                                        <li class="flex items-center text-sm text-gray-600">
                                            <svg class="w-5 h-5 mr-3 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                            <span class="font-medium text-gray-900">{{ hotspot.upload_speed }}</span> &nbsp;Upload Speed
                                        </li>
                                    </ul>
                                </div>

                                <button 
                                    @click="openModal(hotspot)"
                                    class="w-full bg-gray-50 text-gray-900 font-bold py-3 px-4 rounded-xl hover:bg-gradient-to-r hover:from-purple-600 hover:to-blue-600 hover:text-white transition-all duration-300 group-hover:shadow-lg border border-gray-200 hover:border-transparent flex items-center justify-center gap-2"
                                >
                                    Select Package
                                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Checkout Modal (Reused) -->
        <Modal :show="showModal" @close="closeModal">
            <div class="bg-white rounded-2xl overflow-hidden max-w-md w-full mx-auto shadow-2xl">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-purple-600 to-blue-600 p-6 text-white relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                    
                    <div class="flex justify-between items-center mb-1 relative z-10">
                        <h3 class="text-xl font-bold">Complete Purchase</h3>
                        <button @click="closeModal" class="text-white/70 hover:text-white transition-colors p-1 hover:bg-white/10 rounded-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="text-white/80 text-sm relative z-10">Secure payment via M-Pesa</p>
                </div>

                <div class="p-6">
                    <div v-if="selectedHotspot" class="space-y-6">
                        <!-- Package Summary -->
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 flex justify-between items-center">
                            <div>
                                <h4 class="font-bold text-gray-900">{{ selectedHotspot.name }}</h4>
                                <div class="text-xs text-gray-500">{{ selectedHotspot.duration_value }} {{ selectedHotspot.duration_unit }} • {{ selectedHotspot.device_limit }} Devices</div>
                            </div>
                            <div class="text-xl font-bold text-blue-600">
                                KES {{ selectedHotspot.price }}
                            </div>
                        </div>

                        <!-- Phone Input -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">M-Pesa Phone Number</label>
                            <div class="relative">
                                <input
                                    v-model="phoneNumber"
                                    @input="formatPhoneNumber"
                                    type="tel"
                                    placeholder="07XX XXX XXX"
                                    class="w-full pl-4 pr-4 py-3.5 bg-gray-50 border-2 border-gray-200 rounded-xl focus:bg-white focus:border-blue-500 focus:ring-0 text-lg transition-colors font-mono"
                                    :disabled="isProcessing"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Format: 07XXXXXXXX or 01XXXXXXXX</p>
                        </div>

                        <!-- Status Messages (Success/Error) -->
                        <div v-if="paymentMessage" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-start gap-3">
                            <svg class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <div class="flex-1 text-sm">
                                <p class="font-semibold">{{ paymentMessage }}</p>
                                <p v-if="!userCredentials" class="mt-1 text-green-700">Check your phone for the M-Pesa prompt.</p>
                            </div>
                        </div>

                        <div v-if="paymentError" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-start gap-3">
                             <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                             <span class="text-sm font-medium">{{ paymentError }}</span>
                        </div>

                         <!-- Credentials -->
                        <div v-if="userCredentials" class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <h4 class="font-bold text-blue-900 mb-2 text-sm uppercase tracking-wide">Login Credentials</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white p-2 rounded-lg border border-blue-100">
                                    <div class="text-xs text-gray-500 mb-1">Username</div>
                                    <div class="font-mono font-bold text-lg text-blue-600">{{ userCredentials.username }}</div>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-blue-100">
                                    <div class="text-xs text-gray-500 mb-1">Password</div>
                                    <div class="font-mono font-bold text-lg text-blue-600">{{ userCredentials.password }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-2">
                             <button
                                v-if="!paymentMessage" 
                                @click="processPayment"
                                :disabled="isProcessing || !phoneNumber.match(/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/)"
                                class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white font-bold py-4 px-6 rounded-xl hover:from-purple-700 hover:to-blue-700 transition-all shadow-lg transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2"
                            >
                                <span v-if="isProcessing">Processing...</span>
                                <span v-else>Pay KES {{ selectedHotspot.price }}</span>
                            </button>

                            <button 
                                v-if="paymentMessage && !userCredentials"
                                @click="checkPaymentStatus"
                                :disabled="isCheckingPayment"
                                class="w-full bg-blue-600 text-white font-bold py-4 px-6 rounded-xl hover:bg-blue-700 transition-all flex justify-center items-center gap-2"
                            >
                                <span v-if="isCheckingPayment">Checking Status...</span>
                                <span v-else>I have Paid, Check Status</span>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>
