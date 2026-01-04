<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useToast } from 'vue-toastification';
import { countries } from '@/Data/countries';
import { computed } from 'vue';

const toast = useToast();

const props = defineProps({
    gateways: { type: Array, default: () => [] },
    phone_number: { type: String, default: '' },
    country: { type: String, default: 'KE' },
    disabled_gateways: { type: Array, default: () => [] },
});

const currentCountry = computed(() => countries.find(c => c.code === props.country) || countries[0]);
const supportedMethods = computed(() => {
    const methods = currentCountry.value.payment_methods || [];
    
    if (props.disabled_gateways && props.disabled_gateways.length > 0) {
        return methods.filter(method => !props.disabled_gateways.includes(method));
    }
    
    return methods;
});

// Use the first gateway record if any
const existing = props.gateways[0] || {};

// Helper to map payout_method to collection_method
const getInitialCollectionMethod = (gateway) => {
    if (!gateway) return props.country === 'KE' ? 'phone' : 'paystack';
    
    // Check for Custom M-Pesa first
    if (gateway.provider === 'mpesa' && props.country === 'KE' && (gateway.use_own_api === 1 || gateway.use_own_api === true)) {
        return 'custom_mpesa';
    }

    if (gateway.provider === 'momo') return 'momo';
    if (gateway.provider === 'airtel_money') return 'airtel_money';
    if (gateway.provider === 'paystack') return 'paystack';
    if (gateway.provider === 'flutterwave') return 'flutterwave';
    if (gateway.provider === 'bank') return 'bank';
    
    if (gateway.provider === 'mpesa') {
        switch (gateway.payout_method) {
            case 'mpesa_phone': return 'phone';
            case 'till': return 'mpesa_till';
            case 'paybill': return 'mpesa_paybill';
            default: return 'phone';
        }
    }
    return 'phone';
};

// Find the first active or relevant gateway to show initially
const initialGateway = props.gateways.find(g => g.is_active) || props.gateways[0] || {};

const form = useForm({
    provider: initialGateway.provider || (props.country === 'KE' ? 'mpesa' : 'paystack'),
    payout_method: initialGateway.payout_method || (props.country === 'KE' ? 'mpesa_phone' : ''),
    collection_method: getInitialCollectionMethod(initialGateway),
    phone_number: initialGateway.phone_number || props.phone_number || '',
    bank_name: initialGateway.bank_name || '',
    bank_account: initialGateway.bank_account || '',
    bank_paybill: initialGateway.bank_paybill || '',
    till_number: initialGateway.till_number || '',
    paybill_business_number: initialGateway.paybill_business_number || '',
    paybill_account_number: initialGateway.paybill_account_number || '',
    mpesa_consumer_key: initialGateway.mpesa_consumer_key || '',
    mpesa_consumer_secret: initialGateway.mpesa_consumer_secret || '',
    mpesa_shortcode: initialGateway.mpesa_shortcode || '',
    mpesa_passkey: initialGateway.mpesa_passkey || '',
    mpesa_env: initialGateway.mpesa_env || 'sandbox',
    paystack_public_key: initialGateway.paystack_public_key || '',
    paystack_secret_key: initialGateway.paystack_secret_key || '',
    flutterwave_public_key: initialGateway.flutterwave_public_key || '',
    flutterwave_secret_key: initialGateway.flutterwave_secret_key || '',
    momo_api_user: initialGateway.momo_api_user || '',
    momo_api_key: initialGateway.momo_api_key || '',
    momo_subscription_key: initialGateway.momo_subscription_key || '',
    momo_env: initialGateway.momo_env || 'sandbox',
    airtel_client_id: initialGateway.airtel_client_id || '',
    airtel_client_secret: initialGateway.airtel_client_secret || '',
    airtel_env: initialGateway.airtel_env || 'sandbox',
    use_own_api: initialGateway.use_own_api === 1 || initialGateway.use_own_api === true || false,
    is_active: initialGateway.is_active ?? true,
});

