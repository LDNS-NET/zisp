```vue
<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage, router, Head } from '@inertiajs/vue3';
// Standard buttons for weight reduction instead of components if needed, but let's keep them if they are small.
// However, to make it "instantly" load, we should avoid too many component overheads.
// But let's keep them and focus on the CSS.
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
const paystackPublicKey = ref(null);
const deviceMac = ref('');
const maxPollingAttempts = 40; // 40 * 3s = 120s (2 minutes)

import { countries } from '@/Data/countries';
const props = defineProps(['tenant', 'packages', 'country', 'paymentMethods', 'categories', 'groupedPackages', 'settings']);

// Default active category
const activeCategory = ref(null);

onMounted(() => {
    // Capture MAC address from URL
    const urlParams = new URLSearchParams(window.location.search);
    deviceMac.value = urlParams.get('mac') || '';

    // Lazy load the logo to prioritize page rendering
    if (page.props.tenant?.logo) {
        setTimeout(() => {
            logoUrl.value = page.props.tenant.logo;
        }, 300); // Slight delay to ensure content paint first
    }

    // Set initial category
    if (props.categories && props.categories.length > 0) {
        activeCategory.value = props.categories[0].name;
    } else if (props.groupedPackages && Object.keys(props.groupedPackages).length > 0) {
        activeCategory.value = Object.keys(props.groupedPackages)[0];
    } else {
        activeCategory.value = 'General';
    }
});

const currentTemplate = computed(() => props.settings?.portal_template || 'default');

const theme = computed(() => {
    const t = currentTemplate.value;
    if (t === 'modern-dark') {
        return {
            layout: 'sidebar',
            bg: 'bg-slate-950',
            card: 'bg-slate-900/70 border-slate-800 shadow-2xl shadow-black/20',
            text: 'text-white',
            subtext: 'text-slate-400',
            accent: 'text-blue-400',
            accentBg: 'bg-blue-500/10',
            button: 'bg-blue-600 hover:bg-blue-500',
            secondaryButton: 'bg-slate-800 hover:bg-slate-700 text-white',
            input: 'bg-slate-900 border-slate-700 text-white focus:ring-blue-500',
            badge: 'bg-slate-800 text-slate-300',
            tabActive: 'bg-slate-800 text-blue-400 shadow-lg shadow-black/20',
            tabInactive: 'text-slate-400 hover:text-slate-200',
            packageCard: 'bg-slate-900 border-slate-800 hover:border-blue-500/50 hover:shadow-blue-500/10',
            decorative: 'opacity-10'
        };
    }
    if (t === 'vibrant-gradient') {
        return {
            layout: 'hero',
            bg: 'bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500',
            card: 'bg-white/95 border-white/20 shadow-2xl shadow-indigo-950/30',
            text: 'text-slate-900',
            subtext: 'text-slate-600',
            accent: 'text-indigo-600',
            accentBg: 'bg-indigo-50',
            button: 'bg-gradient-to-r from-indigo-600 to-purple-600 hover:shadow-lg hover:shadow-indigo-500/30',
            secondaryButton: 'bg-slate-100 hover:bg-slate-200 text-slate-800',
            input: 'bg-white border-slate-200 focus:ring-indigo-500',
            badge: 'bg-indigo-50 text-indigo-700',
            tabActive: 'bg-indigo-600 text-white shadow-lg shadow-indigo-200',
            tabInactive: 'bg-white/50 text-slate-600 hover:bg-white/80',
            packageCard: 'bg-white border-slate-100 hover:border-indigo-400 hover:shadow-indigo-500/5',
            decorative: 'opacity-40'
        };
    }
    if (t === 'glassmorphism') {
        return {
            layout: 'centered',
            bg: 'bg-[#0f172a] bg-[radial-gradient(ellipse_at_top_left,_var(--tw-gradient-stops))] from-blue-900 via-slate-900 to-black',
            card: 'backdrop-blur-xl bg-white/10 border border-white/10 shadow-2xl shadow-black/50',
            text: 'text-white',
            subtext: 'text-blue-100/70',
            accent: 'text-blue-300',
            accentBg: 'bg-white/5',
            button: 'backdrop-blur-md bg-white/20 hover:bg-white/30 text-white border border-white/20 shadow-lg',
            secondaryButton: 'bg-white/5 hover:bg-white/10 text-white',
            input: 'bg-black/20 border-white/10 text-white placeholder:text-white/30 focus:bg-black/40',
            badge: 'bg-white/10 text-blue-200',
            tabActive: 'bg-white/20 text-white border border-white/20 shadow-inner',
            tabInactive: 'text-white/60 hover:text-white hover:bg-white/5',
            packageCard: 'backdrop-blur-md bg-white/5 border-white/10 hover:border-white/30 hover:bg-white/10',
            decorative: 'opacity-50'
        };
    }
    if (t === 'minimalist-clean') {
        return {
            layout: 'editorial',
            bg: 'bg-white',
            card: 'bg-white border-[1px] border-slate-100 shadow-none',
            text: 'text-black',
            subtext: 'text-slate-500',
            accent: 'text-black font-black',
            accentBg: 'bg-slate-50',
            button: 'bg-black hover:bg-slate-800 text-white rounded-none tracking-widest uppercase text-[10px]',
            secondaryButton: 'bg-white border border-black hover:bg-slate-50 text-black rounded-none',
            input: 'bg-white border-slate-200 rounded-none focus:border-black focus:ring-0',
            badge: 'bg-slate-100 text-slate-800 rounded-none',
            tabActive: 'border-b-2 border-black text-black font-black',
            tabInactive: 'text-slate-400 hover:text-black',
            packageCard: 'bg-white border-slate-100 hover:border-black rounded-none p-8',
            decorative: 'hidden'
        };
    }
    if (t === 'corporate-split') {
        return {
            layout: 'split',
            bg: 'bg-slate-50',
            card: 'bg-white shadow-2xl overflow-hidden',
            text: 'text-slate-900',
            subtext: 'text-slate-500',
            accent: 'text-blue-700',
            accentBg: 'bg-blue-50',
            button: 'bg-blue-700 hover:bg-blue-800',
            secondaryButton: 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-none',
            input: 'bg-white border-slate-200 focus:border-blue-700',
            badge: 'bg-blue-50 text-blue-700',
            tabActive: 'bg-blue-700 text-white font-bold',
            tabInactive: 'text-slate-500 hover:text-blue-700',
            packageCard: 'bg-white border border-slate-100 hover:border-blue-700/30 shadow-md hover:shadow-xl',
            decorative: 'bg-blue-900',
            heroImage: 'corporate_hotspot_bg'
        };
    }
    // Default
    return {
        layout: 'classic',
        bg: 'bg-slate-50',
        card: 'bg-white border-slate-200 shadow-sm',
        text: 'text-slate-900',
        subtext: 'text-slate-500',
        accent: 'text-blue-600',
        accentBg: 'bg-slate-50',
        button: 'bg-blue-600 hover:bg-slate-900',
        secondaryButton: 'bg-slate-50 hover:bg-slate-100 text-slate-700',
        input: 'bg-white border-gray-200 focus:ring-blue-500',
        badge: 'bg-slate-100 text-slate-700',
        tabActive: 'bg-blue-600 text-white shadow-lg shadow-blue-200',
        tabInactive: 'bg-slate-50 text-slate-600 hover:bg-slate-100',
        packageCard: 'bg-white border-slate-100 hover:border-blue-400 hover:shadow-blue-500/5',
        decorative: 'opacity-40'
    };
});

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

const currentHotspots = computed(() => {
    return props.groupedPackages ? (props.groupedPackages[activeCategory.value] || []) : props.packages;
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
        if (!currentPaymentId.value) return;
        
        paymentAttempts.value++;
        
        // Stop polling if we reached max attempts
        if (paymentAttempts.value >= maxPollingAttempts) {
            if (pollingInterval.value) {
                clearInterval(pollingInterval.value);
                pollingInterval.value = null;
            }
            isCheckingPayment.value = false;
            paymentMessage.value = 'Auto-check completed. If you have paid, please click the button below.';
            return;
        }
        
        isCheckingPayment.value = true;

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
                
                if (pollingInterval.value) {
                    clearInterval(pollingInterval.value);
                    pollingInterval.value = null;
                }
                isCheckingPayment.value = false;
                
                // Automatic login
                setTimeout(() => {
                    loginToNetwork(data.user.username, data.user.password);
                }, 1500);
            } else {
                 if (data.status === 'failed') {
                     paymentError.value = 'Payment failed or was cancelled.';
                     if (pollingInterval.value) {
                         clearInterval(pollingInterval.value);
                         pollingInterval.value = null;
                     }
                     isCheckingPayment.value = false;
                 } else {
                     // Still pending, update message occasionally
                     const methodName = paymentMethod.value === 'momo' ? 'MoMo' : 'M-Pesa';
                     paymentMessage.value = `Waiting for ${methodName} payment...`;
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
                email: 'customer@example.com',
                mac: deviceMac.value
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
                payment_method: 'paystack',
                mac: deviceMac.value
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
                payment_method: 'flutterwave',
                mac: deviceMac.value
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


function formatPhoneNumber(event) {
    let value = event.target.value.replace(/\D/g, '');
    phoneNumber.value = value;
}
</script>

<template>
    <Head title="Hotspot" />
    <div :class="['min-h-screen relative flex flex-col transition-all duration-700', theme.bg, theme.text, theme.layout === 'split' ? 'h-screen overflow-hidden p-0' : 'p-4 md:p-8']">
        <!-- Background Asset for Split/Corporate -->
        <div v-if="theme.layout === 'split'" class="hidden lg:block lg:w-1/2 h-full relative overflow-hidden">
             <img :src="'/storage/brain/' + $page.props.conversationId + '/' + theme.heroImage + '.png'" class="absolute inset-0 w-full h-full object-cover" alt="Enterprise Connect" />
             <div class="absolute inset-0 bg-blue-900/40 backdrop-blur-[2px]"></div>
             <div class="absolute inset-0 flex flex-col justify-end p-20 text-white">
                <div class="max-w-md">
                    <h2 class="text-5xl font-black mb-6 leading-tight">Fast. Secure. Uninterrupted.</h2>
                    <p class="text-xl text-white/80 leading-relaxed mb-10">Connected to our premium enterprise network. Experience the speed of light with ZISP connectivity.</p>
                </div>
             </div>
        </div>

        <!-- Decorative Background Elements (Non-split) -->
        <div v-if="theme.layout !== 'split'" :class="['absolute top-0 left-0 w-full h-full pointer-events-none transition-opacity duration-700', theme.decorative]">
            <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] bg-blue-500/10 rounded-full blur-[120px]"></div>
            <div class="absolute top-[20%] -right-[10%] w-[30%] h-[30%] bg-indigo-500/10 rounded-full blur-[100px]"></div>
        </div>

        <!-- LAYOUT ROUTER -->
        <div :class="[
            'relative z-10 w-full mx-auto transition-all duration-700',
            theme.layout === 'split' ? 'lg:w-1/2 lg:ml-auto h-full flex flex-col overflow-y-auto p-8 lg:p-16' : 'max-w-7xl',
            theme.layout === 'centered' ? 'flex items-center justify-center py-12 md:py-20' : '',
        ]">
            
            <div :class="[
                'grid gap-8 items-start',
                theme.layout === 'sidebar' || theme.layout === 'classic' ? 'grid-cols-1 lg:grid-cols-12' : 'grid-cols-1',
                theme.layout === 'centered' ? 'max-w-3xl w-full' : '',
                theme.layout === 'split' ? 'max-w-xl w-full mx-auto' : ''
            ]">
            
            <!-- Left Column: Branding / Info (Desktop) -->
            <div :class="[
                'space-y-6',
                theme.layout === 'sidebar' || theme.layout === 'classic' ? 'lg:col-span-4 lg:sticky lg:top-8' : '',
                theme.layout === 'hero' ? 'max-w-4xl mx-auto w-full text-center' : '',
                theme.layout === 'centered' ? 'w-full text-center mb-0' : '',
                theme.layout === 'split' ? 'w-full' : ''
            ]">
                <!-- Branding Card -->
                <div v-if="theme.layout !== 'split'" :class="[
                    'rounded-2xl border transition-all duration-700', 
                    theme.layout === 'hero' ? 'bg-transparent border-none p-0 pb-8' : 'p-8',
                    theme.card
                ]">
                    <div :class="[
                        'inline-flex items-center justify-center rounded-full mb-6 overflow-hidden ring-4 relative shadow-inner transition-all duration-700 mx-auto lg:mx-0', 
                        theme.accentBg, 
                        currentTemplate === 'default' ? 'ring-slate-100' : 'ring-white/5',
                        theme.layout === 'hero' || theme.layout === 'centered' ? 'lg:mx-auto' : '',
                        theme.layout === 'hero' ? 'w-32 h-32' : 'w-20 h-20'
                    ]">
                        <!-- Lazy loaded logo -->
                        <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="w-full h-full object-cover" />
                        
                        <!-- Fallback Placeholder -->
                        <svg v-else :class="['w-10 h-10 transition-colors', theme.subtext]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                        </svg>
                    </div>
                    <h1 :class="['font-bold mb-2', theme.text, theme.layout === 'hero' ? 'text-5xl lg:text-7xl mb-4' : 'text-3xl']">
                        {{ $page.props.tenant?.name || 'Hotspot Access' }}
                    </h1>
                    <p :class="['leading-relaxed transition-all mx-auto lg:mx-0', theme.subtext, theme.layout === 'hero' ? 'text-xl max-w-2xl lg:mx-auto' : 'max-w-sm']">
                        Welcome to our high-speed network. Login or choose a package to connect instantly.
                    </p>
                    
                     <!-- Contact Info (Hidden in Hero) -->
                    <div v-if="theme.layout !== 'hero' && ($page.props.tenant?.support_phone || $page.props.tenant?.support_email)" :class="['mt-8 pt-6 border-t space-y-3', currentTemplate === 'default' ? 'border-slate-100' : 'border-white/5']">
                        <div v-if="$page.props.tenant?.support_phone" :class="['flex items-center gap-3', theme.subtext]">
                            <div :class="['p-2 rounded-lg transition-all', theme.accentBg]">
                                <svg :class="['w-5 h-5', theme.accent]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <span class="font-medium">{{ $page.props.tenant.support_phone }}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Split Layout Branding -->
                <div v-if="theme.layout === 'split'" class="mb-12">
                     <div class="flex items-center gap-4 mb-4">
                         <div :class="['w-12 h-12 rounded-xl flex items-center justify-center transition-all', theme.accentBg]">
                            <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="w-8 h-8 object-contain" />
                            <svg v-else :class="['w-6 h-6', theme.accent]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                            </svg>
                         </div>
                         <h1 :class="['text-2xl font-black uppercase tracking-tighter', theme.text]">
                             {{ $page.props.tenant?.name || 'Hotspot Access' }}
                         </h1>
                     </div>
                     <p :class="['text-lg', theme.subtext]">Welcome back. Please select a connection method below to continue.</p>
                </div>
                
                <!-- Login Panel with Tabs -->
                <div :class="[
                    'rounded-2xl p-6 border transition-all duration-700', 
                    theme.layout === 'hero' ? 'max-w-2xl mx-auto w-full -mt-4' : '',
                    theme.card
                ]">
                    <!-- Tab Switcher -->
                    <div :class="['flex p-1 rounded-xl mb-6', currentTemplate === 'default' ? 'bg-gray-100' : 'bg-white/5 shadow-inner']">
                        <button 
                            @click="activeTab = 'voucher'"
                            :class="['flex-1 py-2.5 text-sm font-bold rounded-lg transition-all duration-300', activeTab === 'voucher' ? theme.tabActive : theme.tabInactive]"
                        >
                            Voucher
                        </button>
                        <button 
                            @click="activeTab = 'member'"
                            :class="['flex-1 py-2.5 text-sm font-bold rounded-lg transition-all duration-300', activeTab === 'member' ? theme.tabActive : theme.tabInactive]"
                        >
                            Member Login
                        </button>
                    </div>

                    <!-- Voucher Panel -->
                    <div v-show="activeTab === 'voucher'" class="animate-in fade-in slide-in-from-bottom-2 duration-700">
                        <div class="flex items-center gap-3 mb-5">
                            <div :class="['p-2 rounded-xl transition-all shadow-sm', theme.accentBg]">
                                <svg :class="['w-6 h-6 transition-colors', theme.accent]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                                </svg>
                            </div>
                            <h3 :class="['text-lg font-bold transition-colors', theme.text]">Enter Voucher Code</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <input
                                v-model="voucherCode"
                                type="text"
                                placeholder="Code (e.g. AB123)"
                                :class="['w-full px-6 py-4 border-2 rounded-2xl focus:outline-none focus:ring-0 text-xl uppercase font-mono tracking-widest text-center shadow-inner transition-all', theme.input]"
                                :disabled="isAuthenticatingVoucher"
                                @keyup.enter="authenticateVoucher"
                            />
                            
                            <button
                                @click="authenticateVoucher"
                                :disabled="isAuthenticatingVoucher || !voucherCode"
                                :class="['w-full text-white font-bold py-4 px-6 rounded-2xl transition-all duration-300 transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed shadow-xl flex items-center justify-center gap-3 hover:gap-4', theme.button]"
                            >
                                <svg v-if="isAuthenticatingVoucher" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span v-if="isAuthenticatingVoucher">Validating...</span>
                                <span v-else>Connect Now</span>
                                <svg v-if="!isAuthenticatingVoucher" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Member Panel -->
                    <div v-show="activeTab === 'member'" class="animate-in fade-in slide-in-from-bottom-2 duration-700">
                        <div class="flex items-center gap-3 mb-5">
                            <div :class="['p-2 rounded-xl transition-all shadow-sm', theme.accentBg]">
                                <svg :class="['w-6 h-6 transition-colors', theme.accent]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <h3 :class="['text-lg font-bold transition-colors', theme.text]">Member Login</h3>
                        </div>
                        <div class="space-y-4">
                            <input
                                v-model="memberUsername"
                                type="text"
                                placeholder="Username"
                                :class="['w-full px-5 py-3.5 border-2 rounded-2xl transition-all', theme.input]"
                                :disabled="isAuthenticatingMember"
                            />
                            <input
                                v-model="memberPassword"
                                type="password"
                                placeholder="Password"
                                :class="['w-full px-5 py-3.5 border-2 rounded-2xl transition-all', theme.input]"
                                :disabled="isAuthenticatingMember"
                                @keyup.enter="authenticateMember"
                            />
                            <button
                                @click="authenticateMember"
                                :disabled="isAuthenticatingMember || !memberUsername || !memberPassword"
                                :class="['w-full text-white font-bold py-4 px-6 rounded-2xl transition-all duration-300 transform active:scale-[0.98] shadow-xl flex items-center justify-center gap-3', theme.button]"
                            >
                                <svg v-if="isAuthenticatingMember" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ isAuthenticatingMember ? 'Logging in...' : 'Sign In' }}</span>
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

            <!-- Right Column: Packages & Categories -->
            <div :class="[
                'transition-all duration-700 space-y-8',
                theme.layout === 'sidebar' || theme.layout === 'classic' ? 'lg:col-span-8' : 'w-full',
                theme.layout === 'centered' ? 'max-w-4xl mx-auto' : ''
            ]">
                <!-- Section Header -->
                <div v-if="theme.layout !== 'hero' && theme.layout !== 'centered'" class="flex items-baseline justify-between mb-2">
                    <h2 :class="['text-2xl font-black transition-colors uppercase tracking-tight', theme.text]">Select Package</h2>
                    <div :class="['h-1 w-12 rounded-full bg-blue-600', theme.accentBg]"></div>
                </div>

                <!-- Category Navigation -->
                <div v-if="props.categories && props.categories.length > 0" class="relative group">
                    <div class="flex items-center gap-2 overflow-x-auto pb-4 no-scrollbar scroll-smooth">
                        <button 
                            v-for="category in props.categories" 
                            :key="category.id"
                            @click="activeCategory = category.name"
                            :class="[
                                'whitespace-nowrap px-6 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 border-2',
                                activeCategory === category.name 
                                    ? theme.tabActive + ' border-transparent' 
                                    : 'border-transparent ' + theme.tabInactive
                            ]"
                        >
                            {{ category.name }}
                        </button>
                    </div>
                </div>

                <!-- Packages Grid -->
                <div v-if="currentHotspots.length > 0" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                    <div 
                        v-for="hotspot in currentHotspots" 
                        :key="hotspot.id" 
                        :class="['group relative rounded-3xl border p-6 transition-all duration-500 flex flex-col justify-between overflow-hidden cursor-pointer hover:-translate-y-2', theme.packageCard]"
                        @click="openModal(hotspot)"
                    >
                        <!-- Speed / Badge Decor -->
                        <div :class="['absolute -top-4 -right-4 w-24 h-24 blur-2xl transition-opacity group-hover:opacity-60 opacity-0', theme.accentBg.replace('bg-', 'bg-').replace('/10', '/30')]"></div>
                        
                        <div class="relative z-10 h-full flex flex-col justify-between">
                            <div class="flex justify-between items-start mb-6">
                                <div :class="['p-3 rounded-2xl transition-all shadow-sm', theme.accentBg]">
                                    <svg :class="['w-6 h-6', theme.accent]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <div v-if="hotspot.is_popular" :class="['px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest', theme.badge]">Popular</div>
                            </div>

                            <div class="space-y-1 mb-8">
                                <h3 :class="['text-xl font-black transition-colors', theme.text]">{{ hotspot.name }}</h3>
                                <div :class="['text-xs font-bold uppercase tracking-widest flex items-center gap-2', theme.subtext]">
                                    <span v-if="hotspot.device_limit">{{ hotspot.device_limit }} Devices</span>
                                    <span v-if="hotspot.device_limit && hotspot.download_speed" class="opacity-30">•</span>
                                    <span v-if="hotspot.download_speed">{{ hotspot.download_speed }}Mbps Max</span>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-slate-100 dark:border-white/5 flex items-end justify-between">
                                <div>
                                    <div :class="['text-[10px] font-black uppercase tracking-wider mb-1', theme.subtext]">Duration</div>
                                    <div :class="['font-black text-lg', theme.text]">
                                        {{ hotspot.validity || (hotspot.duration_value + ' ' + (hotspot.duration_unit || 'Days')) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div :class="['text-[10px] font-black uppercase tracking-wider mb-1', theme.subtext]">Price</div>
                                    <div :class="['text-2xl font-black', theme.accent]">
                                        {{ currentCountry.currency }} <span class="tracking-tighter">{{ Math.round(hotspot.price) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="currentHotspots.length === 0" :class="['text-center py-20 rounded-3xl border-2 border-dashed transition-all', currentTemplate === 'default' ? 'border-slate-100' : 'border-white/5']">
                    <p :class="['text-lg font-medium', theme.subtext]">No packages found in this category.</p>
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

                <div :class="['p-6 transition-all duration-700', theme.bg]">
                    <div v-if="selectedHotspot" class="space-y-6">
                        <!-- Payment Method Selection -->
                        <div v-if="supportedMethods.length > 1" :class="['flex p-1 rounded-xl mb-6 transition-colors', currentTemplate === 'default' ? 'bg-gray-100' : 'bg-white/5']">
                            <button 
                                v-if="supportedMethods.includes('mpesa')"
                                @click="paymentMethod = 'mpesa'"
                                :class="['flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200', paymentMethod === 'mpesa' ? theme.tabActive : theme.tabInactive]"
                            >
                                M-Pesa
                            </button>
                            <button 
                                v-if="supportedMethods.includes('momo')"
                                @click="paymentMethod = 'momo'"
                                :class="['flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200', paymentMethod === 'momo' ? theme.tabActive : theme.tabInactive]"
                            >
                                MTN MoMo
                            </button>
                            <button 
                                v-if="supportedMethods.includes('paystack')"
                                @click="paymentMethod = 'paystack'"
                                :class="['flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200', paymentMethod === 'paystack' ? theme.tabActive : theme.tabInactive]"
                            >
                                Paystack
                            </button>
                            <button 
                                v-if="supportedMethods.includes('flutterwave')"
                                @click="paymentMethod = 'flutterwave'"
                                :class="['flex-1 py-2 text-sm font-bold rounded-lg transition-all duration-200', paymentMethod === 'flutterwave' ? theme.tabActive : theme.tabInactive]"
                            >
                                Flutterwave
                            </button>
                        </div>
                        <div :class="['rounded-xl p-4 border flex justify-between items-center transition-all duration-700', theme.card]">
                            <div>
                                <h4 :class="['font-bold', theme.text]">{{ selectedHotspot.name }}</h4>
                                <div :class="['text-xs', theme.subtext]">{{ selectedHotspot.duration_value }} {{ selectedHotspot.duration_unit }} • {{ selectedHotspot.device_limit }} Devices</div>
                            </div>
                            <div :class="['text-xl font-bold', theme.accent]">{{ currentCountry.currency }} {{ selectedHotspot.price }}</div>
                        </div>

                        <div>
                            <label :class="['block text-sm font-bold mb-2', theme.text]">
                                {{ paymentMethod === 'paystack' ? 'Phone Number (for your account)' : (paymentMethod === 'momo' ? 'MoMo Phone Number' : 'M-Pesa Phone Number') }} 
                                ({{ currentCountry.dial_code }})
                            </label>
                            <div class="relative">
                                <input
                                    v-model="phoneNumber"
                                    @input="formatPhoneNumber"
                                    type="tel"
                                    placeholder="Enter phone number"
                                    :class="['w-full pl-4 pr-4 py-3.5 border-2 rounded-xl focus:bg-white text-lg transition-all font-mono', theme.input]"
                                    :disabled="isProcessing"
                                />
                            </div>
                            <p :class="['text-xs mt-2', theme.subtext]">Format: {{ currentCountry.code === 'KE' ? '07XXXXXXXX or 01XXXXXXXX' : 'e.g. ' + currentCountry.dial_code + 'XXXXXXXX' }}</p>
                        </div>

                        <div v-if="paymentMessage" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-start gap-3 relative overflow-hidden">
                            <div v-if="isCheckingPayment && !userCredentials" class="absolute bottom-0 left-0 h-1 bg-green-500/30 animate-pulse w-full"></div>
                            
                            <div v-if="isCheckingPayment && !userCredentials" class="mt-0.5 flex-shrink-0">
                                <svg class="animate-spin h-5 w-5 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <svg v-else class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            
                            <div class="flex-1 text-sm">
                                <p class="font-semibold">{{ paymentMessage }}</p>
                                <p v-if="!userCredentials && isCheckingPayment" class="mt-1 text-green-700 text-xs">Waiting for carrier confirmation. Please keep this window open.</p>
                                <p v-else-if="!userCredentials" class="mt-1 text-green-700">Check your phone for the payment prompt.</p>
                            </div>
                        </div>

                        <div v-if="paymentError" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-start gap-3">
                             <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                             <span class="text-sm font-medium">{{ paymentError }}</span>
                        </div>

                        <div v-if="userCredentials" :class="['rounded-xl p-4 transition-all duration-700', theme.card]">
                            <h4 :class="['font-bold mb-2 text-sm uppercase tracking-wide', theme.text]">Login Credentials</h4>
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div :class="['p-2 rounded-lg border transition-all', currentTemplate === 'default' ? 'bg-white border-blue-100' : 'bg-white/5 border-white/10']">
                                    <div :class="['text-xs mb-1', theme.subtext]">Username</div>
                                    <div :class="['font-mono font-bold text-lg', theme.accent]">{{ userCredentials.username }}</div>
                                </div>
                                <div :class="['p-2 rounded-lg border transition-all', currentTemplate === 'default' ? 'bg-white border-blue-100' : 'bg-white/5 border-white/10']">
                                    <div :class="['text-xs mb-1', theme.subtext]">Password</div>
                                    <div :class="['font-mono font-bold text-lg', theme.accent]">{{ userCredentials.password }}</div>
                                </div>
                            </div>
                            <div :class="['p-3 rounded-lg flex items-center justify-between transition-all shadow-lg', theme.button]">
                                <div class="text-xs font-bold uppercase opacity-80">Validity Period</div>
                                <div class="font-black text-sm uppercase tracking-tighter">Starts on first login: {{ userCredentials.duration }}</div>
                            </div>
                        </div>

                        <div class="pt-2">
                             <button
                                v-if="!paymentMessage" 
                                @click="processPayment"
                                :disabled="isProcessing || !isValidPhoneNumber"
                                :class="['w-full font-bold py-4 px-6 rounded-xl transition-all shadow-lg transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2 text-white', theme.button]"
                            >
                                <span v-if="isProcessing">Processing...</span>
                                <span v-else>Pay {{ currentCountry.currency }} {{ selectedHotspot.price }}</span>
                            </button>

                            <button 
                                v-if="paymentMessage && !userCredentials"
                                @click="checkPaymentStatus"
                                :disabled="isCheckingPayment"
                                :class="['w-full font-bold py-4 px-6 rounded-xl transition-all flex justify-center items-center gap-2 text-white', theme.button]"
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
