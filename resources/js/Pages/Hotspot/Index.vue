<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage, router, Head } from '@inertiajs/vue3';
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
const maxPollingAttempts = 30; // 30 * 3s = 90 seconds
const paystackPublicKey = ref(null);
const paystackReference = ref(null);
const paystackAccessCode = ref(null);

import { countries } from '@/Data/countries';
const props = defineProps(['tenant', 'packages', 'country', 'paymentMethods']);

const currentCountry = computed(() => {
    return countries.find(c => c.code === props.country) || countries.find(c => c.code === 'KE');
});

// Use payment methods from backend (configured gateways)
const supportedMethods = computed(() => {
    return props.paymentMethods || ['mpesa'];
});

const paymentMethod = ref(supportedMethods.value.length > 0 ? supportedMethods.value[0] : 'mpesa');

const isValidPhoneNumber = computed(() => {
    if (!phoneNumber.value) return false;
    if (currentCountry.value.code === 'KE') {
        return phoneNumber.value.match(/^(01\d{8}|07\d{8}|254\d{9}|2547\d{8}|2541\d{8})$/);
    }
    // Generic validation for other countries: 9 to 15 digits
    return phoneNumber.value.length >= 9 && phoneNumber.value.length <= 15;
});

// Voucher authentication
const voucherCode = ref('');
const isAuthenticatingVoucher = ref(false);
const voucherMessage = ref('');
const voucherError = ref('');
const voucherCredentials = ref(null);

// Member authentication
const activeTab = ref('voucher'); // 'voucher' or 'member'
const memberUsername = ref('');
const memberPassword = ref('');
const isAuthenticatingMember = ref(false);

const logoUrl = ref(null);

// Packages received from Inertia
const page = usePage();
const hotspots = computed(() => {
    const packages = page.props?.packages || [];
    return packages;
});

onMounted(() => {
    // Lazy load the logo to prioritize page rendering
    if (page.props.tenant?.logo) {
        setTimeout(() => {
            logoUrl.value = page.props.tenant.logo;
        }, 300); // Slight delay to ensure content paint first
    }
});

function openModal(hotspot) {
    selectedHotspot.value = hotspot;
    phoneNumber.value = '';
    paymentMessage.value = '';
    paymentError.value = '';
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
    currentPaymentId.value = null;
}

function loginToNetwork(username, password) {
    const urlParams = new URLSearchParams(window.location.search);
    const loginLink = urlParams.get('login_url') || urlParams.get('link-login') || urlParams.get('link-login-only');
    
    if (loginLink) {
        showToast('Authenticating with network...', 'info');
        
        const targetUrl = new URL(loginLink);
        targetUrl.searchParams.append('username', username);
        targetUrl.searchParams.append('password', password);
        
        // Force redirect to system success page to clear Captive Portal reliably
        // Pass credentials as query parameters for user reference on the success page
        const successUrl = new URL('https://' + window.location.host + '/hotspot/success');
        successUrl.searchParams.append('u', username);
        successUrl.searchParams.append('p', password);
        
        targetUrl.searchParams.append('dst', successUrl.toString());
        
        window.location.href = targetUrl.toString();
        return true;
    }
    
    return false;
}

async function authenticateMember() {
    if (!memberUsername.value || !memberPassword.value) {
        showToast('Please enter both username and password', 'error');
        return;
    }

    isAuthenticatingMember.value = true;
    
    try {
        if (!loginToNetwork(memberUsername.value, memberPassword.value)) {
             showToast('Authentication unavailable (Not connected to hotspot network)', 'error');
        }
    } catch (error) {
        console.error('Member auth error:', error);
        showToast('An error occurred during login.', 'error');
    } finally {
        isAuthenticatingMember.value = false;
    }
}