// Watch for collection method changes to load existing data for that provider
import { watch } from 'vue';
watch(() => form.collection_method, (newMethod) => {
    let targetProvider = 'mpesa';
    let targetPayoutMethod = '';
    let useOwnApi = false;

    if (newMethod === 'momo') targetProvider = 'momo';
    else if (newMethod === 'airtel_money') targetProvider = 'airtel_money';
    else if (newMethod === 'paystack') targetProvider = 'paystack';
    else if (newMethod === 'flutterwave') targetProvider = 'flutterwave';
    else if (newMethod === 'bank') targetProvider = 'bank';
    else if (newMethod === 'custom_mpesa') { targetProvider = 'mpesa'; useOwnApi = true; targetPayoutMethod = 'mpesa_phone'; }
    else if (newMethod === 'phone') { targetProvider = 'mpesa'; targetPayoutMethod = 'mpesa_phone'; }
    else if (newMethod === 'mpesa_till') { targetProvider = 'mpesa'; targetPayoutMethod = 'till'; }
    else if (newMethod === 'mpesa_paybill') { targetProvider = 'mpesa'; targetPayoutMethod = 'paybill'; }

    // Update use_own_api immediately
    form.use_own_api = useOwnApi;

    const existing = props.gateways.find(g => 
        g.provider === targetProvider && 
        (targetProvider !== 'mpesa' || g.payout_method === targetPayoutMethod)
    );

    // If we have an exact match including use_own_api for M-Pesa, prefer that.
    const strictMatch = props.gateways.find(g => 
        g.provider === targetProvider && 
        (targetProvider !== 'mpesa' || (g.payout_method === targetPayoutMethod && (g.use_own_api == useOwnApi)))
    );

    const recordToLoad = strictMatch || existing;

    if (recordToLoad) {
        form.phone_number = recordToLoad.phone_number || '';
        form.bank_name = recordToLoad.bank_name || '';
        form.bank_account = recordToLoad.bank_account || '';
        form.bank_paybill = recordToLoad.bank_paybill || '';
        form.till_number = recordToLoad.till_number || '';
        form.paybill_business_number = recordToLoad.paybill_business_number || '';
        form.paybill_account_number = recordToLoad.paybill_account_number || '';
        form.mpesa_consumer_key = recordToLoad.mpesa_consumer_key || '';
        form.mpesa_consumer_secret = recordToLoad.mpesa_consumer_secret || '';
        form.mpesa_shortcode = recordToLoad.mpesa_shortcode || '';
        form.mpesa_passkey = recordToLoad.mpesa_passkey || '';
        form.mpesa_env = recordToLoad.mpesa_env || 'sandbox';
        form.paystack_public_key = recordToLoad.paystack_public_key || '';
        form.paystack_secret_key = recordToLoad.paystack_secret_key || '';
        form.flutterwave_public_key = recordToLoad.flutterwave_public_key || '';
        form.flutterwave_secret_key = recordToLoad.flutterwave_secret_key || '';
        form.momo_api_user = recordToLoad.momo_api_user || '';
        form.momo_api_key = recordToLoad.momo_api_key || '';
        form.momo_subscription_key = recordToLoad.momo_subscription_key || '';
        form.momo_env = recordToLoad.momo_env || 'sandbox';
        form.airtel_client_id = recordToLoad.airtel_client_id || '';
        form.airtel_client_secret = recordToLoad.airtel_client_secret || '';
        form.airtel_env = recordToLoad.airtel_env || 'sandbox';
        form.use_own_api = recordToLoad.use_own_api === 1 || recordToLoad.use_own_api === true || false;
        form.is_active = recordToLoad.is_active ?? true;
    } else {
        // Reset fields if no existing config
        if (!useOwnApi) form.use_own_api = false;
    }
});


/**
 * Normalize and submit the payment gateway form
 */
const save = () => {
    form.is_active = true;
    switch (form.collection_method) {
        case 'phone':
            form.provider = 'mpesa';
            form.payout_method = 'mpesa_phone';
            form.use_own_api = false;
            break;
        case 'custom_mpesa':
            form.provider = 'mpesa';
            form.payout_method = 'mpesa_phone';
            form.use_own_api = true;
            break;
        case 'bank':
            form.provider = 'bank';
            form.payout_method = 'bank';
            form.use_own_api = false;
            break;
        case 'mpesa_till':
            form.provider = 'mpesa';
            form.payout_method = 'till';
            form.use_own_api = false;
            break;
        case 'mpesa_paybill':
            form.provider = 'mpesa';
            form.payout_method = 'paybill';
            form.use_own_api = false;
            break;
        case 'paystack':
            form.provider = 'paystack';
            form.payout_method = '';
            form.use_own_api = false;
            break;
        case 'flutterwave':
            form.provider = 'flutterwave';
            form.payout_method = '';
            form.use_own_api = false;
            break;
        case 'momo':
            form.provider = 'momo';
            form.payout_method = null;
            form.use_own_api = false;
            break;
        case 'airtel_money':
            form.provider = 'airtel_money';
            form.payout_method = null;
            form.use_own_api = false;
            break;
        default:
            form.provider = form.collection_method;
            form.payout_method = null;
            form.use_own_api = false;
    }

    form.post(route('settings.payment.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Payment settings saved successfully.');
        },
        onError: () => {
            toast.error('Failed to save payment settings.');
        },
    });
};
</script>

