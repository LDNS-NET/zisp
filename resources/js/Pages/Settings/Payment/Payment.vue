<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Layout from '../Layout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    gateways: { type: Array, default: () => [] },
    phone_number: { type: String, default: '' },
});

// Use the first gateway record if any
const existing = props.gateways[0] || {};

// Helper to map payout_method to collection_method
const getInitialCollectionMethod = (payoutMethod) => {
    switch (payoutMethod) {
        case 'mpesa_phone': return 'phone';
        case 'bank': return 'bank';
        case 'till': return 'mpesa_till';
        case 'paybill': return 'mpesa_paybill';
        default: return 'phone';
    }
};

const form = useForm({
    provider: existing.provider || 'mpesa',
    payout_method: existing.payout_method || 'mpesa_phone',
    collection_method: getInitialCollectionMethod(existing.payout_method),
    phone_number: existing.phone_number || props.phone_number || '',
    bank_name: existing.bank_name || '',
    bank_account: existing.bank_account || '',
    bank_paybill: existing.bank_paybill || '',
    till_number: existing.till_number || '',
    paybill_business_number: existing.paybill_business_number || '',
    paybill_account_number: existing.paybill_account_number || '',
    mpesa_consumer_key: existing.mpesa_consumer_key || '',
    mpesa_consumer_secret: existing.mpesa_consumer_secret || '',
    mpesa_shortcode: existing.mpesa_shortcode || '',
    mpesa_passkey: existing.mpesa_passkey || '',
    mpesa_env: existing.mpesa_env || 'sandbox',
    use_own_api: existing.use_own_api === 1 || existing.use_own_api === true || false,
});


/**
 * Normalize and submit the payment gateway form
 */
const save = () => {
    switch (form.collection_method) {
        case 'phone':
            form.provider = 'mpesa';
            form.payout_method = 'mpesa_phone';
            break;
        case 'bank':
            form.provider = 'bank';
            form.payout_method = 'bank';
            break;
        case 'mpesa_till':
            form.provider = 'mpesa';
            form.payout_method = 'till';
            break;
        case 'mpesa_paybill':
            form.provider = 'mpesa';
            form.payout_method = 'paybill';
            break;
        default:
            form.provider = 'custom';
            form.payout_method = '';
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
                <div v-if="form.use_own_api" class="rounded-lg border border-yellow-400 bg-yellow-50 p-4 dark:bg-yellow-900/30">
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
                        <option value="phone">M-Pesa Phone</option>
                        <option value="bank">Bank</option>
                        <option value="mpesa_till">M-Pesa Till</option>
                        <option value="mpesa_paybill">M-Pesa Paybill</option>
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

                <!-- Custom M-Pesa API Settings -->
                <div class="border-t border-gray-300 pt-6 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Custom M-Pesa API</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Use your own M-Pesa Daraja API credentials for collections.
                            </p>
                        </div>
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                v-model="form.use_own_api"
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Enable Custom API</span>
                        </div>
                    </div>

                    <div v-if="form.use_own_api" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
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