async function authenticateVoucher() {
    if (!voucherCode.value || voucherCode.value.trim().length < 6) {
        voucherError.value = 'Please enter a valid voucher code';
        return;
    }

    isAuthenticatingVoucher.value = true;
    voucherError.value = '';
    voucherMessage.value = '';

    // Check if connected to hotspot (similar to member login)
    const urlParams = new URLSearchParams(window.location.search);
    const loginLink = urlParams.get('login_url') || urlParams.get('link-login') || urlParams.get('link-login-only');
    
    if (!loginLink) {
        voucherError.value = 'Authentication unavailable (Not connected to hotspot network)';
        showToast('Authentication unavailable (Not connected to hotspot network)', 'error');
        isAuthenticatingVoucher.value = false;
        return;
    }

    try {
        const response = await fetch('/hotspot/voucher-auth', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                code: voucherCode.value.trim().toUpperCase(),
                login_link: loginLink
            })
        });

        const data = await response.json();

        if (data.success && data.user) {
            voucherCredentials.value = data.user;
            voucherMessage.value = data.message;
            voucherError.value = '';
            
            if (!loginToNetwork(data.user.username, data.user.password)) {
                showToast('Voucher authenticated! Connect to hotspot to use internet.', 'success');
                
                setTimeout(() => {
                    voucherCode.value = '';
                    voucherCredentials.value = null;
                    voucherMessage.value = '';
                }, 15000);
            }
            return; 
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

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white font-medium z-50 transition-all duration-300 transform translate-x-full shadow-lg`;
    
    if (type === 'success') toast.classList.add('bg-green-600');
    else if (type === 'error') toast.classList.add('bg-red-600');
    else toast.classList.add('bg-blue-600');
    
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 100);
    
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 5000);
}

// ... Payment Logic ...
const currentPaymentId = ref(null);

async function checkPaymentStatus() {
    if (!currentPaymentId.value) return;
    
    isCheckingPayment.value = true;
    try {
        const response = await fetch(`/hotspot/payment-status/${currentPaymentId.value}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                // 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') // GET request doesn't need CSRF token usually, but good to keep if headers are standard
            }
        });
        const data = await response.json();
        
        if (data.success && data.status === 'paid' && data.user) {
            userCredentials.value = data.user;
            paymentMessage.value = 'Payment confirmed! Connecting you now...';
            paymentError.value = '';
            showToast('Payment confirmed!', 'success');
            if (pollingInterval.value) { clearInterval(pollingInterval.value); pollingInterval.value = null; }
            
            // Automatic login
            setTimeout(() => {
                loginToNetwork(data.user.username, data.user.password);
            }, 1000);
        } else {
             if (data.status === 'paid' && !data.user) {
                  // Paid but no user yet? Retry one more time or show meaningful error
                   paymentMessage.value = 'Payment confirmed. Generating user...';
             } else {
                 // Still pending or failed
                 paymentMessage.value = 'Payment not yet confirmed. Please wait...';
             }
        }
    } catch (error) {
        paymentError.value = 'Failed to check payment status.';
    } finally {
        isCheckingPayment.value = false;
    }
}