<template>
    <Layout>
        <Head title="Payment Settings" />

        <section
            class="rounded-xl border border-blue-400 bg-gray-200 p-6 shadow-sm dark:bg-gray-900"
        >
            <header>
                <h2 class="font-extrabold text-blue-700 dark:text-blue-400">
                    Payment Gateway
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Configure how you collect payments from customers.
                </p>
            </header>

            <form @submit.prevent="save" class="mt-6 space-y-6">
                <!-- Warning for Custom API -->
                <div v-if="form.collection_method === 'custom_mpesa' && country === 'KE'" class="rounded-lg border border-yellow-400 bg-yellow-50 p-4 dark:bg-yellow-900/30">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Custom API Enabled</h3>
                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                <p>
                                    When using your own M-Pesa API, payments are collected directly into your shortcode. 
                                    <strong>Automatic settlements and disbursements from the system are bypassed.</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collection Method -->
                <div>
                    <InputLabel value="Collection Method" />
                    <select
                        v-model="form.collection_method"
                        class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-800"
                    >
                        <template v-if="country === 'KE'">
                            <option v-if="supportedMethods.includes('mpesa') || supportedMethods.includes('phone')" value="phone">M-Pesa Phone (Automatic)</option>
                            <option v-if="supportedMethods.includes('custom_mpesa')" value="custom_mpesa">Custom M-Pesa API</option>
                            <option v-if="supportedMethods.includes('bank')" value="bank">Bank</option>
                            <option v-if="supportedMethods.includes('mpesa_till')" value="mpesa_till">M-Pesa Till</option>
                            <option v-if="supportedMethods.includes('mpesa_paybill')" value="mpesa_paybill">M-Pesa Paybill</option>
                        </template>
                        <option v-if="supportedMethods.includes('momo')" value="momo">MTN MoMo</option>
                        <option v-if="supportedMethods.includes('airtel_money')" value="airtel_money">Airtel Money</option>
                        <option v-if="supportedMethods.includes('paystack')" value="paystack">Paystack</option>
                        <option v-if="supportedMethods.includes('flutterwave')" value="flutterwave">Flutterwave</option>
                    </select>
                </div>

                <!-- Phone Collection -->
                <div v-if="form.collection_method === 'phone'">
                    <InputLabel value="Phone Number" />
                    <TextInput
                        v-model="form.phone_number"
                        class="mt-1 block w-full"
                        placeholder="e.g., 2547XXXXXXXX"
                    />
                </div>

                <!-- Bank Collection -->
                <div v-if="form.collection_method === 'bank'">
                    <InputLabel value="Bank Name" />
                    <TextInput
                        v-model="form.bank_name"
                        class="mt-1 block w-full"
                        placeholder="Enter bank name"
                    />
                    <InputLabel class="mt-3" value="Bank Account Number" />
                    <TextInput
                        v-model="form.bank_account"
                        class="mt-1 block w-full"
                        placeholder="Enter account number"
                    />
                    <InputLabel class="mt-3" value="Bank Paybill / Business Number" />
                    <TextInput
                        v-model="form.bank_paybill"
                        class="mt-1 block w-full"
                        placeholder="e.g., 400200 for Co-op, 247247 for Equity"
                    />
                </div>

                <!-- M-Pesa Till -->
                <div v-if="form.collection_method === 'mpesa_till'">
                    <InputLabel value="M-Pesa Till Number" />
                    <TextInput
                        v-model="form.till_number"
                        class="mt-1 block w-full"
                        placeholder="Enter till number"
                    />
                </div>

                <!-- M-Pesa Paybill -->
                <div v-if="form.collection_method === 'mpesa_paybill'">
                    <InputLabel value="Paybill Business Number" />
                    <TextInput
                        v-model="form.paybill_business_number"
                        class="mt-1 block w-full"
                        placeholder="Enter business number"
                    />
                    <InputLabel class="mt-3" value="Paybill Account Number" />
                    <TextInput
                        v-model="form.paybill_account_number"
                        class="mt-1 block w-full"
                        placeholder="Enter account number"
                    />
                </div>

                <!-- Paystack -->
                <div v-if="form.collection_method === 'paystack'">
                    <InputLabel value="Paystack Public Key" />
                    <TextInput
                        v-model="form.paystack_public_key"
                        class="mt-1 block w-full"
                        placeholder="pk_test_..."
                    />
                    <InputLabel class="mt-3" value="Paystack Secret Key" />
                    <TextInput
                        v-model="form.paystack_secret_key"
                        class="mt-1 block w-full"
                        type="password"
                        placeholder="sk_test_..."
                    />
                </div>

                <!-- Flutterwave -->
                <div v-if="form.collection_method === 'flutterwave'">
                    <InputLabel value="Flutterwave Public Key" />
                    <TextInput
                        v-model="form.flutterwave_public_key"
                        class="mt-1 block w-full"
                        placeholder="FLWPUBK_TEST-..."
                    />
                    <InputLabel class="mt-3" value="Flutterwave Secret Key" />
                    <TextInput
                        v-model="form.flutterwave_secret_key"
                        class="mt-1 block w-full"
                        type="password"
                        placeholder="FLWSECK_TEST-..."
                    />
                </div>

                <!-- MoMo -->
                <div v-if="form.collection_method === 'momo'">
                    <InputLabel value="MoMo API User" />
                    <TextInput
                        v-model="form.momo_api_user"
                        class="mt-1 block w-full"
                        placeholder="Enter MoMo API User"
                    />
                    <InputLabel class="mt-3" value="MoMo API Key" />
                    <TextInput
                        v-model="form.momo_api_key"
                        class="mt-1 block w-full"
                        type="password"
                        placeholder="Enter MoMo API Key"
                    />
                    <InputLabel class="mt-3" value="MoMo Subscription Key" />
                    <TextInput
                        v-model="form.momo_subscription_key"
                        class="mt-1 block w-full"
                        type="password"
                        placeholder="Enter MoMo Subscription Key"
                    />
                    <InputLabel class="mt-3" value="Environment" />
                    <select
                        v-model="form.momo_env"
                        class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-800"
                    >
                        <option value="sandbox">Sandbox</option>
                        <option value="production">Production</option>
                    </select>
                </div>

                <!-- Airtel Money -->
                <div v-if="form.collection_method === 'airtel_money'">
                    <InputLabel value="Airtel Client ID" />
                    <TextInput
                        v-model="form.airtel_client_id"
                        class="mt-1 block w-full"
                        placeholder="Enter Airtel Client ID"
                    />
                    <InputLabel class="mt-3" value="Airtel Client Secret" />
                    <TextInput
                        v-model="form.airtel_client_secret"
                        class="mt-1 block w-full"
                        type="password"
                        placeholder="Enter Airtel Client Secret"
                    />
                    <InputLabel class="mt-3" value="Environment" />
                    <select
                        v-model="form.airtel_env"
                        class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-800"
                    >
                        <option value="sandbox">Sandbox</option>
                        <option value="production">Production</option>
                    </select>
                </div>

                <!-- Custom M-Pesa API Settings -->
                <div v-if="form.collection_method === 'custom_mpesa'" class="border-t border-gray-300 pt-6 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Custom M-Pesa API Credentials</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel value="Consumer Key" />
                            <TextInput
                                v-model="form.mpesa_consumer_key"
                                class="mt-1 block w-full"
                                placeholder="Enter M-Pesa Consumer Key"
                            />
                        </div>
                        <div>
                            <InputLabel value="Consumer Secret" />
                            <TextInput
                                v-model="form.mpesa_consumer_secret"
                                class="mt-1 block w-full"
                                type="password"
                                placeholder="Enter M-Pesa Consumer Secret"
                            />
                        </div>
                        <div>
                            <InputLabel value="Shortcode" />
                            <TextInput
                                v-model="form.mpesa_shortcode"
                                class="mt-1 block w-full"
                                placeholder="e.g., 174379"
                            />
                        </div>
                        <div>
                            <InputLabel value="Passkey" />
                            <TextInput
                                v-model="form.mpesa_passkey"
                                class="mt-1 block w-full"
                                type="password"
                                placeholder="Enter Lipa Na M-Pesa Passkey"
                            />
                        </div>
                        <div>
                            <InputLabel value="Environment" />
                            <select
                                v-model="form.mpesa_env"
                                class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-800"
                            >
                                <option value="sandbox">Sandbox</option>
                                <option value="production">Production</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between pt-4">
                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-if="form.recentlySuccessful"
                            class="text-sm text-green-600 dark:text-green-400"
                        >
                            Saved successfully.
                        </p>
                    </Transition>

                    <PrimaryButton :disabled="form.processing"
                        >Save</PrimaryButton
                    >
                </div>
            </form>
        </section>
    </Layout>
</template>