function startPaymentPolling() {
    paymentAttempts.value = 0;
    if (pollingInterval.value) clearInterval(pollingInterval.value);
    
    // Define polling logic
    const poll = async () => {
        paymentAttempts.value++;
        if (paymentAttempts.value >= maxPollingAttempts) {
            if (pollingInterval.value) clearInterval(pollingInterval.value);
            // Don't show error, just stop auto-polling. User can click "Check Status"
            return;
        }
        
        if (!currentPaymentId.value) return;

        // Visual feedback
        if (!userCredentials.value && !paymentMessage.value.includes('confirmed')) {
             const methodName = paymentMethod.value === 'momo' ? 'MoMo' : 'M-Pesa';
             paymentMessage.value = `Waiting for ${methodName}... (Attempt ${paymentAttempts.value})`;
        }

        try {
            const url = paymentMethod.value === 'momo' 
                ? `/hotspot/momo/status/${currentPaymentId.value}` 
                : `/hotspot/payment-status/${currentPaymentId.value}`;
                
            const response = await fetch(url, {
                 method: 'GET',
                 headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();
            
            if (data.success && (data.status === 'paid' || data.user)) {
                userCredentials.value = data.user;
                paymentMessage.value = 'Payment received! Connecting you now...';
                paymentError.value = '';
                showToast('Payment received!', 'success');
                if (pollingInterval.value) clearInterval(pollingInterval.value);
                
                // Automatic login
                setTimeout(() => {
                    loginToNetwork(data.user.username, data.user.password);
                }, 1000);
            } else {
                 if (data.status === 'pending') {
                     // Keep waiting
                 } else if (data.status === 'failed') {
                     paymentError.value = 'Payment failed or was cancelled.';
                     if (pollingInterval.value) clearInterval(pollingInterval.value);
                 }
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    };

    // Initial check immediately
    poll();

    // Start interval (3 seconds)
    pollingInterval.value = setInterval(poll, 3000); 
}

async function processPayment() {
    if (!isValidPhoneNumber.value) {
        paymentError.value = 'Invalid phone number';
        return;
    }
    
    // Handle Paystack separately with inline popup (now redirect)
    if (paymentMethod.value === 'paystack') {
        await processPaystackPayment();
        return;
    }

    // Handle Flutterwave separately
    if (paymentMethod.value === 'flutterwave') {
        await processFlutterwavePayment();
        return;
    }
    
    isProcessing.value = true;
    paymentError.value = '';
    
    try {
        const url = paymentMethod.value === 'momo' ? '/hotspot/momo/checkout' : '/hotspot/checkout';
        const response = await fetch(url, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') 
            },
            body: JSON.stringify({ 
                hotspot_package_id: selectedHotspot.value.id, 
                phone: phoneNumber.value, 
                email: 'customer@example.com' 
            })
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                paymentMessage.value = data.message;
                if (data.payment_id || data.reference_id) {
                    // For MoMo, use reference_id (UUID). For M-Pesa, use payment_id.
                    currentPaymentId.value = paymentMethod.value === 'momo' 
                        ? (data.reference_id || data.payment_id) 
                        : (data.payment_id || data.reference_id);
                    startPaymentPolling();
                }
                showToast('Payment initiated!', 'success');
            } else {
                paymentError.value = data.message;
                showToast(data.message, 'error');
            }
        } else {
            const errorData = await response.json();
            paymentError.value = errorData.message || 'Payment failed';
            showToast(errorData.message, 'error');
        }
    } catch (error) {
        paymentError.value = 'Payment failed.';
        showToast('Payment failed.', 'error');
    } finally {
        isProcessing.value = false;
    }
}

async function processPaystackPayment() {
    isProcessing.value = true;
    paymentError.value = '';
    
    try {
        // Initialize payment with backend
        const response = await fetch('/hotspot/checkout', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') 
            },
            body: JSON.stringify({ 
                hotspot_package_id: selectedHotspot.value.id, 
                phone: phoneNumber.value, 
                email: props.tenant.email || 'billing@' + props.tenant.subdomain + '.com',
                payment_method: 'paystack'
            })
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.authorization_url) {
                // Redirect to Paystack hosted page (similar to system renewal)
                window.location.href = data.authorization_url;
            } else {
                paymentError.value = data.message || 'Failed to initialize Paystack payment';
                showToast(data.message, 'error');
                isProcessing.value = false;
            }
        } else {
            const errorData = await response.json();
            paymentError.value = errorData.message || 'Payment failed';
            showToast(errorData.message, 'error');
            isProcessing.value = false;
        }
    } catch (error) {
        console.error('Paystack initialization error:', error);
        paymentError.value = 'Payment initialization failed: ' + error.message;
        showToast('Payment failed.', 'error');
        isProcessing.value = false;
    }
}

async function processFlutterwavePayment() {
    isProcessing.value = true;
    paymentError.value = '';
    
    try {
        // Initialize payment with backend
        const response = await fetch('/hotspot/checkout', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') 
            },
            body: JSON.stringify({ 
                hotspot_package_id: selectedHotspot.value.id, 
                phone: phoneNumber.value, 
                email: props.tenant.email || 'billing@' + props.tenant.subdomain + '.com',
                payment_method: 'flutterwave'
            })
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.authorization_url) {
                // Redirect to Flutterwave hosted page
                window.location.href = data.authorization_url;
            } else {
                paymentError.value = data.message || 'Failed to initialize Flutterwave payment';
                showToast(data.message, 'error');
                isProcessing.value = false;
            }
        } else {
            const errorData = await response.json();
            paymentError.value = errorData.message || 'Payment failed';
            showToast(errorData.message, 'error');
            isProcessing.value = false;
        }
    } catch (error) {
        console.error('Flutterwave initialization error:', error);
        paymentError.value = 'Payment initialization failed: ' + error.message;
        showToast('Payment failed.', 'error');
        isProcessing.value = false;
    }
}

function openPaystackPopup() {
    if (!window.PaystackPop) {
        paymentError.value = 'Paystack library not loaded. Please refresh the page.';
        showToast('Paystack not loaded', 'error');
        isProcessing.value = false;
        return;
    }

    try {
        const handler = window.PaystackPop.setup({
            key: paystackPublicKey.value,
            access_code: paystackAccessCode.value,
            onClose: function() {
                isProcessing.value = false;
                paymentError.value = 'Payment cancelled';
                showToast('Payment cancelled', 'error');
            },
            callback: function(response) {
                verifyPaystackPayment(response.reference);
            }
        });
        handler.openIframe();
    } catch (error) {
        console.error('Paystack popup error:', error);
        paymentError.value = 'Failed to open payment popup: ' + error.message;
        showToast('Popup error', 'error');
        isProcessing.value = false;
    }
}

async function verifyPaystackPayment(reference) {
    isProcessing.value = true;
    try {
        const response = await fetch(`/customer/paystack/verify/${reference}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        
        const data = await response.json();
        
        if (data.success && data.status === 'paid' && data.user) {
            userCredentials.value = data.user;
            paymentMessage.value = 'Payment received! Connecting you now...';
            paymentError.value = '';
            isProcessing.value = false;
            showToast('Payment successful!', 'success');
            
            // Automatic login
            setTimeout(() => {
                loginToNetwork(data.user.username, data.user.password);
            }, 1000);
        } else {
            paymentError.value = data.message || 'Payment verification failed.';
            showToast('Payment verification failed', 'error');
            isProcessing.value = false;
        }
    } catch (error) {
        console.error('Paystack verification error:', error);
        paymentError.value = 'Payment verification error: ' + error.message;
        showToast('Verification error', 'error');
        isProcessing.value = false;
    }
}

function formatPhoneNumber(event) {
    let value = event.target.value.replace(/\D/g, '');
    phoneNumber.value = value;
}
</script>

<template>
    <Head title="Hotspot" />
    <div class="min-h-screen bg-slate-50 p-4 md:p-8 relative overflow-hidden">
        <!-- Decorative Background Elements (CSS only, no weight) -->
        <div class="absolute top-0 left-0 w-full h-full pointer-events-none opacity-40">
            <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-blue-100/30 rounded-full blur-[120px]"></div>
            <div class="absolute top-[20%] -right-[10%] w-[30%] h-[30%] bg-indigo-100/20 rounded-full blur-[100px]"></div>
        </div>

        <div class="max-w-7xl mx-auto h-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-start relative z-10">
            
            <!-- Left Column: Branding / Info (Desktop) -->
            <div class="lg:col-span-4 lg:sticky lg:top-8 space-y-6">
                <!-- Branding Card -->
                <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-slate-50 rounded-full mb-6 overflow-hidden ring-4 ring-slate-100 relative shadow-inner">
                        <!-- Lazy loaded logo -->
                        <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="w-full h-full object-cover transition-opacity duration-700 opacity-100" />
                        
                        <!-- Fallback Placeholder -->
                        <svg v-else class="w-10 h-10 text-slate-400 transition-opacity duration-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-slate-900 mb-2">{{ $page.props.tenant?.name || 'Hotspot Access' }}</h1>
                    <p class="text-slate-500 leading-relaxed max-w-sm">
                        Welcome to our high-speed network. Login or choose a package to connect instantly.
                    </p>
                    
                     <!-- Contact Info -->
                    <div v-if="$page.props.tenant?.support_phone || $page.props.tenant?.support_email" class="mt-8 pt-6 border-t border-slate-100 space-y-3">
                        <div v-if="$page.props.tenant?.support_phone" class="flex items-center gap-3 text-slate-600">
                            <div class="p-2 bg-slate-50 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <span class="font-medium">{{ $page.props.tenant.support_phone }}</span>
                        </div>
                        <div v-if="$page.props.tenant?.support_email" class="flex items-center gap-3 text-slate-600">
                            <div class="p-2 bg-slate-50 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="font-medium">{{ $page.props.tenant.support_email }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Login Panel with Tabs -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                    <!-- Tab Switcher -->
                    <div class="flex p-1 bg-gray-100 rounded-xl mb-6">
                        <button 
                            @click="activeTab = 'voucher'"
                            class="flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200"
                            :class="activeTab === 'voucher' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Voucher
                        </button>
                        <button 
                            @click="activeTab = 'member'"
                            class="flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200"
                            :class="activeTab === 'member' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Member Login
                        </button>
                    </div>

                    <!-- Voucher Panel -->
                    <div v-if="activeTab === 'voucher'">
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
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-lg uppercase font-mono tracking-widest text-center text-gray-900"
                                :disabled="isAuthenticatingVoucher"
                                @keyup.enter="authenticateVoucher"
                            />
                            
                            <button
                                @click="authenticateVoucher"
                                :disabled="isAuthenticatingVoucher || !voucherCode"
                                class="w-full bg-emerald-600 text-white font-bold py-3.5 px-4 rounded-xl hover:bg-emerald-700 transition-all duration-300 transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed shadow-sm flex items-center justify-center gap-2"
                            >
                                <svg v-if="isAuthenticatingVoucher" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span v-if="isAuthenticatingVoucher">Connecting...</span>
                                <span v-else>Connect</span>
                            </button>
                        </div>
                    </div>

                    <!-- Member Login Panel -->
                    <div v-else>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-2 bg-blue-100 text-blue-600 rounded-lg">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900">Member Login</h3>
                        </div>
                        
                        <div class="space-y-3">
                            <input
                                v-model="memberUsername"
                                type="text"
                                placeholder="Username"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm text-gray-900"
                                :disabled="isAuthenticatingMember"
                            />
                            <input
                                v-model="memberPassword"
                                type="password"
                                placeholder="Password"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm text-gray-900"
                                :disabled="isAuthenticatingMember"
                                @keyup.enter="authenticateMember"
                            />
                            
                            <button
                                @click="authenticateMember"
                                :disabled="isAuthenticatingMember || !memberUsername || !memberPassword"
                                class="w-full bg-blue-600 text-white font-bold py-3.5 px-4 rounded-xl hover:bg-blue-700 transition-all duration-300 transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed shadow-sm flex items-center justify-center gap-2"
                            >
                                <svg v-if="isAuthenticatingMember" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span v-if="isAuthenticatingMember">Logging in...</span>
                                <span v-else>Login</span>
                            </button>
                        </div>
                    </div>

                    <!-- Shared Message Area -->
                     <div v-if="voucherMessage && voucherCredentials && activeTab === 'voucher'" class="mt-4 bg-green-50 border border-green-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-2 text-green-800 font-semibold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            {{ voucherMessage }}
                        </div>
                    </div>
                     <div v-if="voucherError" class="mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        {{ voucherError }}
                    </div>
                </div>
            </div>

            <!-- Right Column: Packages (Desktop) -->
            <div class="lg:col-span-8">
                <div class="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-slate-900">Available Packages</h2>
                        <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold uppercase tracking-wider">
                            {{ hotspots.length }} Plans
                        </span>
                    </div>

                    <div v-if="hotspots.length === 0" class="text-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">No hotspot packages available</h3>
                        <p class="text-gray-500 mt-1">Please check back later</p>
                    </div>

                    <!-- Organized Row-based Package List -->
                    <div v-else class="space-y-3">
                        <div 
                            v-for="hotspot in hotspots" 
                            :key="hotspot.id" 
                            class="group bg-slate-50/50 rounded-2xl border border-slate-100 p-5 hover:bg-white hover:border-blue-200 transition-all duration-300 hover:shadow-md"
                        >
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                                <!-- Group 1: Name & Speed -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-extrabold text-gray-900 truncate mb-1">
                                        {{ hotspot.name }}
                                    </h3>
                                    <div class="flex items-center text-xs text-blue-600 font-bold bg-blue-50/50 w-fit px-2 py-0.5 rounded-md">
                                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        {{ hotspot.upload_speed }} / {{ hotspot.download_speed }}
                                    </div>
                                </div>

                                <!-- Group 2: Price & Devices -->
                                <div class="flex-1 md:text-center md:border-x md:border-gray-100 md:px-4">
                                    <div class="flex items-center md:justify-center gap-1.5 mb-1">
                                        <span class="text-gray-400 font-black italic text-sm">@</span>
                                        <div class="text-2xl font-black text-gray-900 leading-none">
                                            <span class="text-[10px] font-bold uppercase align-top mr-0.5 mt-1 inline-block">{{ currentCountry.currency }}</span>
                                            {{ hotspot.price }}
                                        </div>
                                    </div>
                                    <div class="flex items-center md:justify-center text-[10px] font-black uppercase tracking-widest text-gray-500">
                                        <svg class="w-3 h-3 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        {{ hotspot.device_limit }} Devices
                                    </div>
                                </div>

                                <!-- Group 3: Action -->
                                <div class="flex-shrink-0">
                                    <button 
                                        @click="openModal(hotspot)"
                                        class="w-full md:w-auto bg-gray-900 text-white font-black py-3 px-10 rounded-xl hover:bg-blue-600 transition-all duration-300 shadow-md hover:shadow-blue-500/20 flex items-center justify-center gap-2 uppercase tracking-tighter text-sm active:scale-95"
                                    >
                                        Buy
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Checkout Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="bg-white rounded-2xl overflow-hidden max-w-md w-full mx-auto shadow-2xl border border-slate-200">
                <div class="bg-slate-900 p-6 text-white relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/5 rounded-full blur-xl"></div>
                    <div class="flex justify-between items-center mb-1 relative z-10">
                        <h3 class="text-xl font-bold text-white">Complete Purchase</h3>
                        <button @click="closeModal" class="text-white/70 hover:text-white transition-colors p-1 hover:bg-white/10 rounded-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="text-white/80 text-sm relative z-10">Secure payment via {{ paymentMethod === 'paystack' ? 'Paystack' : (paymentMethod === 'flutterwave' ? 'Flutterwave' : (paymentMethod === 'momo' ? 'MTN MoMo' : 'M-Pesa')) }}</p>
                </div>

                <div class="p-6">
                    <div v-if="selectedHotspot" class="space-y-6">
                        <!-- Payment Method Selection -->
                        <div v-if="supportedMethods.length > 1" class="flex p-1 bg-gray-100 rounded-xl">
                            <button 
                                v-if="supportedMethods.includes('mpesa')"
                                @click="paymentMethod = 'mpesa'"
                                class="flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200"
                                :class="paymentMethod === 'mpesa' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                M-Pesa
                            </button>
                            <button 
                                v-if="supportedMethods.includes('momo')"
                                @click="paymentMethod = 'momo'"
                                class="flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200"
                                :class="paymentMethod === 'momo' ? 'bg-white text-yellow-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                MTN MoMo
                            </button>
                            <button 
                                v-if="supportedMethods.includes('paystack')"
                                @click="paymentMethod = 'paystack'"
                                class="flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200"
                                :class="paymentMethod === 'paystack' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                Paystack
                            </button>
                            <button 
                                v-if="supportedMethods.includes('flutterwave')"
                                @click="paymentMethod = 'flutterwave'"
                                class="flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200"
                                :class="paymentMethod === 'flutterwave' ? 'bg-white text-orange-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            >
                                Flutterwave
                            </button>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 flex justify-between items-center">
                            <div>
                                <h4 class="font-bold text-gray-900">{{ selectedHotspot.name }}</h4>
                                <div class="text-xs text-gray-500">{{ selectedHotspot.duration_value }} {{ selectedHotspot.duration_unit }} • {{ selectedHotspot.device_limit }} Devices</div>
                            </div>
                            <div class="text-xl font-bold text-blue-600">{{ currentCountry.currency }} {{ selectedHotspot.price }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                {{ paymentMethod === 'paystack' ? 'Phone Number (for your account)' : (paymentMethod === 'momo' ? 'MoMo Phone Number' : 'M-Pesa Phone Number') }} 
                                ({{ currentCountry.dial_code }})
                            </label>
                            <div class="relative">
                                <input
                                    v-model="phoneNumber"
                                    @input="formatPhoneNumber"
                                    type="tel"
                                    placeholder="Enter phone number"
                                    class="w-full pl-4 pr-4 py-3.5 bg-gray-50 border-2 border-gray-200 rounded-xl focus:bg-white focus:border-blue-500 focus:ring-0 text-lg transition-colors font-mono"
                                    :disabled="isProcessing"
                                />
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Format: {{ currentCountry.code === 'KE' ? '07XXXXXXXX or 01XXXXXXXX' : 'e.g. ' + currentCountry.dial_code + 'XXXXXXXX' }}</p>
                        </div>

                        <div v-if="paymentMessage" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-start gap-3">
                            <svg class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <div class="flex-1 text-sm">
                                <p class="font-semibold">{{ paymentMessage }}</p>
                                <p v-if="!userCredentials" class="mt-1 text-green-700">Check your phone for the payment prompt.</p>
                            </div>
                        </div>

                        <div v-if="paymentError" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-start gap-3">
                             <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                             <span class="text-sm font-medium">{{ paymentError }}</span>
                        </div>

                        <div v-if="userCredentials" class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <h4 class="font-bold text-blue-900 mb-2 text-sm uppercase tracking-wide">Login Credentials</h4>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="bg-white p-2 rounded-lg border border-blue-100">
                                    <div class="text-xs text-gray-500 mb-1">Username</div>
                                    <div class="font-mono font-bold text-lg text-blue-600">{{ userCredentials.username }}</div>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-blue-100">
                                    <div class="text-xs text-gray-500 mb-1">Password</div>
                                    <div class="font-mono font-bold text-lg text-blue-600">{{ userCredentials.password }}</div>
                                </div>
                            </div>
                            <div class="bg-blue-600 text-white p-3 rounded-lg flex items-center justify-between">
                                <div class="text-xs font-bold uppercase opacity-80">Validity Period</div>
                                <div class="font-black text-sm uppercase tracking-tighter">Starts on first login: {{ userCredentials.duration }}</div>
                            </div>
                        </div>

                        <div class="pt-2">
                             <button
                                v-if="!paymentMessage" 
                                @click="processPayment"
                                :disabled="isProcessing || !isValidPhoneNumber"
                                class="w-full bg-slate-900 text-white font-bold py-4 px-6 rounded-xl hover:bg-blue-600 transition-all shadow-lg transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2"
                            >
                                <span v-if="isProcessing">Processing...</span>
                                <span v-else>Pay {{ currentCountry.currency }} {{ selectedHotspot.price }}</span>
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
